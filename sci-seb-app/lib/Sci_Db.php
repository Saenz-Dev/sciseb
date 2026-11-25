<?php
/**
 * Conexión PDO única por request. PDO (no mysqli) porque Sci_CasoQuery construye una lista
 * variable de parámetros con ?-placeholders, lo que con mysqli obligaría a mantener a mano una
 * cadena de tipos ("ssii") en paralelo al array de valores — fuente fácil de errores justo en el
 * código de filtros dinámicos.
 */
class Sci_Db
{
    private static $pdo = null;

    public static function conexion()
    {
        if (self::$pdo === null) {
            $cfg = Sci_Config::get('db');
            self::$pdo = new PDO($cfg['dsn'], $cfg['usuario'], $cfg['clave'], array(
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES   => true,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ));
        }
        return self::$pdo;
    }
}
