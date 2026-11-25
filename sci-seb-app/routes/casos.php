<?php
/** @var Sci_Router $router */

$router->get('/indicadores', function (Sci_Request $req, $params, $sesion) {
    Sci_Response::json(200, CasoService::indicadores());
}, 'AUTH');

$router->get('/casos', function (Sci_Request $req, $params, $sesion) {
    $filtro = array(
        'q'               => $req->query('q'),
        'provinciaId'     => $req->query('provinciaId'),
        'clasificacionId' => $req->query('clasificacionId'),
        'ruta'            => $req->query('ruta'),
        'estado'          => $req->query('estado'),
        'borrador'        => $req->query('borrador') === 'true',
    );
    $r = CasoService::listar($filtro, $req->query('pagina', 0), $req->query('tamano', 20));
    Sci_Response::json(200, $r);
}, 'AUTH');

$router->get('/casos/exportar', function (Sci_Request $req, $params, $sesion) {
    $filtro = array(
        'q'               => $req->query('q'),
        'provinciaId'     => $req->query('provinciaId'),
        'clasificacionId' => $req->query('clasificacionId'),
        'ruta'            => $req->query('ruta'),
        'estado'          => $req->query('estado'),
        'borrador'        => $req->query('borrador') === 'true',
    );
    $a = ExportacionService::exportar($sesion, $filtro, $req->query('formato', 'csv'));
    Sci_Response::archivo($a['nombre'], $a['contenido'], $a['tipo']);
}, 'AUTH');

$router->get('/casos/{id}', function (Sci_Request $req, $params, $sesion) {
    Sci_Response::json(200, CasoService::obtener($params['id']));
}, 'AUTH');

$router->get('/agresores/buscar', function (Sci_Request $req, $params, $sesion) {
    $cedula = Sci_Validator::limpiar($req->query('cedula'));
    $nombre = Sci_Validator::limpiar($req->query('nombre'));
    Sci_Response::json(200, CasoService::buscarAgresor($cedula, $nombre));
}, 'AUTH');

$router->post('/casos', function (Sci_Request $req, $params, $sesion) {
    Sci_Response::json(201, CasoService::crear($sesion, $req->cuerpo()));
}, 'AUTH');

$router->put('/casos/{id}', function (Sci_Request $req, $params, $sesion) {
    Sci_Response::json(200, CasoService::actualizar($sesion, $params['id'], $req->cuerpo()));
}, 'AUTH');

$router->post('/casos/{id}/archivar', function (Sci_Request $req, $params, $sesion) {
    Sci_Response::json(200, CasoService::archivar($sesion, $params['id'], $req->campo('motivo')));
}, 'ADMIN');

$router->post('/casos/{id}/reactivar', function (Sci_Request $req, $params, $sesion) {
    Sci_Response::json(200, CasoService::reactivar($sesion, $params['id'], $req->campo('motivo')));
}, 'ADMIN');

$router->put('/casos/{id}/ruta', function (Sci_Request $req, $params, $sesion) {
    Sci_Response::json(200, CasoService::cambiarRuta($sesion, $params['id'], !empty($req->campo('activa'))));
}, 'AUTH');

$router->put('/casos/{id}/pasos/{numero}', function (Sci_Request $req, $params, $sesion) {
    Sci_Response::json(200, CasoService::actualizarPaso($sesion, $params['id'], $params['numero'], $req->cuerpo()));
}, 'AUTH');
