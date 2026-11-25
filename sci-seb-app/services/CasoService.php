<?php
/**
 * Puerto de CasoService.java (la fase de mayor riesgo del plan): CRUD de casos, resolución del
 * presunto agresor con el flujo "borrador" (nombre sin cédula todavía), bloqueo optimista real
 * sobre caso.version, los 12 pasos de la ruta de atención, y archivar/reactivar.
 */
class CasoService
{
    // ------------------------------------------------------------------ construcción de Datos

    /** A partir del cuerpo JSON de la solicitud, aplica limpiar()/normalizarIniciales() como datosDe() en Java. */
    public static function datosDe($cuerpo)
    {
        $victimasCrudas = isset($cuerpo['victimas']) && is_array($cuerpo['victimas']) ? $cuerpo['victimas'] : array();
        $victimas = array();
        foreach ($victimasCrudas as $v) {
            $n = Sci_Validator::normalizarIniciales($v);
            if ($n !== null) $victimas[] = $n;
        }
        return array(
            'idCaso'             => Sci_Validator::limpiar(isset($cuerpo['idCaso']) ? $cuerpo['idCaso'] : null),
            'presuntoAgresor'    => Sci_Validator::limpiar(isset($cuerpo['presuntoAgresor']) ? $cuerpo['presuntoAgresor'] : null),
            'victimas'           => $victimas,
            'cedula'             => Sci_Validator::limpiar(isset($cuerpo['cedula']) ? $cuerpo['cedula'] : null),
            'areaAsignatura'     => Sci_Validator::limpiar(isset($cuerpo['areaAsignatura']) ? $cuerpo['areaAsignatura'] : null),
            'nRadicado'          => Sci_Validator::limpiar(isset($cuerpo['nRadicado']) ? $cuerpo['nRadicado'] : null),
            'resolucionSeb'      => Sci_Validator::limpiar(isset($cuerpo['resolucionSeb']) ? $cuerpo['resolucionSeb'] : null),
            'resolucionRectoral' => Sci_Validator::limpiar(isset($cuerpo['resolucionRectoral']) ? $cuerpo['resolucionRectoral'] : null),
            'ubicacionAgresor'   => Sci_Validator::limpiar(isset($cuerpo['ubicacionAgresor']) ? $cuerpo['ubicacionAgresor'] : null),
            'fechaOcurrencia'    => (isset($cuerpo['fechaOcurrencia']) && $cuerpo['fechaOcurrencia'] !== '') ? $cuerpo['fechaOcurrencia'] : null,
            'lugarOcurrencia'    => Sci_Validator::limpiar(isset($cuerpo['lugarOcurrencia']) ? $cuerpo['lugarOcurrencia'] : null),
            'disciplinario'      => Sci_Validator::limpiar(isset($cuerpo['disciplinario']) ? $cuerpo['disciplinario'] : null),
            'decisionComite'     => Sci_Validator::limpiar(isset($cuerpo['decisionComite']) ? $cuerpo['decisionComite'] : null),
            'penal'              => Sci_Validator::limpiar(isset($cuerpo['penal']) ? $cuerpo['penal'] : null),
        );
    }

    // ------------------------------------------------------------------ consulta

