<?php
/**
 * Puerto de ImportacionService.java. El archivo no se guarda: se valida fila por fila con las
 * mismas reglas del registro manual, y solo las filas válidas quedan en una vista previa temporal
 * — persistida en la tabla import_preview (ver basedatos/04_import_preview_php.sql) porque PHP no
 * tiene el ConcurrentHashMap en memoria que usaba Java entre una solicitud y la siguiente.
 */
class ImportacionService
{
    // PHP no admite const con valor array hasta la 5.6 — propiedades estáticas en vez de const.
    public static $COLUMNAS = array('id_caso', 'presunto_agresor', 'victima_iniciales', 'cedula', 'area_asignatura',
        'institucion', 'sede', 'municipio', 'provincia', 'clasificacion', 'ruta', 'n_radicado', 'resolucion_seb',
        'resolucion_rectoral', 'ubicacion', 'fecha_ocurrencia', 'lugar_ocurrencia', 'disciplinario',
        'decision_comite', 'penal');

    public static $OBLIGATORIAS = array('presunto_agresor', 'victima_iniciales', 'cedula',
        'institucion', 'sede', 'municipio', 'provincia', 'clasificacion');

    public static function plantillaCsv()
    {
        return "\xEF\xBB\xBF" . implode(';', self::$COLUMNAS) . "\r\n";
    }

    // ------------------------------------------------------------------ vista previa

    private static function victimas($celda)
    {
        if ($celda === null) return array();
        $resultado = array();
        foreach (explode(';', $celda) as $v) {
            $n = Sci_Validator::normalizarIniciales($v);
            if ($n !== null) $resultado[] = $n;
        }
        return $resultado;
    }

