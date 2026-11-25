<?php
/**
 * Toda salida JSON de la API pasa por aquí. Descarta cualquier buffer previo (espacios/avisos
 * colados por algún include) antes de escribir, para nunca corromper el JSON.
 */
class Sci_Response
{
    public static function json($estado, $datos)
    {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code($estado);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($datos);
    }

    public static function archivo($nombreArchivo, $contenido, $tipoMime = 'text/csv; charset=UTF-8')
    {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code(200);
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
        http_response_code(204);
    }
}