    public static function listar($filtro, $pagina, $tamano)
    {
        $pdo = Sci_Db::conexion();
        $tamano = max(1, min((int) $tamano, 100));
        $pagina = max(0, (int) $pagina);

        list($where, $params) = Sci_CasoQuery::build($filtro);

        $stmtTotal = $pdo->prepare('SELECT COUNT(*) ' . Sci_CasoQuery::JOINS . ' ' . $where);
        $stmtTotal->execute($params);
        $total = (int) $stmtTotal->fetchColumn();

        $sql = 'SELECT c.id, c.id_caso, c.nombre_agresor_borrador, c.ruta_activa, c.estado,
                       c.fecha_ultima_edicion, ag.nombre AS ag_nombre, ag.cedula AS ag_cedula,
                       ie.nombre AS ie_nombre, se.nombre AS se_nombre, mu.nombre AS mu_nombre,
                       pr.nombre AS pr_nombre, cl.nombre AS cl_nombre, edi.nombre AS editado_por_nombre,
                       (SELECT COUNT(*) FROM caso_paso cp WHERE cp.id_caso = c.id AND cp.efectuado = 1) AS pasos_efectuados
                  ' . Sci_CasoQuery::JOINS . "
                  JOIN usuario edi ON c.editado_por = edi.id_usuario
                  $where
                 ORDER BY c.fecha_ultima_edicion DESC
                 LIMIT $tamano OFFSET " . ($pagina * $tamano);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $filas = $stmt->fetchAll();

        $ids = array();
        foreach ($filas as $f) $ids[] = (int) $f['id'];
        $victimasPorCaso = self::victimasPorCasos($ids);

        $contenido = array();
        foreach ($filas as $f) {
            $esBorrador = $f['ag_nombre'] === null && $f['nombre_agresor_borrador'] !== null;
            $contenido[] = array(
                'id'                 => (int) $f['id'],
                'idCaso'             => $f['id_caso'],
                'presuntoAgresor'    => $f['ag_nombre'] !== null ? $f['ag_nombre'] : $f['nombre_agresor_borrador'],
                'cedula'             => $f['ag_nombre'] !== null ? Sci_Validator::enmascararCedula($f['ag_cedula']) : null,
                'agresorBorrador'    => $esBorrador,
                'victimas'           => isset($victimasPorCaso[(int) $f['id']]) ? $victimasPorCaso[(int) $f['id']] : array(),
                'institucion'        => $f['ie_nombre'],
                'sede'               => $f['se_nombre'],
                'municipio'          => $f['mu_nombre'],
                'provincia'          => $f['pr_nombre'],
                'clasificacion'      => $f['cl_nombre'],
                'rutaActiva'         => (bool) $f['ruta_activa'],
                'estado'             => $f['estado'],
                'pasosEfectuados'    => (int) $f['pasos_efectuados'],
                'fechaUltimaEdicion' => $f['fecha_ultima_edicion'],
                'editadoPor'         => $f['editado_por_nombre'],
            );
        }

        return array('contenido' => $contenido, 'total' => $total, 'pagina' => $pagina, 'tamano' => $tamano);
    }

    private static function victimasPorCasos($ids)
    {
        $resultado = array();
        if (count($ids) === 0) return $resultado;
        $pdo = Sci_Db::conexion();
        $marcadores = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT id_caso, iniciales FROM caso_victima WHERE id_caso IN ($marcadores) ORDER BY id_victima");
        $stmt->execute($ids);
        foreach ($stmt->fetchAll() as $f) {
            $idCaso = (int) $f['id_caso'];
            if (!isset($resultado[$idCaso])) $resultado[$idCaso] = array();
            $resultado[$idCaso][] = $f['iniciales'];
        }
        return $resultado;
    }

