<?php
/**
 * BCrypt en PHP 5.3 puro usando la crypt() nativa (CRYPT_BLOWFISH), sin password_hash() ni
 * password_verify() (llegaron en PHP 5.5). Genera y verifica hashes "$2a$" — el mismo formato que
 * ya produce BCryptPasswordEncoder de Spring Security, así que los hashes existentes en la BD
 * siguen siendo válidos sin ninguna migración de datos.
 *
 * La codificación de la sal (encode64) es el algoritmo radix-64 estándar de crypt_blowfish
 * (alfabeto "./A-Za-z0-9", distinto del base64 normal) — el mismo que usan phpass, Crypt::Eksblowfish
 * de Perl y la mayoría de wrappers de bcrypt; está documentado públicamente, no es secreto.
 */
class Sci_Bcrypt
{
    const ITOA64 = './ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

    public static function hash($password, $costo = 10)
    {
        if (!defined('CRYPT_BLOWFISH') || CRYPT_BLOWFISH !== 1) {
            throw new RuntimeException('Este servidor PHP no tiene soporte CRYPT_BLOWFISH.');
        }
        $sal = self::generarSal($costo);
        $hash = crypt($password, $sal);
        if (!is_string($hash) || strlen($hash) !== 60) {
            throw new RuntimeException('No se pudo generar el hash de la contraseña.');
        }
        return $hash;
    }

    public static function verificar($password, $hashGuardado)
    {
        if (!is_string($hashGuardado) || $hashGuardado === '') {
            return false;
        }
        $calculado = crypt($password, $hashGuardado);
        return Sci_Util::compararSeguro($calculado, $hashGuardado);
    }

    private static function generarSal($costo)
    {
        $bytesAleatorios = self::bytesAleatorios(16);
        $costo = max(4, min(31, (int) $costo));
        return '$2a$' . sprintf('%02d', $costo) . '$' . self::codificar64($bytesAleatorios, 16);
    }

    private static function bytesAleatorios($cantidad)
    {
        if (function_exists('random_bytes')) {
            return random_bytes($cantidad);
        }
        if (function_exists('openssl_random_pseudo_bytes')) {
            return openssl_random_pseudo_bytes($cantidad);
        }
        $bytes = '';
        for ($i = 0; $i < $cantidad; $i++) {
            $bytes .= chr(mt_rand(0, 255));
        }
        return $bytes;
    }

    /** Codificación radix-64 de crypt_blowfish (no es base64 estándar). */
    private static function codificar64($entrada, $cantidad)
    {
        $itoa64 = self::ITOA64;
        $salida = '';
        $i = 0;
        while (true) {
            $c1 = ord($entrada[$i++]);
            $salida .= $itoa64[$c1 >> 2];
            $c1 = ($c1 & 0x03) << 4;
            if ($i >= $cantidad) {
                $salida .= $itoa64[$c1];
                break;
            }
            $c2 = ord($entrada[$i++]);
            $c1 |= $c2 >> 4;
            $salida .= $itoa64[$c1];
            $c1 = ($c2 & 0x0f) << 2;
            if ($i >= $cantidad) {
                $salida .= $itoa64[$c1];
                break;
            }
            $c2 = ord($entrada[$i++]);
            $c1 |= $c2 >> 6;
            $salida .= $itoa64[$c1];
            $salida .= $itoa64[$c2 & 0x3f];
        }
        return $salida;
    }
}
