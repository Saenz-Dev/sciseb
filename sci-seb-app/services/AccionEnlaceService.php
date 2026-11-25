<?php
/**
 * Puerto de AccionEnlaceService.java. Regla clave: el permiso de editar/retirar es por ÁREA del
 * autor original (cualquier usuario de esa área puede editar lo que registró otro de la misma
 * área), nunca por usuario individual. Los enlaces se borran siempre de forma lógica (activo=0).
 */
class AccionEnlaceService
{
    private static function casoActivo($idCaso)
    {
        $pdo = Sci_Db::conexion();
        $stmt = $pdo->prepare('SELECT id, estado FROM caso WHERE id = ?');
        $stmt->execute(array((int) $idCaso));
        $c = $stmt->fetch();
        if ($c === false) throw Sci_Api_Exception::noEncontrado('No existe el caso ' . $idCaso . '.');
        if ($c['estado'] === 'ARCHIVADO') {
            throw Sci_Api_Exception::conflicto('CASO_ARCHIVADO', 'El caso está archivado y no se puede modificar.');
        }
        return $c;
    }

    private static function casoEstado($idCaso)
    {
        $pdo = Sci_Db::conexion();
        $stmt = $pdo->prepare('SELECT estado FROM caso WHERE id = ?');
        $stmt->execute(array((int) $idCaso));
        $fila = $stmt->fetch();
        if ($fila === false) throw Sci_Api_Exception::noEncontrado('No existe el caso ' . $idCaso . '.');
        return $fila['estado'];
    }

    // ------------------------------------------------------------------ acciones

    public static function listarAcciones($sesion, $idCaso)
    {
        $abierto = self::casoEstado($idCaso) === 'ACTIVO';
        $pdo = Sci_Db::conexion();
        $stmt = $pdo->prepare(
            'SELECT ac.id_accion, ac.texto, ac.fecha_registro, ac.fecha_modificacion,
                    u.nombre AS usuario_nombre, a.id_area, a.nombre AS area_nombre
               FROM accion_caso ac
               JOIN usuario u ON ac.id_usuario = u.id_usuario
               JOIN area a ON u.id_area = a.id_area
              WHERE ac.id_caso = ?
              ORDER BY ac.fecha_registro ASC'
        );
        $stmt->execute(array((int) $idCaso));
        $resultado = array();
        foreach ($stmt->fetchAll() as $f) {
            $resultado[] = array(
                'id'                => (int) $f['id_accion'],
                'texto'             => $f['texto'],
                'usuario'           => $f['usuario_nombre'],
                'area'              => $f['area_nombre'],
                'areaId'            => (int) $f['id_area'],
                'fechaRegistro'     => $f['fecha_registro'],
                'fechaModificacion' => $f['fecha_modificacion'],
                'editable'          => $abierto && (int) $f['id_area'] === (int) $sesion['areaId'],
            );
        }
        return $resultado;
    }

    private static function textoValido($t)
    {
        $texto = $t === null ? null : trim((string) $t);
        if ($texto === null || $texto === '' || mb_strlen($texto, 'UTF-8') > 2000) {
            throw Sci_Api_Exception::validacion(array('texto' => 'Escriba la acción realizada (máximo 2000 caracteres).'));
        }
        return $texto;
    }

    public static function crearAccion($sesion, $idCaso, $texto)
    {
        $texto = self::textoValido($texto);
        self::casoActivo($idCaso);
        $pdo = Sci_Db::conexion();
        $pdo->prepare('INSERT INTO accion_caso (id_caso, id_usuario, texto, fecha_registro) VALUES (?, ?, ?, NOW())')
            ->execute(array((int) $idCaso, $sesion['id'], $texto));
        $idAccion = (int) $pdo->lastInsertId();
        AuditoriaService::registrar($sesion['id'], (int) $idCaso, 'REGISTRAR_ACCION', 'acción ' . $idAccion);
        return self::listarAcciones($sesion, $idCaso);
    }

    public static function editarAccion($sesion, $idAccion, $texto)
    {
        $texto = self::textoValido($texto);
        $pdo = Sci_Db::conexion();
        $stmt = $pdo->prepare(
            'SELECT ac.id_caso, u.id_area FROM accion_caso ac JOIN usuario u ON ac.id_usuario = u.id_usuario WHERE ac.id_accion = ?'
        );
        $stmt->execute(array((int) $idAccion));
        $a = $stmt->fetch();
        if ($a === false) throw Sci_Api_Exception::noEncontrado('No existe la acción.');
        $idCaso = (int) $a['id_caso'];
        self::casoActivo($idCaso);
        if ((int) $a['id_area'] !== (int) $sesion['areaId']) {
            throw Sci_Api_Exception::prohibido('Solo el área que registró la acción puede modificarla.');
        }
        $pdo->prepare('UPDATE accion_caso SET texto = ?, fecha_modificacion = NOW() WHERE id_accion = ?')
            ->execute(array($texto, (int) $idAccion));
        AuditoriaService::registrar($sesion['id'], $idCaso, 'EDITAR_ACCION', 'acción ' . $idAccion);
        return self::listarAcciones($sesion, $idCaso);
    }

