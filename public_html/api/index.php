<?php
/**
 * Front controller único de la API. Replica, en este orden, lo que antes hacían en conjunto
 * SecurityConfig + JwtFilter + ApiExceptionHandler de Spring:
 *   1) CORS / preflight OPTIONS
 *   2) autenticación (relee usuario, renueva token) — ver Sci_Auth::procesar
 *   3) enrutado
 *   4) un único try/catch que traduce cualquier excepción a {codigo,mensaje,errores}
 *   5) un manejador de fatales que nunca deja salir HTML/blanco, solo JSON 500 limpio
 *
 * Ningún archivo de este proyecto cierra con "?>" — ver nota en bootstrap.php.
 */

ob_start();

// dirname(__FILE__, $niveles) con segundo parámetro solo existe desde PHP 7 — PHP 5.3.3 no lo
// soporta, por eso se encadenan llamadas simples.
//
// La ubicación real de sci-seb-app/ cambia según el entorno (en desarrollo local es hermana de
// public_html/; en el hosting real de producción vive en private/sci-seb-app/, porque public_html/
// aquí se sube dentro de una subcarpeta de "web" — ver docs/MANUAL_INSTALACION_PHP.md). En vez de
// fijar un número de niveles, se prueban las ubicaciones candidatas y se usa la primera que exista.
$candidatosBootstrap = array(
    dirname(dirname(dirname(__FILE__))) . '/sci-seb-app/bootstrap.php',
    dirname(dirname(dirname(dirname(__FILE__)))) . '/private/sci-seb-app/bootstrap.php',
);
$rutaBootstrap = null;
foreach ($candidatosBootstrap as $candidato) {
    if (is_file($candidato)) { $rutaBootstrap = $candidato; break; }
}
if ($rutaBootstrap === null) {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(array('codigo' => 'ERROR_INTERNO', 'mensaje' => 'No se encontró sci-seb-app/bootstrap.php en ninguna ubicación esperada.', 'errores' => null));
    exit;
}
require $rutaBootstrap;

// Diagnóstico temporal: este host no deja ver dónde cae error_log(), así que además se escribe un
// archivo propio dentro de sci-seb-app/ (fuera del docroot, no accesible por web) para poder
// revisarlo por FTP. BORRAR esta función y sus llamadas una vez resuelto el problema que se esté
// depurando en este momento.
define('SCI_DIAG_LOG', dirname($rutaBootstrap) . '/diag.log');
function sci_diag($msg)
{
    @file_put_contents(SCI_DIAG_LOG, '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
}

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error === null) return;
    $fatales = array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR);
    if (!in_array($error['type'], $fatales, true)) return;
    error_log('SCI-SEB fatal: ' . $error['message'] . ' en ' . $error['file'] . ':' . $error['line']);
    sci_diag('FATAL: ' . $error['message'] . ' en ' . $error['file'] . ':' . $error['line']);
    if (!headers_sent()) {
        Sci_Response::json(500, array('codigo' => 'ERROR_INTERNO', 'mensaje' => 'Error interno.', 'errores' => null));
    }
});

function sci_cors()
{
    $origenes = Sci_Config::get('cors');
    $origenesPermitidos = $origenes['origenes'];
    $origen = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : null;

    if ($origen !== null && in_array($origen, $origenesPermitidos, true)) {
        header('Access-Control-Allow-Origin: ' . $origen);
        header('Vary: Origin');
        header('Access-Control-Expose-Headers: X-Token-Renovado, Content-Disposition');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type');
        header('Access-Control-Max-Age: 3600');
        // http_response_code() no existe hasta PHP 5.4 — se manda la línea de estado a mano.
        $protocolo = isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1';
        header($protocolo . ' 204 No Content', true, 204);
        exit;
    }
}

sci_cors();

try {
    $uri = $_SERVER['REQUEST_URI'];
    // No se asume que la API vive en "/api/..." desde la raíz del dominio — en despliegues reales
    // puede estar en una subcarpeta (p. ej. "/siuce/api/..."). SCRIPT_NAME siempre refleja dónde
    // está físicamente este index.php relativo al dominio, así que se usa eso para calcular el
    // prefijo exacto a quitar, sea cual sea la profundidad.
    $prefijoApi = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    if ($prefijoApi !== '/' && strpos($uri, $prefijoApi) === 0) {
        $uri = substr($uri, strlen($prefijoApi));
    }
    if ($uri === '' || $uri[0] !== '/') {
        $uri = '/' . $uri;
    }

    $request = new Sci_Request($_SERVER['REQUEST_METHOD'], $uri);

    $auth = Sci_Auth::procesar($request);
    if ($auth['detener']) {
        exit; // Sci_Auth ya escribió la respuesta 403 CAMBIO_CLAVE (sin X-Token-Renovado).
    }
    $sesion = $auth['sesion'];

    $router = sci_construir_router();
    $coincidencia = $router->encontrar($request->metodo, $request->ruta);

    if ($coincidencia === null) {
        throw Sci_Api_Exception::noEncontrado();
    }

    $ruta = $coincidencia['ruta'];
    if (($ruta['auth'] === 'AUTH' || $ruta['auth'] === 'ADMIN') && $sesion === null) {
        throw new Sci_Api_Exception(401, 'NO_AUTENTICADO', 'Debe iniciar sesión.');
    }
    if ($ruta['auth'] === 'ADMIN' && !Sci_Auth::esAdmin($sesion)) {
        throw Sci_Api_Exception::prohibido();
    }

    call_user_func($ruta['manejador'], $request, $coincidencia['params'], $sesion);

} catch (Sci_Api_Exception $e) {
    Sci_Response::json($e->getEstado(), array(
        'codigo'  => $e->getCodigo(),
        'mensaje' => $e->getMessage(),
        'errores' => $e->getErrores(),
    ));
} catch (PDOException $e) {
    $codigoSql = isset($e->errorInfo[1]) ? (int) $e->errorInfo[1] : 0;
    if ($codigoSql === 1062) {
        Sci_Response::json(409, array('codigo' => 'INTEGRIDAD', 'mensaje' => 'El registro ya existe.', 'errores' => null));
    } elseif ($codigoSql === 1451 || $codigoSql === 1452) {
        Sci_Response::json(409, array('codigo' => 'INTEGRIDAD', 'mensaje' => 'Hay datos relacionados que impiden la operación.', 'errores' => null));
    } else {
        error_log('SCI-SEB PDOException: ' . $e->getMessage());
        sci_diag('PDOException: ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());
        Sci_Response::json(500, array('codigo' => 'ERROR_INTERNO', 'mensaje' => 'Error interno.', 'errores' => null));
    }
} catch (Exception $e) {
    error_log('SCI-SEB Exception: ' . $e->getMessage());
    sci_diag('Exception: ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());
    Sci_Response::json(500, array('codigo' => 'ERROR_INTERNO', 'mensaje' => 'Error interno.', 'errores' => null));
}
