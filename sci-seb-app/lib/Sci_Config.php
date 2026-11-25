<?php
class Sci_Config
{
    private static $datos = null;

    public static function cargar($archivo)
    {
        self::$datos = require $archivo;
    }

    public static function get($clave, $porDefecto = null)
    {
        return isset(self::$datos[$clave]) ? self::$datos[$clave] : $porDefecto;
    }
}
