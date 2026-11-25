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
// soporta, por eso se encadenan llamadas simples. public_html/api/index.php -> subir 2 niveles
// hasta la carpeta que contiene tanto public_html/ como sci-seb-app/ (fuera del docroot).
require dirname(dirname(dirname(__FILE__))) . '/sci-seb-app/bootstrap.php';

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error === null) return;
    $fatales = array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR);
    if (!in_array($error['type'], $fatales, true)) return;
    error_log('SCI-SEB fatal: ' . $error['message'] . ' en ' . $error['file'] . ':' . $error['line']);
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
        http_response_code(204);
        exit;
    }
}

sci_cors();

try {
    $uri = $_SERVER['REQUEST_URI'];
    // El front controller vive en public_html/api/index.php; REQUEST_URI llega como "/api/...".
    if (strpos($uri, '/api') === 0) {
        $uri = substr($uri, 4);
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
        Sci_Response::json(500, array('codigo' => 'ERROR_INTERNO', 'mensaje' => 'Error interno.', 'errores' => null));
    }
} catch (Exception $e) {
    error_log('SCI-SEB Exception: ' . $e->getMessage());
    Sci_Response::json(500, array('codigo' => 'ERROR_INTERNO', 'mensaje' => 'Error interno.', 'errores' => null));
}
