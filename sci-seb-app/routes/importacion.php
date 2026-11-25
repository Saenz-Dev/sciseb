<?php
/** @var Sci_Router $router */

$router->get('/importaciones/plantilla', function (Sci_Request $req, $params, $sesion) {
    Sci_Response::archivo('plantilla_casos.csv', ImportacionService::plantillaCsv(), 'text/csv; charset=UTF-8');
}, 'AUTH');

$router->post('/importaciones/vista-previa', function (Sci_Request $req, $params, $sesion) {
    if (!isset($_FILES['archivo']) || !is_uploaded_file($_FILES['archivo']['tmp_name'])) {
        throw Sci_Api_Exception::solicitudInvalida('Debe adjuntar un archivo.');
    }
    $archivo = $_FILES['archivo'];
    if ($archivo['error'] === UPLOAD_ERR_INI_SIZE || $archivo['error'] === UPLOAD_ERR_FORM_SIZE) {
        throw new Sci_Api_Exception(413, 'ARCHIVO_GRANDE', 'El archivo supera el tamaño máximo permitido.');
    }
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        throw Sci_Api_Exception::solicitudInvalida('No se pudo leer el archivo.');
    }
    $contenido = file_get_contents($archivo['tmp_name']);
    $r = ImportacionService::vistaPrevia($sesion, $archivo['name'], $contenido);
    Sci_Response::json(200, $r);
}, 'AUTH');

$router->post('/importaciones/confirmar', function (Sci_Request $req, $params, $sesion) {
    $r = ImportacionService::confirmar($sesion, $req->campo('token'));
    Sci_Response::json(201, $r);
}, 'AUTH');

$router->delete('/importaciones/vista-previa/{token}', function (Sci_Request $req, $params, $sesion) {
    ImportacionService::cancelar($sesion, $params['token']);
    Sci_Response::sinContenido();
}, 'AUTH');
