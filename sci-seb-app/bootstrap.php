<?php
/**
 * Punto único de carga. PHP 5.3.3: sin namespaces ni autoloader (con ~30 archivos, requires
 * explícitos en orden de dependencia son más simples de auditar que resolver problemas de
 * autoload en filesystems case-sensitive o cachés de opcode viejas de un hosting cPanel antiguo).
 *
 * Importante: ningún archivo PHP de este proyecto debe terminar en "?>" — un espacio o salto de
 * línea después de ese cierre se cuela en la salida y corrompe el JSON de la respuesta.
 */

date_default_timezone_set('America/Bogota');
setlocale(LC_NUMERIC, 'C'); // evita que json_encode use "," como separador decimal según el locale
mb_internal_encoding('UTF-8');

error_reporting(E_ALL);
ini_set('display_errors', '0'); // los errores nunca deben imprimirse en la respuesta HTTP

$raiz = dirname(__FILE__);

// --- lib ---
require_once $raiz . '/lib/Sci_Config.php';
require_once $raiz . '/lib/Sci_Db.php';
require_once $raiz . '/lib/Sci_Api_Exception.php';
require_once $raiz . '/lib/Sci_Response.php';
require_once $raiz . '/lib/Sci_Request.php';
require_once $raiz . '/lib/Sci_Router.php';
require_once $raiz . '/lib/Sci_Util.php';
require_once $raiz . '/lib/Sci_Bcrypt.php';
require_once $raiz . '/lib/Sci_Jwt.php';
require_once $raiz . '/lib/Sci_Auth.php';
require_once $raiz . '/lib/Sci_Validator.php';
require_once $raiz . '/lib/Sci_CasoQuery.php';
require_once $raiz . '/lib/Sci_Csv.php';
require_once $raiz . '/lib/Sci_Zip.php';
require_once $raiz . '/lib/Sci_Xlsx.php';

Sci_Config::cargar($raiz . '/config/config.php');

// --- services ---
require_once $raiz . '/services/AuditoriaService.php';
require_once $raiz . '/services/AuthService.php';
require_once $raiz . '/services/UsuarioService.php';
require_once $raiz . '/services/CatalogoService.php';
require_once $raiz . '/services/CasoService.php';
require_once $raiz . '/services/AccionEnlaceService.php';
require_once $raiz . '/services/ImportacionService.php';
require_once $raiz . '/services/ExportacionService.php';

/**
 * Construye el router con todas las rutas registradas. Separado de la carga anterior para que
 * api/index.php pueda construirlo después de tener todo disponible.
 */
function sci_construir_router()
{
    $router = new Sci_Router();
    $raiz = dirname(__FILE__);
    require $raiz . '/routes/auth.php';
    require $raiz . '/routes/casos.php';
    require $raiz . '/routes/acciones_enlaces.php';
    require $raiz . '/routes/catalogos.php';
    require $raiz . '/routes/importacion.php';
    require $raiz . '/routes/usuarios.php';
    require $raiz . '/routes/auditoria.php';
    return $router;
}