    public static function indicadores()
    {
        $pdo = Sci_Db::conexion();
        $total = (int) $pdo->query("SELECT COUNT(*) FROM caso WHERE estado = 'ACTIVO'")->fetchColumn();
        $rutaActiva = (int) $pdo->query("SELECT COUNT(*) FROM caso WHERE estado = 'ACTIVO' AND ruta_activa = 1")->fetchColumn();
        $tipoI = (int) $pdo->query("SELECT COUNT(*) FROM caso c JOIN clasificacion_siuce cl ON c.id_clasificacion = cl.id_clasificacion
                                      WHERE c.estado = 'ACTIVO' AND cl.nombre = 'Tipo I'")->fetchColumn();
        $tipoIII = (int) $pdo->query("SELECT COUNT(*) FROM caso c JOIN clasificacion_siuce cl ON c.id_clasificacion = cl.id_clasificacion
                                      WHERE c.estado = 'ACTIVO' AND cl.nombre = 'Tipo III'")->fetchColumn();
        $rutaCompleta = (int) $pdo->query("SELECT COUNT(*) FROM caso c WHERE c.estado = 'ACTIVO' AND 12 = (
                                              SELECT COUNT(*) FROM caso_paso cp WHERE cp.id_caso = c.id AND cp.efectuado = 1)")->fetchColumn();
        return array('total' => $total, 'rutaActiva' => $rutaActiva, 'tipoI' => $tipoI, 'tipoIII' => $tipoIII, 'rutaCompleta' => $rutaCompleta);
    }

    /** @return array fila cruda de la BD, o lanza NO_ENCONTRADO. */
    public static function buscar($id)
    {
        $pdo = Sci_Db::conexion();
        $stmt = $pdo->prepare(
            'SELECT c.*, ag.nombre AS ag_nombre, ag.cedula AS ag_cedula, ag.area_asignatura AS ag_area,
                    se.nombre AS se_nombre, ie.id_institucion AS ie_id, ie.nombre AS ie_nombre,
                    mu.id_municipio AS mu_id, mu.nombre AS mu_nombre, pr.id_provincia AS pr_id, pr.nombre AS pr_nombre,
                    cl.nombre AS cl_nombre,
                    cre.nombre AS creado_por_nombre, crea.nombre AS creado_por_area,
                    edi.nombre AS editado_por_nombre, edia.nombre AS editado_por_area
               FROM caso c
               LEFT JOIN presunto_agresor ag ON c.id_agresor = ag.id_agresor
               LEFT JOIN sede se ON c.id_sede = se.id_sede
               LEFT JOIN institucion_educativa ie ON se.id_institucion = ie.id_institucion
               LEFT JOIN municipio mu ON ie.id_municipio = mu.id_municipio
               LEFT JOIN provincia pr ON mu.id_provincia = pr.id_provincia
               LEFT JOIN clasificacion_siuce cl ON c.id_clasificacion = cl.id_clasificacion
               JOIN usuario cre ON c.creado_por = cre.id_usuario
               JOIN area crea ON cre.id_area = crea.id_area
               JOIN usuario edi ON c.editado_por = edi.id_usuario
               JOIN area edia ON edi.id_area = edia.id_area
              WHERE c.id = ?'
        );
        $stmt->execute(array((int) $id));
        $fila = $stmt->fetch();
        if ($fila === false) throw Sci_Api_Exception::noEncontrado('No existe el caso ' . $id . '.');
        return $fila;
    }

    public static function buscarEditable($id)
    {
        $c = self::buscar($id);
        if ($c['estado'] === 'ARCHIVADO') {
            throw Sci_Api_Exception::conflicto('CASO_ARCHIVADO',
                'El caso está archivado y no se puede modificar. Pida al Administrador que lo reactive.');
        }
        return $c;
    }

    public static function obtener($id)
    {
        return self::detalle(self::buscar($id));
    }

    private static function pasosDe($idCaso)
    {
        $pdo = Sci_Db::conexion();
        $stmt = $pdo->prepare(
            'SELECT cp.id_paso, pr.nombre AS paso_nombre, cp.efectuado, u.nombre AS usuario_nombre,
                    a.nombre AS area_nombre, cp.fecha_efectuado, cp.nota
               FROM caso_paso cp
               JOIN paso_ruta pr ON cp.id_paso = pr.id_paso
               LEFT JOIN usuario u ON cp.id_usuario = u.id_usuario
               LEFT JOIN area a ON u.id_area = a.id_area
              WHERE cp.id_caso = ?
              ORDER BY cp.id_paso ASC'
        );
        $stmt->execute(array((int) $idCaso));
        $pasos = array();
        $hechos = 0;
        foreach ($stmt->fetchAll() as $f) {
            $efectuado = (bool) $f['efectuado'];
            if ($efectuado) $hechos++;
            $pasos[] = array(
                'id'        => (int) $f['id_paso'],
                'nombre'    => $f['paso_nombre'],
                'efectuado' => $efectuado,
                'usuario'   => $f['usuario_nombre'],
                'area'      => $f['area_nombre'],
                'fecha'     => $f['fecha_efectuado'],
                'nota'      => $f['nota'],
            );
        }
        return array($pasos, $hechos);
    }

    private static function victimasDe($idCaso)
    {
        $pdo = Sci_Db::conexion();
        $stmt = $pdo->prepare('SELECT iniciales FROM caso_victima WHERE id_caso = ? ORDER BY id_victima');
        $stmt->execute(array((int) $idCaso));
        $resultado = array();
        foreach ($stmt->fetchAll() as $f) $resultado[] = $f['iniciales'];
        return $resultado;
    }

    public static function detalle($c)
    {
        list($pasos, $hechos) = self::pasosDe($c['id']);
        $esBorrador = $c['ag_nombre'] === null && $c['nombre_agresor_borrador'] !== null;
        return array(
            'id'                 => (int) $c['id'],
            'idCaso'             => $c['id_caso'],
            'presuntoAgresor'    => $c['ag_nombre'] !== null ? $c['ag_nombre'] : $c['nombre_agresor_borrador'],
            'cedula'             => $c['ag_nombre'] !== null ? $c['ag_cedula'] : null,
            'agresorBorrador'    => $esBorrador,
            'areaAsignatura'     => $c['ag_nombre'] !== null ? $c['ag_area'] : null,
            'victimas'           => self::victimasDe($c['id']),
            'provinciaId'        => $c['pr_id'] !== null ? (int) $c['pr_id'] : null,
            'provincia'          => $c['pr_nombre'],
            'municipioId'        => $c['mu_id'] !== null ? (int) $c['mu_id'] : null,
            'municipio'          => $c['mu_nombre'],
            'institucionId'      => $c['ie_id'] !== null ? (int) $c['ie_id'] : null,
            'institucion'        => $c['ie_nombre'],
            'sedeId'             => $c['id_sede'] !== null ? (int) $c['id_sede'] : null,
            'sede'               => $c['se_nombre'],
            'clasificacionId'    => $c['id_clasificacion'] !== null ? (int) $c['id_clasificacion'] : null,
            'clasificacion'      => $c['cl_nombre'],
            'rutaActiva'         => (bool) $c['ruta_activa'],
            'nRadicado'          => $c['n_radicado'],
            'resolucionSeb'      => $c['resolucion_seb'],
            'resolucionRectoral' => $c['resolucion_rectoral'],
            'ubicacionAgresor'   => $c['ubicacion_agresor'],
            'fechaOcurrencia'    => $c['fecha_ocurrencia'],
            'lugarOcurrencia'    => $c['lugar_ocurrencia'],
            'disciplinario'      => $c['disciplinario'],
            'decisionComite'     => $c['decision_comite'],
            'penal'              => $c['penal'],
            'estado'             => $c['estado'],
            'version'            => (int) $c['version'],
            'creadoPor'          => $c['creado_por_nombre'],
            'creadoPorArea'      => $c['creado_por_area'],
            'fechaCreacion'      => $c['fecha_creacion'],
            'editadoPor'         => $c['editado_por_nombre'],
            'editadoPorArea'     => $c['editado_por_area'],
            'fechaUltimaEdicion' => $c['fecha_ultima_edicion'],
            'pasos'              => $pasos,
            'pasosEfectuados'    => $hechos,
        );
    }

    // ------------------------------------------------------------------ registro y edición

    public static function crear($sesion, $cuerpo)
    {
        $d = self::datosDe($cuerpo);
        $errores = Sci_Validator::validar($d, date('Y-m-d'));

        $pdo = Sci_Db::conexion();
        $sedeId = null;
        if (!empty($cuerpo['sedeId'])) {
            $stmt = $pdo->prepare('SELECT id_sede FROM sede WHERE id_sede = ?');
            $stmt->execute(array((int) $cuerpo['sedeId']));
            if ($stmt->fetch() === false) { $errores['sedeId'] = 'La sede indicada no existe.'; } else { $sedeId = (int) $cuerpo['sedeId']; }
        }
        $clasificacionId = null;
        if (!empty($cuerpo['clasificacionId'])) {
            $stmt = $pdo->prepare('SELECT id_clasificacion FROM clasificacion_siuce WHERE id_clasificacion = ?');
            $stmt->execute(array((int) $cuerpo['clasificacionId']));
            if ($stmt->fetch() === false) { $errores['clasificacionId'] = 'La clasificación indicada no existe.'; } else { $clasificacionId = (int) $cuerpo['clasificacionId']; }
        }
        if ($d['idCaso'] !== null && !isset($errores['idCaso']) && self::existeIdCaso($d['idCaso'])) {
            $errores['idCaso'] = 'Ya existe un caso con ese ID.';
        }
        if (count($errores) > 0) throw Sci_Api_Exception::validacion($errores);

        $rutaActiva = !empty($cuerpo['rutaActiva']);
        $permitirNombreDistinto = !empty($cuerpo['confirmarNombreDistinto']);

        $pdo->beginTransaction();
        try {
            $idCasoNuevo = self::persistirNuevo($sesion['id'], $d, $sedeId, $clasificacionId, $rutaActiva, $permitirNombreDistinto);
            AuditoriaService::registrar($sesion['id'], $idCasoNuevo, 'CREAR_CASO', null);
            $pdo->commit();
        } catch (Exception $ex) {
            $pdo->rollBack();
            throw $ex;
        }

        return self::detalle(self::buscar($idCasoNuevo));
    }

    private static function existeIdCaso($idCaso)
    {
        $pdo = Sci_Db::conexion();
        $stmt = $pdo->prepare('SELECT 1 FROM caso WHERE id_caso = ?');
        $stmt->execute(array($idCaso));
        return $stmt->fetch() !== false;
    }

    /**
     * Crea el caso, su presunto agresor (o reutiliza el existente) y los 12 pasos pendientes.
     * Sin auditoría (la pone el llamador) y SIN transacción propia — el llamador decide el alcance
     * transaccional (crear() envuelve esto; la importación masiva envolverá varias llamadas juntas).
     * @return int id del caso nuevo
     */
    public static function persistirNuevo($idCreador, $d, $sedeId, $clasificacionId, $rutaActiva, $permitirNombreDistinto)
    {
        $pdo = Sci_Db::conexion();
        // Sin cédula no se puede crear el presunto agresor (la tabla exige ambos datos): si se dio
        // un nombre, queda como borrador hasta que se complete la cédula editando el caso después.
        $idAgresor = $d['cedula'] === null ? null
            : self::resolverAgresor($d['presuntoAgresor'], $d['cedula'], $d['areaAsignatura'], $permitirNombreDistinto);

        $stmt = $pdo->prepare(
            'INSERT INTO caso (id_caso, id_agresor, nombre_agresor_borrador, id_sede, id_clasificacion,
                                ruta_activa, n_radicado, resolucion_seb, resolucion_rectoral, ubicacion_agresor,
                                fecha_ocurrencia, lugar_ocurrencia, disciplinario, decision_comite, penal,
                                estado, version, creado_por, fecha_creacion, editado_por, fecha_ultima_edicion)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'ACTIVO\', 0, ?, NOW(), ?, NOW())'
        );
        $stmt->execute(array(
            $d['idCaso'], $idAgresor, $idAgresor === null ? $d['presuntoAgresor'] : null,
            $sedeId, $clasificacionId, $rutaActiva ? 1 : 0,
            $d['nRadicado'], $d['resolucionSeb'], $d['resolucionRectoral'], $d['ubicacionAgresor'],
            $d['fechaOcurrencia'], $d['lugarOcurrencia'], $d['disciplinario'], $d['decisionComite'], $d['penal'],
            $idCreador, $idCreador,
        ));
        $idCaso = (int) $pdo->lastInsertId();

        $stmtVictima = $pdo->prepare('INSERT INTO caso_victima (id_caso, iniciales) VALUES (?, ?)');
        foreach ($d['victimas'] as $iniciales) {
            $stmtVictima->execute(array($idCaso, $iniciales));
        }

        $stmtPaso = $pdo->prepare('INSERT INTO caso_paso (id_caso, id_paso, efectuado) VALUES (?, ?, 0)');
        $pasos = $pdo->query('SELECT id_paso FROM paso_ruta ORDER BY id_paso ASC')->fetchAll();
        foreach ($pasos as $p) {
            $stmtPaso->execute(array($idCaso, (int) $p['id_paso']));
        }

        return $idCaso;
    }

    /**
     * Reutiliza al presunto agresor cuando la cédula ya existe. Si la cédula es nueva, el nombre
     * pasa a ser obligatorio (la tabla presunto_agresor exige ambos datos a la vez).
     * @return int id del presunto_agresor
     */
    public static function resolverAgresor($nombre, $cedula, $area, $permitirNombreDistinto)
    {
        $pdo = Sci_Db::conexion();
        $stmt = $pdo->prepare('SELECT id_agresor, nombre, area_asignatura FROM presunto_agresor WHERE cedula = ?');
        $stmt->execute(array($cedula));
        $existente = $stmt->fetch();

        if ($existente !== false) {
            if ($nombre !== null && !Sci_Validator::mismoNombre($existente['nombre'], $nombre) && !$permitirNombreDistinto) {
                throw Sci_Api_Exception::conflicto('NOMBRE_DISTINTO',
                    'Esa cédula ya está registrada a nombre de «' . $existente['nombre'] .
                    '». Verifique la cédula o confirme que se trata de la misma persona.');
            }
            if ($area !== null && $existente['area_asignatura'] === null) {
                $pdo->prepare('UPDATE presunto_agresor SET area_asignatura = ? WHERE id_agresor = ?')
                    ->execute(array($area, $existente['id_agresor']));
            }
            return (int) $existente['id_agresor'];
        }

        if ($nombre === null) {
            throw Sci_Api_Exception::validacion(array('presuntoAgresor' =>
                'Esta cédula no está registrada. Escriba el nombre del presunto agresor para registrarlo.'));
        }
        $pdo->prepare('INSERT INTO presunto_agresor (nombre, cedula, area_asignatura) VALUES (?, ?, ?)')
            ->execute(array($nombre, $cedula, $area));
        return (int) $pdo->lastInsertId();
    }

    /** Autocompletado del formulario: busca un presunto agresor ya registrado por cédula o por nombre. */
    public static function buscarAgresor($cedula, $nombre)
    {
        $pdo = Sci_Db::conexion();
        if ($cedula !== null) {
            $stmt = $pdo->prepare('SELECT nombre, cedula, area_asignatura FROM presunto_agresor WHERE cedula = ?');
            $stmt->execute(array($cedula));
        } elseif ($nombre !== null) {
            $stmt = $pdo->prepare('SELECT nombre, cedula, area_asignatura FROM presunto_agresor WHERE LOWER(nombre) = LOWER(?) LIMIT 1');
            $stmt->execute(array($nombre));
        } else {
            return null;
        }
        $fila = $stmt->fetch();
        if ($fila === false) return null;
        return array('nombre' => $fila['nombre'], 'cedula' => $fila['cedula'], 'areaAsignatura' => $fila['area_asignatura']);
    }

    public static function actualizar($sesion, $id, $cuerpo)
    {
        $c = self::buscarEditable($id);

        $versionEnviada = isset($cuerpo['version']) ? $cuerpo['version'] : null;
        if ($versionEnviada === null || (int) $versionEnviada !== (int) $c['version']) {
            throw Sci_Api_Exception::conflicto('CONFLICTO_VERSION',
                'Otra persona modificó este caso mientras usted lo editaba. Recargue la página y vuelva a intentarlo.');
        }

        $d = self::datosDe($cuerpo);
        $errores = Sci_Validator::validar($d, date('Y-m-d'));

        $pdo = Sci_Db::conexion();
        $sedeId = null;
        if (!empty($cuerpo['sedeId'])) {
            $stmt = $pdo->prepare('SELECT id_sede FROM sede WHERE id_sede = ?');
            $stmt->execute(array((int) $cuerpo['sedeId']));
            if ($stmt->fetch() === false) { $errores['sedeId'] = 'La sede indicada no existe.'; } else { $sedeId = (int) $cuerpo['sedeId']; }
        }
        $clasificacionId = null;
        if (!empty($cuerpo['clasificacionId'])) {
            $stmt = $pdo->prepare('SELECT id_clasificacion FROM clasificacion_siuce WHERE id_clasificacion = ?');
            $stmt->execute(array((int) $cuerpo['clasificacionId']));
            if ($stmt->fetch() === false) { $errores['clasificacionId'] = 'La clasificación indicada no existe.'; } else { $clasificacionId = (int) $cuerpo['clasificacionId']; }
        }
        if ($d['idCaso'] !== null && !isset($errores['idCaso']) && $d['idCaso'] !== $c['id_caso'] && self::existeIdCaso($d['idCaso'])) {
            $errores['idCaso'] = 'Ya existe un caso con ese ID.';
        }
        if (count($errores) > 0) throw Sci_Api_Exception::validacion($errores);

        $cambios = array();
        self::marcar($cambios, 'idCaso', $c['id_caso'], $d['idCaso']);

        // --- resolución del agresor: las 3 ramas, línea a línea como CasoService.actualizar ---
        $idAgresorFinal = $c['id_agresor'] !== null ? (int) $c['id_agresor'] : null;
        $borradorFinal = $c['nombre_agresor_borrador'];
        if ($d['cedula'] === null) {
            // sin cédula: no hay (ni puede haber) agresor resuelto; el nombre, si se dio, queda como borrador
            if ($idAgresorFinal !== null) { $idAgresorFinal = null; $cambios[] = 'presuntoAgresor'; }
            if ($borradorFinal !== $d['presuntoAgresor']) {
                if ($c['id_agresor'] === null) $cambios[] = 'presuntoAgresor';
                $borradorFinal = $d['presuntoAgresor'];
            }
        } elseif ($c['id_agresor'] !== null && $c['ag_cedula'] === $d['cedula']) {
            // misma persona: se corrige su nombre (si se dio) o área (el cambio vale para todos sus casos)
            if ($d['presuntoAgresor'] !== null && $c['ag_nombre'] !== $d['presuntoAgresor']) {
                $pdo->prepare('UPDATE presunto_agresor SET nombre = ? WHERE id_agresor = ?')->execute(array($d['presuntoAgresor'], $c['id_agresor']));
                $cambios[] = 'presuntoAgresor';
            }
            if ($c['ag_area'] !== $d['areaAsignatura']) {
                $pdo->prepare('UPDATE presunto_agresor SET area_asignatura = ? WHERE id_agresor = ?')->execute(array($d['areaAsignatura'], $c['id_agresor']));
                $cambios[] = 'areaAsignatura';
            }
        } else {
            // antes sin agresor (quizás con nombre en borrador), o cambia de cédula
            $permitirNombreDistinto = !empty($cuerpo['confirmarNombreDistinto']);
            $idAgresorFinal = self::resolverAgresor($d['presuntoAgresor'], $d['cedula'], $d['areaAsignatura'], $permitirNombreDistinto);
            $borradorFinal = null;
            $cambios[] = 'cedula';
        }

        self::marcar($cambios, 'sede', $c['id_sede'] !== null ? (int) $c['id_sede'] : null, $sedeId);
        self::marcar($cambios, 'clasificacion', $c['id_clasificacion'] !== null ? (int) $c['id_clasificacion'] : null, $clasificacionId);

        $victimasActuales = self::victimasDe($id);
        $victimasCambiaron = $victimasActuales !== $d['victimas'];
        if ($victimasCambiaron) $cambios[] = 'victimas';

        $rutaActivaNueva = !empty($cuerpo['rutaActiva']);
        self::marcar($cambios, 'rutaActiva', (bool) $c['ruta_activa'], $rutaActivaNueva);
        self::marcar($cambios, 'nRadicado', $c['n_radicado'], $d['nRadicado']);
        self::marcar($cambios, 'resolucionSeb', $c['resolucion_seb'], $d['resolucionSeb']);
        self::marcar($cambios, 'resolucionRectoral', $c['resolucion_rectoral'], $d['resolucionRectoral']);
        self::marcar($cambios, 'ubicacionAgresor', $c['ubicacion_agresor'], $d['ubicacionAgresor']);
        self::marcar($cambios, 'fechaOcurrencia', $c['fecha_ocurrencia'], $d['fechaOcurrencia']);
        self::marcar($cambios, 'lugarOcurrencia', $c['lugar_ocurrencia'], $d['lugarOcurrencia']);
        self::marcar($cambios, 'disciplinario', $c['disciplinario'], $d['disciplinario']);
        self::marcar($cambios, 'decisionComite', $c['decision_comite'], $d['decisionComite']);
        self::marcar($cambios, 'penal', $c['penal'], $d['penal']);

        $pdo->beginTransaction();
        try {
            if ($victimasCambiaron) {
                $pdo->prepare('DELETE FROM caso_victima WHERE id_caso = ?')->execute(array($id));
                $stmtV = $pdo->prepare('INSERT INTO caso_victima (id_caso, iniciales) VALUES (?, ?)');
                foreach ($d['victimas'] as $iniciales) $stmtV->execute(array($id, $iniciales));
            }

            // Capa B del bloqueo optimista: el UPDATE atómico es lo que realmente cierra la carrera,
            // no la comparación de versión de más arriba (que solo da un error rápido y amistoso).
            $stmt = $pdo->prepare(
                'UPDATE caso SET id_caso = ?, id_agresor = ?, nombre_agresor_borrador = ?, id_sede = ?,
                                 id_clasificacion = ?, ruta_activa = ?, n_radicado = ?, resolucion_seb = ?,
                                 resolucion_rectoral = ?, ubicacion_agresor = ?, fecha_ocurrencia = ?,
                                 lugar_ocurrencia = ?, disciplinario = ?, decision_comite = ?, penal = ?,
                                 editado_por = ?, fecha_ultima_edicion = NOW(), version = version + 1
                  WHERE id = ? AND version = ?'
            );
            $stmt->execute(array(
                $d['idCaso'], $idAgresorFinal, $idAgresorFinal === null ? $borradorFinal : null, $sedeId,
                $clasificacionId, $rutaActivaNueva ? 1 : 0, $d['nRadicado'], $d['resolucionSeb'],
                $d['resolucionRectoral'], $d['ubicacionAgresor'], $d['fechaOcurrencia'], $d['lugarOcurrencia'],
                $d['disciplinario'], $d['decisionComite'], $d['penal'],
                $sesion['id'], $id, (int) $c['version'],
            ));
            if ($stmt->rowCount() === 0) {
                $pdo->rollBack();
                throw Sci_Api_Exception::conflicto('CONFLICTO_VERSION',
                    'Otra persona modificó este caso mientras usted lo editaba. Recargue la página y vuelva a intentarlo.');
            }

            AuditoriaService::registrar($sesion['id'], (int) $id, 'EDITAR_CASO',
                count($cambios) === 0 ? 'sin cambios' : 'campos: ' . implode(', ', array_unique($cambios)));
            $pdo->commit();
        } catch (Exception $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $ex;
        }

        return self::detalle(self::buscar($id));
    }

    private static function marcar(&$cambios, $campo, $antes, $despues)
    {
        if ($antes !== $despues) $cambios[] = $campo;
    }

    // ------------------------------------------------------------------ ruta de atención

    public static function cambiarRuta($sesion, $id, $activa)
    {
        $c = self::buscarEditable($id);
        $activa = $activa ? 1 : 0;
        if ((int) $c['ruta_activa'] !== $activa) {
            $pdo = Sci_Db::conexion();
            $stmt = $pdo->prepare('UPDATE caso SET ruta_activa = ?, editado_por = ?, fecha_ultima_edicion = NOW(), version = version + 1 WHERE id = ? AND version = ?');
            $stmt->execute(array($activa, $sesion['id'], $id, (int) $c['version']));
            if ($stmt->rowCount() === 0) {
                throw Sci_Api_Exception::conflicto('CONFLICTO_VERSION', 'Otra persona modificó este caso. Recargue la página.');
            }
            AuditoriaService::registrar($sesion['id'], (int) $id, $activa ? 'ACTIVAR_RUTA' : 'DESACTIVAR_RUTA', null);
        }
        return self::detalle(self::buscar($id));
    }

    public static function actualizarPaso($sesion, $id, $numeroPaso, $cuerpo)
    {
        $c = self::buscarEditable($id);
        $pdo = Sci_Db::conexion();
        $stmt = $pdo->prepare('SELECT * FROM caso_paso WHERE id_caso = ? AND id_paso = ?');
        $stmt->execute(array((int) $id, (int) $numeroPaso));
        $cp = $stmt->fetch();
        if ($cp === false) throw Sci_Api_Exception::noEncontrado('El paso ' . $numeroPaso . ' no existe.');

        $accion = null;
        $sets = array();
        $params = array();

        if (array_key_exists('efectuado', $cuerpo) && $cuerpo['efectuado'] !== null) {
            $nuevoEfectuado = (bool) $cuerpo['efectuado'];
            if ($nuevoEfectuado !== (bool) $cp['efectuado']) {
                $sets[] = 'efectuado = ?'; $params[] = $nuevoEfectuado ? 1 : 0;
                $sets[] = 'id_usuario = ?'; $params[] = $nuevoEfectuado ? $sesion['id'] : null;
                $sets[] = 'fecha_efectuado = ?'; $params[] = $nuevoEfectuado ? date('Y-m-d H:i:s') : null;
                $accion = $nuevoEfectuado ? 'PASO_EFECTUADO' : 'PASO_PENDIENTE';
            }
        }

        if (array_key_exists('nota', $cuerpo) && $cuerpo['nota'] !== null) {
            $nota = Sci_Validator::limpiar($cuerpo['nota']);
            if ($nota !== null && mb_strlen($nota, 'UTF-8') > 1000) {
                throw Sci_Api_Exception::validacion(array('nota' => 'La nota no puede superar 1000 caracteres.'));
            }
            if ($nota !== $cp['nota']) {
                $sets[] = 'nota = ?'; $params[] = $nota;
                if ($accion === null) $accion = 'NOTA_PASO';
            }
        }

        if ($accion !== null) {
            $pdo->beginTransaction();
            try {
                $params[] = (int) $id; $params[] = (int) $numeroPaso;
                $pdo->prepare('UPDATE caso_paso SET ' . implode(', ', $sets) . ' WHERE id_caso = ? AND id_paso = ?')->execute($params);

                $stmtCaso = $pdo->prepare('UPDATE caso SET editado_por = ?, fecha_ultima_edicion = NOW(), version = version + 1 WHERE id = ? AND version = ?');
                $stmtCaso->execute(array($sesion['id'], $id, (int) $c['version']));
                if ($stmtCaso->rowCount() === 0) {
                    $pdo->rollBack();
                    throw Sci_Api_Exception::conflicto('CONFLICTO_VERSION', 'Otra persona modificó este caso. Recargue la página.');
                }
                AuditoriaService::registrar($sesion['id'], (int) $id, $accion, 'paso ' . $numeroPaso);
                $pdo->commit();
            } catch (Exception $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $ex;
            }
        }

        return self::detalle(self::buscar($id));
    }

    // ------------------------------------------------------------------ archivo

    public static function archivar($sesion, $id, $motivo)
    {
        return self::cambiarEstadoArchivo($sesion, $id, $motivo, 'ARCHIVADO', 'YA_ARCHIVADO', 'El caso ya está archivado.', 'ARCHIVAR_CASO');
    }

    public static function reactivar($sesion, $id, $motivo)
    {
        return self::cambiarEstadoArchivo($sesion, $id, $motivo, 'ACTIVO', 'YA_ACTIVO', 'El caso ya está activo.', 'REACTIVAR_CASO');
    }

    private static function cambiarEstadoArchivo($sesion, $id, $motivo, $estadoNuevo, $codigoSiYa, $mensajeSiYa, $accion)
    {
        $m = Sci_Validator::limpiar($motivo);
        if ($m === null || mb_strlen($m, 'UTF-8') < 5 || mb_strlen($m, 'UTF-8') > 300) {
            throw Sci_Api_Exception::validacion(array('motivo' => 'Escriba el motivo (de 5 a 300 caracteres).'));
        }
        // archivar/reactivar usan buscar() (no buscarEditable()): el chequeo de ya-archivado abajo
        // es el que corresponde, igual que en CasoService.java.
        $c = self::buscar($id);
        if ($c['estado'] === $estadoNuevo) {
            throw Sci_Api_Exception::conflicto($codigoSiYa, $mensajeSiYa);
        }

        $pdo = Sci_Db::conexion();
        $stmt = $pdo->prepare('UPDATE caso SET estado = ?, editado_por = ?, fecha_ultima_edicion = NOW(), version = version + 1 WHERE id = ? AND version = ?');
        $stmt->execute(array($estadoNuevo, $sesion['id'], $id, (int) $c['version']));
        if ($stmt->rowCount() === 0) {
            throw Sci_Api_Exception::conflicto('CONFLICTO_VERSION', 'Otra persona modificó este caso. Recargue la página.');
        }
        AuditoriaService::registrar($sesion['id'], (int) $id, $accion, $m);

        return self::detalle(self::buscar($id));
    }
}
