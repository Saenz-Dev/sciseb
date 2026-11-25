<?php
/**
 * Replica JwtFilter.java paso a paso: relee el usuario de la BD en cada request (nunca confía en
 * nada del token salvo el id), aplica las reglas de estado, y renueva el token en CADA respuesta
 * autenticada (vía la cabecera X-Token-Renovado) salvo el único caso CAMBIO_CLAVE.
 *
 * Se llama UNA vez al principio de api/index.php, antes de enrutar, para que la cabecera renovada
 * quede adjunta a la respuesta sin importar si después se despacha con éxito o se lanza una
 * excepción — los header() ya enviados sobreviven a cualquier salida posterior.
 */
class Sci_Auth
{
    /**
     * @return array('sesion'=>array|null, 'detener'=>bool)
     * 'detener'=true significa que ya se escribió una respuesta completa (403 CAMBIO_CLAVE) y
     * api/index.php debe terminar sin enrutar nada más.
     */
    public static function procesar(Sci_Request $request)
    {
        $token = $request->token();
        $idUsuario = $token !== null ? Sci_Jwt::leerId($token) : null;

        if ($idUsuario === null) {
            return array('sesion' => null, 'detener' => false);
        }

        $pdo = Sci_Db::conexion();
        $stmt = $pdo->prepare(
            'SELECT u.id_usuario, u.nombre, u.correo, u.estado, u.id_area, a.nombre AS area_nombre,
                    r.nombre AS rol_nombre
               FROM usuario u
               JOIN area a ON a.id_area = u.id_area
               JOIN rol r  ON r.id_rol  = u.id_rol
              WHERE u.id_usuario = ?'
        );
        $stmt->execute(array($idUsuario));
        $u = $stmt->fetch();

        if ($u === false) {
            return array('sesion' => null, 'detener' => false);
        }
        if ($u['estado'] !== 'ACTIVO' && $u['estado'] !== 'PENDIENTE_CAMBIO_CLAVE') {
            // BLOQUEADO / INACTIVO: se trata igual que no-autenticado (sin error explícito aquí).
            return array('sesion' => null, 'detener' => false);
        }

        if ($u['estado'] === 'PENDIENTE_CAMBIO_CLAVE' && strpos($request->ruta, '/auth/') !== 0) {
            // Único caso sin cabecera renovada: corta aquí mismo, antes de emitir X-Token-Renovado.
            Sci_Response::json(403, array(
                'codigo'  => 'CAMBIO_CLAVE',
                'mensaje' => 'Debe cambiar la clave temporal antes de continuar.',
                'errores' => null,
            ));
            return array('sesion' => null, 'detener' => true);
        }

        $sesion = array(
            'id'      => (int) $u['id_usuario'],
            'correo'  => $u['correo'],
            'nombre'  => $u['nombre'],
            'areaId'  => (int) $u['id_area'],
            'area'    => $u['area_nombre'],
            'rol'     => $u['rol_nombre'],
        );

        header('X-Token-Renovado: ' . Sci_Jwt::crear($sesion['id']));

        return array('sesion' => $sesion, 'detener' => false);
    }

    public static function esAdmin($sesion)
    {
        return $sesion !== null && $sesion['rol'] === 'ADMIN';
    }
}
