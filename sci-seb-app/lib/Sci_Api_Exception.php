<?php
/**
 * Puerto de config/ApiException.java: excepción con codigo+estado HTTP+errores de campo,
 * capturada en un único punto (api/index.php) para producir siempre {codigo,mensaje,errores}.
 */
class Sci_Api_Exception extends Exception
{
    private $estado;
    private $codigo;
    private $errores;

    public function __construct($estado, $codigo, $mensaje, $errores = null)
    {
        parent::__construct($mensaje);
        $this->estado = $estado;
        $this->codigo = $codigo;
        $this->errores = $errores;
    }

    public function getEstado() { return $this->estado; }
    public function getCodigo() { return $this->codigo; }
    public function getErrores() { return $this->errores; }

    public static function noEncontrado($mensaje = 'No se encontró el recurso solicitado.')
    {
        return new self(404, 'NO_ENCONTRADO', $mensaje);
    }

    public static function solicitudInvalida($mensaje)
    {
        return new self(400, 'SOLICITUD_INVALIDA', $mensaje);
    }

    public static function validacion($errores)
    {
        return new self(422, 'VALIDACION', 'Hay datos inválidos en la solicitud.', $errores);
    }

    public static function conflicto($codigo, $mensaje)
    {
        return new self(409, $codigo, $mensaje);
    }

    public static function prohibido($mensaje = 'No tiene permiso para realizar esta acción.')
    {
        return new self(403, 'PROHIBIDO', $mensaje);
    }
}
