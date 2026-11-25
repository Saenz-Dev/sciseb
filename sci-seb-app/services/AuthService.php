<?php
/**
 * Puerto de AuthService.java. El contador de intentos fallidos debe escribirse SIEMPRE, incluso
 * cuando después se lanza la excepción 401/423 — por eso ese UPDATE se ejecuta solo (autocommit),
 * nunca dentro de una transacción que una excepción pudiera revertir (ver nota de la fase 4 del
 * plan sobre AuthService::login).
 */
class AuthService
{
    const MAX_INTENTOS = 5;

    public static function login($correo, $clave)
    {
        $pdo = Sci_Db::conexion();
        $stmt = $pdo->prepare(
            "SELECT u.*, r.nombre AS rol_nombre, a.nombre AS area_nombre
               FROM usuario u
               JOIN rol r  ON r.id_rol  = u.id_rol
               JOIN area a ON a.id_area = u.id_area
              WHERE LOWER(u.correo) = LOWER(?)"
        );
        $stmt->execute(array(trim($correo)));
        $u = $stmt->fetch();

        if ($u === false || !Sci_Bcrypt::verificar($clave, $u['clave_hash'])) {
            if ($u !== false && $u['estado'] !== 'BLOQUEADO' && $u['estado'] !== 'INACTIVO') {
                $quedoBloqueada = self::registrarIntentoFallido($u);
                if ($quedoBloqueada) {
                    throw new Sci_Api_Exception(423, 'CUENTA_BLOQUEADA',
                        'Esta cuenta está bloqueada por demasiados intentos fallidos. Un administrador debe restablecer la clave.');
                }
            }
            throw new Sci_Api_Exception(401, 'CREDENCIALES', 'Correo o clave incorrectos.');
        }

        if ($u['estado'] === 'BLOQUEADO') {
            throw new Sci_Api_Exception(423, 'CUENTA_BLOQUEADA',
                'Esta cuenta está bloqueada por demasiados intentos fallidos. Un administrador debe restablecer la clave.');
        }
        if ($u['estado'] === 'INACTIVO') {
            throw new Sci_Api_Exception(403, 'CUENTA_INACTIVA', 'Esta cuenta está inactiva.');
        }

        $pdo->prepare('UPDATE usuario SET intentos_fallidos = 0, ultimo_acceso = NOW() WHERE id_usuario = ?')
            ->execute(array($u['id_usuario']));
        AuditoriaService::registrar($u['id_usuario'], null, 'INICIO_SESION', null);

        $token = Sci_Jwt::crear($u['id_usuario']);
        $u['ultimo_acceso'] = date('Y-m-d H:i:s');
        $u['intentos_fallidos'] = 0;

        return array(
            'token'           => $token,
            'usuario'         => self::dto($u),
            'debeCambiarClave' => $u['estado'] === 'PENDIENTE_CAMBIO_CLAVE',
        );
    }

    /** @return bool true si este intento dejó la cuenta BLOQUEADA (se debe cortar con 423 ya mismo). */
    private static function registrarIntentoFallido($u)
    {
        $pdo = Sci_Db::conexion();
        $intentos = (int) $u['intentos_fallidos'] + 1;
        if ($intentos >= self::MAX_INTENTOS) {
            $pdo->prepare('UPDATE usuario SET intentos_fallidos = ?, estado = ? WHERE id_usuario = ?')
                ->execute(array($intentos, 'BLOQUEADO', $u['id_usuario']));
            AuditoriaService::registrar($u['id_usuario'], null, 'CUENTA_BLOQUEADA', null);
            return true;
        }
        $pdo->prepare('UPDATE usuario SET intentos_fallidos = ? WHERE id_usuario = ?')
            ->execute(array($intentos, $u['id_usuario']));
        return false;
    }

    public static function yo($sesion)
    {
        $pdo = Sci_Db::conexion();
        $stmt = $pdo->prepare(
            "SELECT u.*, r.nombre AS rol_nombre, a.nombre AS area_nombre
               FROM usuario u
               JOIN rol r  ON r.id_rol  = u.id_rol
               JOIN area a ON a.id_area = u.id_area
              WHERE u.id_usuario = ?"
        );
        $stmt->execute(array($sesion['id']));
        $u = $stmt->fetch();
        if ($u === false) throw Sci_Api_Exception::noEncontrado();
        return self::dto($u);
    }

    public static function cambiarClave($sesion, $claveActual, $claveNueva)
    {
        $pdo = Sci_Db::conexion();
        $stmt = $pdo->prepare('SELECT * FROM usuario WHERE id_usuario = ?');
        $stmt->execute(array($sesion['id']));
        $u = $stmt->fetch();
        if ($u === false) throw Sci_Api_Exception::noEncontrado();

        if (!Sci_Bcrypt::verificar((string) $claveActual, $u['clave_hash'])) {
            throw Sci_Api_Exception::solicitudInvalida('La clave actual no es correcta.');
        }
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9]).{10,72}$/', (string) $claveNueva)) {
            throw Sci_Api_Exception::validacion(array(
                'claveNueva' => 'Debe tener entre 10 y 72 caracteres, con mayúscula, minúscula y número.',
            ));
        }
        if (Sci_Bcrypt::verificar((string) $claveNueva, $u['clave_hash'])) {
            throw Sci_Api_Exception::solicitudInvalida('La clave nueva no puede ser igual a la actual.');
        }

        $nuevoHash = Sci_Bcrypt::hash($claveNueva, 10);
        $pdo->prepare('UPDATE usuario SET clave_hash = ?, estado = ? WHERE id_usuario = ?')
            ->execute(array($nuevoHash, 'ACTIVO', $u['id_usuario']));
        AuditoriaService::registrar($u['id_usuario'], null, 'CAMBIO_CLAVE', null);

        return self::yo($sesion);
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
}