    // ------------------------------------------------------------------ enlaces

    public static function listarEnlaces($sesion, $idCaso)
    {
        $abierto = self::casoEstado($idCaso) === 'ACTIVO';
        $pdo = Sci_Db::conexion();
        $stmt = $pdo->prepare(
            'SELECT e.id_enlace, e.titulo, e.url, e.fecha_registro, u.nombre AS usuario_nombre,
                    u.id_area, a.nombre AS area_nombre
               FROM enlace_evidencia e
               JOIN usuario u ON e.id_usuario = u.id_usuario
               JOIN area a ON u.id_area = a.id_area
              WHERE e.id_caso = ? AND e.activo = 1
              ORDER BY e.fecha_registro ASC'
        );
        $stmt->execute(array((int) $idCaso));
        $resultado = array();
        foreach ($stmt->fetchAll() as $f) {
            $resultado[] = array(
                'id'            => (int) $f['id_enlace'],
                'titulo'        => $f['titulo'],
                'url'           => $f['url'],
                'usuario'       => $f['usuario_nombre'],
                'area'          => $f['area_nombre'],
                'fechaRegistro' => $f['fecha_registro'],
                'eliminable'    => $abierto && (int) $f['id_area'] === (int) $sesion['areaId'],
            );
        }
        return $resultado;
    }

    /** Solo https, con servidor y sin usuario ni clave dentro de la dirección, sin espacios. */
    private static function urlValida($u)
    {
        $url = $u === null ? '' : trim((string) $u);
        $error = 'Escriba un enlace que empiece por https:// (por ejemplo, la carpeta compartida de la institución).';
        if ($url === '' || strlen($url) > 500 || preg_match('/\s/', $url)) {
            throw Sci_Api_Exception::validacion(array('url' => $error));
        }
        $partes = parse_url($url);
        if ($partes === false || !isset($partes['scheme']) || strcasecmp($partes['scheme'], 'https') !== 0
            || empty($partes['host']) || isset($partes['user'])) {
            throw Sci_Api_Exception::validacion(array('url' => $error));
        }
        return $url;
    }

    public static function crearEnlace($sesion, $idCaso, $cuerpo)
    {
        $titulo = Sci_Validator::limpiar(isset($cuerpo['titulo']) ? $cuerpo['titulo'] : null);
        if ($titulo === null || mb_strlen($titulo, 'UTF-8') > 150) {
            throw Sci_Api_Exception::validacion(array('titulo' => 'Escriba el nombre del documento (máximo 150 caracteres).'));
        }
        $url = self::urlValida(isset($cuerpo['url']) ? $cuerpo['url'] : null);
        self::casoActivo($idCaso);

        $pdo = Sci_Db::conexion();
        $confirmarDuplicado = !empty($cuerpo['confirmarDuplicado']);
        if (!$confirmarDuplicado) {
            $stmt = $pdo->prepare('SELECT 1 FROM enlace_evidencia WHERE id_caso = ? AND url = ? AND activo = 1');
            $stmt->execute(array((int) $idCaso, $url));
            if ($stmt->fetch() !== false) {
                throw Sci_Api_Exception::conflicto('ENLACE_DUPLICADO', 'Este enlace ya está registrado en el caso. ¿Desea agregarlo de nuevo?');
            }
        }

        $pdo->prepare('INSERT INTO enlace_evidencia (id_caso, id_usuario, titulo, url, activo, fecha_registro) VALUES (?, ?, ?, ?, 1, NOW())')
            ->execute(array((int) $idCaso, $sesion['id'], $titulo, $url));
        $idEnlace = (int) $pdo->lastInsertId();
        AuditoriaService::registrar($sesion['id'], (int) $idCaso, 'AGREGAR_ENLACE', 'enlace ' . $idEnlace);
        return self::listarEnlaces($sesion, $idCaso);
    }

    public static function quitarEnlace($sesion, $idEnlace)
    {
        $pdo = Sci_Db::conexion();
        $stmt = $pdo->prepare(
            'SELECT e.id_caso, u.id_area FROM enlace_evidencia e JOIN usuario u ON e.id_usuario = u.id_usuario WHERE e.id_enlace = ?'
        );
        $stmt->execute(array((int) $idEnlace));
        $e = $stmt->fetch();
        if ($e === false) throw Sci_Api_Exception::noEncontrado('No existe el enlace.');
        $idCaso = (int) $e['id_caso'];
        self::casoActivo($idCaso);
        if ((int) $e['id_area'] !== (int) $sesion['areaId']) {
            throw Sci_Api_Exception::prohibido('Solo el área que agregó el enlace puede retirarlo.');
        }
        $pdo->prepare('UPDATE enlace_evidencia SET activo = 0 WHERE id_enlace = ?')->execute(array((int) $idEnlace));
        AuditoriaService::registrar($sesion['id'], $idCaso, 'QUITAR_ENLACE', 'enlace ' . $idEnlace);
        return self::listarEnlaces($sesion, $idCaso);
    }
}
