<?php
/**
 * Toda salida JSON de la API pasa por aquí. Descarta cualquier buffer previo (espacios/avisos
 * colados por algún include) antes de escribir, para nunca corromper el JSON.
 */
class Sci_Response
{
    // http_response_code() no existe hasta PHP 5.4 — se manda la línea de estado a mano.
    private static $textos = array(
        200 => 'OK', 201 => 'Created', 204 => 'No Content',
        400 => 'Bad Request', 401 => 'Unauthorized', 403 => 'Forbidden', 404 => 'Not Found',
        405 => 'Method Not Allowed', 409 => 'Conflict', 410 => 'Gone', 413 => 'Payload Too Large',
        422 => 'Unprocessable Entity', 423 => 'Locked', 500 => 'Internal Server Error',
    );

    private static function estadoHttp($codigo)
    {
        $texto = isset(self::$textos[$codigo]) ? self::$textos[$codigo] : '';
        $protocolo = isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1';
        header($protocolo . ' ' . $codigo . ' ' . $texto, true, $codigo);
    }

    public static function json($estado, $datos)
    {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        self::estadoHttp($estado);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($datos);
    }

    public static function archivo($nombreArchivo, $contenido, $tipoMime = 'text/csv; charset=UTF-8')
    {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        self::estadoHttp(200);
        header('Content-Type: ' . $tipoMime);
        header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"; filename*=UTF-8\'\'' . rawurlencode($nombreArchivo));
        header('Content-Length: ' . strlen($contenido));
        echo $contenido;
    }

    public static function sinContenido()
    {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        self::estadoHttp(204);
    }
}
