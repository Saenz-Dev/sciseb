<?php
/** @var Sci_Router $router */

$router->get('/casos/{id}/acciones', function (Sci_Request $req, $params, $sesion) {
    Sci_Response::json(200, AccionEnlaceService::listarAcciones($sesion, $params['id']));
}, 'AUTH');

$router->post('/casos/{id}/acciones', function (Sci_Request $req, $params, $sesion) {
    Sci_Response::json(201, AccionEnlaceService::crearAccion($sesion, $params['id'], $req->campo('texto')));
}, 'AUTH');

$router->put('/acciones/{idAccion}', function (Sci_Request $req, $params, $sesion) {
    Sci_Response::json(200, AccionEnlaceService::editarAccion($sesion, $params['idAccion'], $req->campo('texto')));
}, 'AUTH');

$router->get('/casos/{id}/enlaces', function (Sci_Request $req, $params, $sesion) {
    Sci_Response::json(200, AccionEnlaceService::listarEnlaces($sesion, $params['id']));
}, 'AUTH');

$router->post('/casos/{id}/enlaces', function (Sci_Request $req, $params, $sesion) {
    Sci_Response::json(201, AccionEnlaceService::crearEnlace($sesion, $params['id'], $req->cuerpo()));
}, 'AUTH');

$router->delete('/enlaces/{idEnlace}', function (Sci_Request $req, $params, $sesion) {
    Sci_Response::json(200, AccionEnlaceService::quitarEnlace($sesion, $params['idEnlace']));
}, 'AUTH');
