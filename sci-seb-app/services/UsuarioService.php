<?php
/**
 * Puerto de UsuarioService.java. Todas las rutas son ADMIN-only (se aplica a nivel de ruta, no
 * aquí). Los alfabetos de la clave temporal excluyen caracteres ambiguos (I, O, l, 0, 1) para que
 * se puedan dictar o transcribir sin confusión.
 */
class UsuarioService
{
    const MAYUS = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    const MINUS = 'abcdefghijkmnpqrstuvwxyz';
    const DIGITOS = '23456789';
    const PATRON_CORREO = '/^[^@\s]+@[^@\s]+\.[^@\s]+$/';

    public static function generarClaveTemporal()
    {
        // PHP 5.3 no admite indexar una constante de clase directamente con "[]" (self::MAYUS[0]
        // es un error de sintaxis ahí) — hay que guardarla en una variable primero.
        $mayus = self::MAYUS;
        $minus = self::MINUS;
        $digitos = self::DIGITOS;
        $todos = $mayus . $minus . $digitos;
        $sb = $mayus[self::indiceAleatorio(strlen($mayus))]
            . $minus[self::indiceAleatorio(strlen($minus))]
            . $digitos[self::indiceAleatorio(strlen($digitos))];
        while (strlen($sb) < 12) {
            $sb .= $todos[self::indiceAleatorio(strlen($todos))];
        }
        return $sb;
    }

    private static function indiceAleatorio($max)
    {
        return function_exists('random_int') ? random_int(0, $max - 1) : mt_rand(0, $max - 1);
    }

    private static function dto($u)
    {
        return array(
            'id'           => (int) $u['id_usuario'],
            'nombre'       => $u['nombre'],
            'correo'       => $u['correo'],
            'areaId'       => (int) $u['id_area'],
            'area'         => $u['area_nombre'],
            'rol'          => $u['rol_nombre'],
            'estado'       => $u['estado'],
            'ultimoAcceso' => $u['ultimo_acceso'],
        );
    }

    private static function cargar($id)
    {
        $pdo = Sci_Db::conexion();
        $stmt = $pdo->prepare(
            "SELECT u.*, a.nombre AS area_nombre, r.nombre AS rol_nombre
               FROM usuario u JOIN area a ON a.id_area = u.id_area JOIN rol r ON r.id_rol = u.id_rol
              WHERE u.id_usuario = ?"
        );
        $stmt->execute(array((int) $id));
        $u = $stmt->fetch();
        if ($u === false) throw Sci_Api_Exception::noEncontrado('Usuario no encontrado.');
        return $u;
    }

    public static function listar()
    {
        $pdo = Sci_Db::conexion();
        $stmt = $pdo->query(
            "SELECT u.*, a.nombre AS area_nombre, r.nombre AS rol_nombre
               FROM usuario u JOIN area a ON a.id_area = u.id_area JOIN rol r ON r.id_rol = u.id_rol
              ORDER BY u.nombre ASC"
        );
        $resultado = array();
        foreach ($stmt->fetchAll() as $u) $resultado[] = self::dto($u);
        return $resultado;
    }

