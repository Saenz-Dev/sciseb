<?php
/**
 * JWT HS256 hecho a mano (sin librería): header.payload.firma, cada parte en base64url.
 * Reemplaza io.jsonwebtoken (jjwt) de la app Java — mismas claims: sub (id de usuario), iat, exp.
 */
class Sci_Jwt
{
    private static function base64urlCodificar($bytes)
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private static function base64urlDecodificar($texto)
    {
        $relleno = strlen($texto) % 4;
        if ($relleno > 0) {
            $texto .= str_repeat('=', 4 - $relleno);
        }
        return base64_decode(strtr($texto, '-_', '+/'));
    }

    /** Crea un token nuevo para el usuario (sub = id), con la expiración configurada. */
    public static function crear($idUsuario)
    {
        $cfg = Sci_Config::get('jwt');
        $ahora = time();
        $encabezado = self::base64urlCodificar(json_encode(array('alg' => 'HS256', 'typ' => 'JWT')));
        $cuerpo = self::base64urlCodificar(json_encode(array(
            'sub' => (string) $idUsuario,
            'iat' => $ahora,
            'exp' => $ahora + ((int) $cfg['expiracion_minutos'] * 60),
        )));
        $entrada = $encabezado . '.' . $cuerpo;
        $firma = self::base64urlCodificar(hash_hmac('sha256', $entrada, $cfg['secreto'], true));
        return $entrada . '.' . $firma;
    }

    /**
     * Devuelve el id de usuario (entero) si el token es válido y no ha expirado, o null en
     * cualquier otro caso — mismo contrato "catch-all devuelve null" que JwtService.leerId.
     */
    public static function leerId($token)
    {
        if (!is_string($token) || $token === '') return null;
        $partes = explode('.', $token);
        if (count($partes) !== 3) return null;
        list($encabezado, $cuerpo, $firmaRecibida) = $partes;

        $cfg = Sci_Config::get('jwt');
        $firmaEsperada = self::base64urlCodificar(hash_hmac('sha256', $encabezado . '.' . $cuerpo, $cfg['secreto'], true));
        if (!Sci_Util::compararSeguro($firmaRecibida, $firmaEsperada)) return null;

        $datosCuerpo = json_decode(self::base64urlDecodificar($cuerpo), true);
        if (!is_array($datosCuerpo) || !isset($datosCuerpo['sub']) || !isset($datosCuerpo['exp'])) return null;
        if ((int) $datosCuerpo['exp'] <= time()) return null;
        if (!ctype_digit((string) $datosCuerpo['sub'])) return null;

        return (int) $datosCuerpo['sub'];
    }
}