    /** Acepta AAAA-MM-DD o D/M/AAAA; devuelve siempre Y-m-d, o null si no es una fecha real. */
    private static function fecha($t)
    {
        if ($t === null) return null;
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $t, $m)) {
            return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $t : null;
        }
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $t, $m)) {
            $d = (int) $m[1]; $mo = (int) $m[2]; $y = (int) $m[3];
            return checkdate($mo, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : null;
        }
        return null;
    }

    private static function claveClasificacion($t)
    {
        if ($t === null) return null;
        $x = str_replace(' ', '', str_replace('TIPO', '', mb_strtoupper($t, 'UTF-8')));
        if ($x === 'I' || $x === '1') return 'Tipo I';
        if ($x === 'II' || $x === '2') return 'Tipo II';
        if ($x === 'III' || $x === '3') return 'Tipo III';
        return null;
    }

    /** null (celda vacía) -> false, igual que en Java: la ruta en blanco se asume inactiva, no es error. */
    private static function ruta($t)
    {
        if ($t === null) return false;
        $x = mb_strtolower($t, 'UTF-8');
        if ($x === 'activa' || $x === 'si' || $x === 'sí' || $x === 'true' || $x === '1') return true;
        if ($x === 'inactiva' || $x === 'no' || $x === 'false' || $x === '0') return false;
        return null; // valor irreconocible: error (distinto de celda vacía)
    }

    private static function buscarIgnoreCase($tabla, $colId, $colNombre, $nombre, $colPadre = null, $padreId = null)
    {
        $pdo = Sci_Db::conexion();
        if ($colPadre !== null) {
            $stmt = $pdo->prepare("SELECT $colId FROM $tabla WHERE LOWER($colNombre) = LOWER(?) AND $colPadre = ?");
            $stmt->execute(array($nombre, $padreId));
        } else {
            $stmt = $pdo->prepare("SELECT $colId FROM $tabla WHERE LOWER($colNombre) = LOWER(?)");
            $stmt->execute(array($nombre));
        }
        $fila = $stmt->fetch();
        return $fila === false ? null : (int) $fila[$colId];
    }

    public static function vistaPrevia($sesion, $nombreArchivo, $contenido)
    {
        self::purgar();
        if ($contenido === null || $contenido === '') {
            throw Sci_Api_Exception::solicitudInvalida('El archivo está vacío.');
        }
        $nombre = $nombreArchivo === null ? 'archivo.csv' : preg_replace('/[\r\n\\\\\/]/', '_', $nombreArchivo);
        if (strlen($nombre) > 150) $nombre = substr($nombre, -150);
        if (strtolower(substr($nombre, -4)) !== '.csv') {
            throw Sci_Api_Exception::solicitudInvalida('Seleccione un archivo con extensión .csv.');
        }

        $texto = Sci_Csv::decodificar($contenido);
        $finPrimeraLinea = strpos($texto, "\n");
        $primeraLinea = $finPrimeraLinea === false ? $texto : substr($texto, 0, $finPrimeraLinea);
        $delimitador = Sci_Csv::detectarDelimitador($primeraLinea);

        $leido = Sci_Csv::leer($texto, $delimitador);
        $encabezados = $leido['encabezados'];
        $filasArchivo = $leido['filas'];

        $faltan = array();
        foreach (self::$OBLIGATORIAS as $c) {
            if (!in_array($c, $encabezados, true)) $faltan[] = $c;
        }
        if (count($faltan) > 0) {
            throw new Sci_Api_Exception(422, 'COLUMNAS_FALTANTES',
                'Al archivo le faltan estas columnas: ' . implode(', ', $faltan) . '. Use la plantilla del sistema.');
        }
        if (count($filasArchivo) === 0) {
            throw Sci_Api_Exception::solicitudInvalida('El archivo no tiene filas de datos.');
        }
        $max = (int) Sci_Config::get('importacion')['max_filas'];
        if (count($filasArchivo) > $max) {
            throw new Sci_Api_Exception(422, 'DEMASIADAS_FILAS',
                'El archivo tiene ' . count($filasArchivo) . ' filas y el máximo es ' . $max . '. Divídalo en varios archivos.');
        }

        $validas = array();
        $rechazadas = array();
        $vista = array();
        $idsArchivo = array();
        $nombresPorCedula = array();
        $contador = 1; // la fila 1 del archivo es el encabezado
        $hoy = date('Y-m-d');

        foreach ($filasArchivo as $r) {
            $fila = ++$contador;
            $errores = array();

            $d = array(
                'idCaso'             => Sci_Validator::limpiar(isset($r['id_caso']) ? $r['id_caso'] : null),
                'presuntoAgresor'    => Sci_Validator::limpiar(isset($r['presunto_agresor']) ? $r['presunto_agresor'] : null),
                'victimas'           => self::victimas(isset($r['victima_iniciales']) ? $r['victima_iniciales'] : null),
                'cedula'             => Sci_Validator::limpiar(isset($r['cedula']) ? $r['cedula'] : null),
                'areaAsignatura'     => Sci_Validator::limpiar(isset($r['area_asignatura']) ? $r['area_asignatura'] : null),
                'nRadicado'          => Sci_Validator::limpiar(isset($r['n_radicado']) ? $r['n_radicado'] : null),
                'resolucionSeb'      => Sci_Validator::limpiar(isset($r['resolucion_seb']) ? $r['resolucion_seb'] : null),
                'resolucionRectoral' => Sci_Validator::limpiar(isset($r['resolucion_rectoral']) ? $r['resolucion_rectoral'] : null),
                'ubicacionAgresor'   => Sci_Validator::limpiar(isset($r['ubicacion']) ? $r['ubicacion'] : null),
                'fechaOcurrencia'    => self::fecha(Sci_Validator::limpiar(isset($r['fecha_ocurrencia']) ? $r['fecha_ocurrencia'] : null)),
                'lugarOcurrencia'    => Sci_Validator::limpiar(isset($r['lugar_ocurrencia']) ? $r['lugar_ocurrencia'] : null),
                'disciplinario'      => Sci_Validator::limpiar(isset($r['disciplinario']) ? $r['disciplinario'] : null),
                'decisionComite'     => Sci_Validator::limpiar(isset($r['decision_comite']) ? $r['decision_comite'] : null),
                'penal'              => Sci_Validator::limpiar(isset($r['penal']) ? $r['penal'] : null),
            );
            $fallos = Sci_Validator::validar($d, $hoy);
            $celdaFecha = Sci_Validator::limpiar(isset($r['fecha_ocurrencia']) ? $r['fecha_ocurrencia'] : null);
            if ($celdaFecha !== null && $d['fechaOcurrencia'] === null) {
                $fallos['fechaOcurrencia'] = 'La fecha debe estar en formato AAAA-MM-DD o DD/MM/AAAA.';
            }
            foreach ($fallos as $msg) $errores[] = $msg;

            // ubicación geográfica por nombre: todo es opcional, pero si viene un nombre debe existir en el catálogo
            $sedeId = null;
            $nProv = Sci_Validator::limpiar(isset($r['provincia']) ? $r['provincia'] : null);
            $nMun = Sci_Validator::limpiar(isset($r['municipio']) ? $r['municipio'] : null);
            $nIe = Sci_Validator::limpiar(isset($r['institucion']) ? $r['institucion'] : null);
            $nSede = Sci_Validator::limpiar(isset($r['sede']) ? $r['sede'] : null);
            if ($nProv !== null || $nMun !== null || $nIe !== null || $nSede !== null) {
                $provId = $nProv === null ? null : self::buscarIgnoreCase('provincia', 'id_provincia', 'nombre', $nProv);
                if ($nProv !== null && $provId === null) {
                    $errores[] = 'La provincia no existe en el catálogo.';
                } else {
                    $munId = ($nMun === null || $provId === null) ? null : self::buscarIgnoreCase('municipio', 'id_municipio', 'nombre', $nMun, 'id_provincia', $provId);
                    if ($nMun !== null && $munId === null) {
                        $errores[] = 'El municipio no existe en esa provincia del catálogo.';
                    } else {
                        $ieId = ($nIe === null || $munId === null) ? null : self::buscarIgnoreCase('institucion_educativa', 'id_institucion', 'nombre', $nIe, 'id_municipio', $munId);
                        if ($nIe !== null && $ieId === null) {
                            $errores[] = 'La institución educativa no existe en ese municipio del catálogo.';
                        } elseif ($nSede !== null) {
                            $sedeId = $ieId === null ? null : self::buscarIgnoreCase('sede', 'id_sede', 'nombre', $nSede, 'id_institucion', $ieId);
                            if ($sedeId === null) $errores[] = 'La sede no existe en esa institución del catálogo.';
                        }
                    }
                }
            }

            $celdaCl = Sci_Validator::limpiar(isset($r['clasificacion']) ? $r['clasificacion'] : null);
            $clId = null;
            if ($celdaCl !== null) {
                $nCl = self::claveClasificacion($celdaCl);
                $clId = $nCl === null ? null : self::buscarIgnoreCase('clasificacion_siuce', 'id_clasificacion', 'nombre', $nCl);
                if ($clId === null) $errores[] = 'La clasificación debe ser Tipo I, Tipo II o Tipo III.';
            }

            $rutaActiva = self::ruta(Sci_Validator::limpiar(isset($r['ruta']) ? $r['ruta'] : null));
            if ($rutaActiva === null) $errores[] = 'La ruta debe ser Activa o Inactiva.';

            if ($d['idCaso'] !== null && !isset($fallos['idCaso'])) {
                if (isset($idsArchivo[$d['idCaso']])) {
                    $errores[] = 'El ID del caso está repetido dentro del archivo.';
                } else {
                    $idsArchivo[$d['idCaso']] = true;
                    $pdoChk = Sci_Db::conexion();
                    $stmtChk = $pdoChk->prepare('SELECT 1 FROM caso WHERE id_caso = ?');
                    $stmtChk->execute(array($d['idCaso']));
                    if ($stmtChk->fetch() !== false) $errores[] = 'Ya existe un caso con ese ID.';
                }
            }
            if ($d['cedula'] !== null && !isset($fallos['cedula'])) {
                $pdo = Sci_Db::conexion();
                $stmt = $pdo->prepare('SELECT nombre FROM presunto_agresor WHERE cedula = ?');
                $stmt->execute(array($d['cedula']));
                $existenteNombre = $stmt->fetchColumn();
                if ($d['presuntoAgresor'] !== null && !isset($fallos['presuntoAgresor'])) {
                    $nombreNorm = mb_strtolower($d['presuntoAgresor'], 'UTF-8');
                    if (isset($nombresPorCedula[$d['cedula']]) && $nombresPorCedula[$d['cedula']] !== $nombreNorm) {
                        $errores[] = 'La cédula aparece en el archivo con nombres distintos.';
                    } elseif (!isset($nombresPorCedula[$d['cedula']])) {
                        $nombresPorCedula[$d['cedula']] = $nombreNorm;
                    }
                    if ($existenteNombre !== false && strcasecmp($existenteNombre, $d['presuntoAgresor']) !== 0) {
                        $errores[] = 'La cédula ya está registrada con otro nombre; regístrelo manualmente para confirmarlo.';
                    }
                } elseif ($existenteNombre === false) {
                    $errores[] = 'Esta cédula no está registrada; indique también el nombre del presunto agresor.';
                }
            }

            $idMostrar = $d['idCaso'];
            if (count($errores) === 0) {
                $validas[] = array('fila' => $fila, 'datos' => $d, 'sedeId' => $sedeId, 'clasificacionId' => $clId, 'rutaActiva' => (bool) $rutaActiva);
                $vista[] = array('fila' => $fila, 'idCaso' => $idMostrar, 'valida' => true, 'errores' => array());
            } else {
                $rechazadas[] = array('fila' => $fila, 'idCaso' => $idMostrar, 'motivo' => implode(' ', $errores));
                $vista[] = array('fila' => $fila, 'idCaso' => $idMostrar, 'valida' => false, 'errores' => $errores);
            }
        }

        $token = self::uuid();
        $cfg = Sci_Config::get('importacion');
        $pdo = Sci_Db::conexion();
        $stmt = $pdo->prepare(
            'INSERT INTO import_preview (token, id_usuario, archivo, total, rechazadas_count, rechazadas_json, pendientes_json, fecha_expira)
             VALUES (?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))'
        );
        $stmt->execute(array(
            $token, $sesion['id'], $nombre, count($filasArchivo), count($rechazadas),
            json_encode($rechazadas), json_encode($validas), (int) $cfg['minutos_vista_previa'],
        ));

        return array(
            'token'      => $token,
            'archivo'    => $nombre,
            'total'      => count($filasArchivo),
            'validas'    => count($validas),
            'rechazadas' => count($rechazadas),
            'filas'      => $vista,
        );
    }

    private static function uuid()
    {
        $b = Sci_Bcrypt::bytesAleatorios(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        $hex = bin2hex($b);
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-'
            . substr($hex, 16, 4) . '-' . substr($hex, 20, 12);
    }

    private static function purgar()
    {
        Sci_Db::conexion()->exec('DELETE FROM import_preview WHERE fecha_expira < NOW()');
    }

    public static function cancelar($sesion, $token)
    {
        $pdo = Sci_Db::conexion();
        $stmt = $pdo->prepare('DELETE FROM import_preview WHERE token = ? AND id_usuario = ?');
        $stmt->execute(array($token, $sesion['id']));
    }

    // ------------------------------------------------------------------ confirmación

    /** Todo o nada: si falla una fila, no se guarda ninguna. */
    public static function confirmar($sesion, $token)
    {
        self::purgar();
        $pdo = Sci_Db::conexion();

        if ($token === null) {
            throw new Sci_Api_Exception(410, 'VISTA_PREVIA_VENCIDA', 'La vista previa venció o no existe. Cargue el archivo de nuevo.');
        }

        $pdo->beginTransaction();
        // SELECT ... FOR UPDATE + DELETE en la misma transacción: dos confirmaciones casi
        // simultáneas del mismo token no pueden colarse ambas (una gana la fila, la otra ya no la encuentra).
        $stmt = $pdo->prepare('SELECT * FROM import_preview WHERE token = ? FOR UPDATE');
        $stmt->execute(array($token));
        $p = $stmt->fetch();

        if ($p === false || (int) $p['id_usuario'] !== (int) $sesion['id']) {
            $pdo->rollBack();
            throw new Sci_Api_Exception(410, 'VISTA_PREVIA_VENCIDA', 'La vista previa venció o no existe. Cargue el archivo de nuevo.');
        }
        $pendientes = json_decode($p['pendientes_json'], true);
        $rechazadas = json_decode($p['rechazadas_json'], true);
        if (count($pendientes) === 0) {
            $pdo->rollBack();
            throw Sci_Api_Exception::conflicto('SIN_FILAS_VALIDAS', 'No hay filas válidas para importar.');
        }
        $pdo->prepare('DELETE FROM import_preview WHERE token = ?')->execute(array($token)); // un solo uso

        try {
            foreach ($pendientes as $f) {
                if ($f['datos']['idCaso'] !== null) {
                    $chk = $pdo->prepare('SELECT 1 FROM caso WHERE id_caso = ?');
                    $chk->execute(array($f['datos']['idCaso']));
                    if ($chk->fetch() !== false) {
                        throw Sci_Api_Exception::conflicto('ID_DUPLICADO',
                            'Mientras tanto se registró un caso con el ID de la fila ' . $f['fila'] . '. No se importó nada; cargue el archivo de nuevo.');
                    }
                }
                CasoService::persistirNuevo($sesion['id'], $f['datos'], $f['sedeId'], $f['clasificacionId'], $f['rutaActiva'], false);
            }

            $stmtImp = $pdo->prepare(
                'INSERT INTO importacion_csv (id_usuario, nombre_archivo, fecha, filas_total, filas_importadas, filas_rechazadas)
                 VALUES (?, ?, NOW(), ?, ?, ?)'
            );
            $stmtImp->execute(array($sesion['id'], $p['archivo'], (int) $p['total'], count($pendientes), (int) $p['rechazadas_count']));
            $idImportacion = (int) $pdo->lastInsertId();

            AuditoriaService::registrar($sesion['id'], null, 'IMPORTAR_CSV',
                'importación ' . $idImportacion . ': ' . count($pendientes) . ' importadas, ' . $p['rechazadas_count'] . ' rechazadas');

            $pdo->commit();
        } catch (Exception $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $ex;
        }

        return array(
            'idImportacion'  => $idImportacion,
            'total'          => (int) $p['total'],
            'importadas'     => count($pendientes),
            'rechazadas'     => (int) $p['rechazadas_count'],
            'filasRechazadas' => $rechazadas,
        );
    }
}