    public static function crear($admin, $cuerpo)
    {
        $errores = array();
        $nombre = isset($cuerpo['nombre']) ? trim((string) $cuerpo['nombre']) : '';
        $correo = isset($cuerpo['correo']) ? mb_strtolower(trim((string) $cuerpo['correo']), 'UTF-8') : '';

        if (mb_strlen($nombre, 'UTF-8') < 3 || mb_strlen($nombre, 'UTF-8') > 120) {
            $errores['nombre'] = 'Escriba el nombre completo (3 a 120 caracteres).';
        }

        $pdo = Sci_Db::conexion();
        if (!preg_match(self::PATRON_CORREO, $correo) || strlen($correo) > 120) {
            $errores['correo'] = 'Escriba un correo válido.';
        } else {
            $stmt = $pdo->prepare('SELECT 1 FROM usuario WHERE LOWER(correo) = LOWER(?)');
            $stmt->execute(array($correo));
            if ($stmt->fetch() !== false) $errores['correo'] = 'Ya existe un usuario con ese correo.';
        }

        $areaId = isset($cuerpo['areaId']) ? (int) $cuerpo['areaId'] : null;
        if ($areaId !== null) {
            $stmt = $pdo->prepare('SELECT 1 FROM area WHERE id_area = ?');
            $stmt->execute(array($areaId));
            if ($stmt->fetch() === false) $areaId = null;
        }
        if ($areaId === null) $errores['areaId'] = 'Seleccione el área.';

        $rolNombre = isset($cuerpo['rol']) ? $cuerpo['rol'] : null;
        $rolId = null;
        if ($rolNombre === 'ADMIN' || $rolNombre === 'USUARIO') {
            $stmt = $pdo->prepare('SELECT id_rol FROM rol WHERE nombre = ?');
            $stmt->execute(array($rolNombre));
            $fila = $stmt->fetch();
            $rolId = $fila === false ? null : (int) $fila['id_rol'];
        }
        if ($rolId === null) $errores['rol'] = 'Seleccione el rol (ADMIN o USUARIO).';

        if (count($errores) > 0) throw Sci_Api_Exception::validacion($errores);

        $temporal = self::generarClaveTemporal();
        $hash = Sci_Bcrypt::hash($temporal, 10);
        $pdo->prepare(
            'INSERT INTO usuario (nombre, correo, clave_hash, id_area, id_rol, estado, intentos_fallidos)
             VALUES (?, ?, ?, ?, ?, \'PENDIENTE_CAMBIO_CLAVE\', 0)'
        )->execute(array($nombre, $correo, $hash, $areaId, $rolId));
        $id = (int) $pdo->lastInsertId();

        AuditoriaService::registrar($admin['id'], null, 'CREAR_USUARIO', 'usuario ' . $id . ', rol ' . $rolNombre);
        return array('usuario' => self::dto(self::cargar($id)), 'claveTemporal' => $temporal);
    }

    public static function cambiarEstado($admin, $id, $estado)
    {
        $u = self::cargar($id);
        if ($estado !== 'ACTIVO' && $estado !== 'INACTIVO') {
            throw Sci_Api_Exception::solicitudInvalida('El estado debe ser ACTIVO o INACTIVO.');
        }
        if ((int) $u['id_usuario'] === (int) $admin['id']) {
            throw Sci_Api_Exception::conflicto('AUTO_INHABILITAR', 'No puede cambiar el estado de su propia cuenta.');
        }
        $pdo = Sci_Db::conexion();
        if ($estado === 'INACTIVO') {
            $pdo->prepare('UPDATE usuario SET estado = \'INACTIVO\' WHERE id_usuario = ?')->execute(array((int) $id));
        } else {
            // Conserva su clave actual pero exige cambiarla en el siguiente ingreso.
            $pdo->prepare('UPDATE usuario SET estado = \'PENDIENTE_CAMBIO_CLAVE\', intentos_fallidos = 0 WHERE id_usuario = ?')
                ->execute(array((int) $id));
        }
        AuditoriaService::registrar($admin['id'], null, $estado === 'INACTIVO' ? 'INHABILITAR_USUARIO' : 'REACTIVAR_USUARIO', 'usuario ' . $id);
        return self::dto(self::cargar($id));
    }

    public static function restablecerClave($admin, $id)
    {
        $u = self::cargar($id);
        if ($u['estado'] === 'INACTIVO') {
            throw Sci_Api_Exception::conflicto('CUENTA_INACTIVA', 'La cuenta está inhabilitada; reactívela primero.');
        }
        $temporal = self::generarClaveTemporal();
        $hash = Sci_Bcrypt::hash($temporal, 10);
        $pdo = Sci_Db::conexion();
        $pdo->prepare('UPDATE usuario SET clave_hash = ?, intentos_fallidos = 0, estado = \'PENDIENTE_CAMBIO_CLAVE\' WHERE id_usuario = ?')
            ->execute(array($hash, (int) $id));
        AuditoriaService::registrar($admin['id'], null, 'RESTABLECER_CLAVE', 'usuario ' . $id);
        return array('usuario' => self::dto(self::cargar($id)), 'claveTemporal' => $temporal);
    }
}
