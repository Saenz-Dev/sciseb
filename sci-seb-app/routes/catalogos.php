<?php
/** @var Sci_Router $router */

$router->get('/catalogos', function (Sci_Request $req, $params, $sesion) {
    Sci_Response::json(200, CatalogoService::listar());
}, 'AUTH');

$router->get('/catalogos/municipios', function (Sci_Request $req, $params, $sesion) {
    Sci_Response::json(200, CatalogoService::municipios($req->query('provinciaId')));
}, 'AUTH');

$router->get('/catalogos/instituciones', function (Sci_Request $req, $params, $sesion) {
    Sci_Response::json(200, CatalogoService::instituciones($req->query('municipioId')));
}, 'AUTH');

$router->get('/catalogos/sedes', function (Sci_Request $req, $params, $sesion) {
    Sci_Response::json(200, CatalogoService::sedes($req->query('institucionId')));
}, 'AUTH');

$router->post('/catalogos/municipios', function (Sci_Request $req, $params, $sesion) {
    $r = CatalogoService::crearMunicipio($req->campo('nombre'), $req->campo('padreId'), $sesion['id']);
    Sci_Response::json(201, $r);
}, 'ADMIN');

$router->post('/catalogos/instituciones', function (Sci_Request $req, $params, $sesion) {
    $r = CatalogoService::crearInstitucion($req->campo('nombre'), $req->campo('padreId'), $sesion['id']);
    Sci_Response::json(201, $r);
}, 'ADMIN');

$router->post('/catalogos/sedes', function (Sci_Request $req, $params, $sesion) {
    $r = CatalogoService::crearSede($req->campo('nombre'), $req->campo('padreId'), $sesion['id']);
    Sci_Response::json(201, $r);
}, 'ADMIN');

$router->put('/catalogos/municipios/{id}', function (Sci_Request $req, $params, $sesion) {
    $r = CatalogoService::renombrarMunicipio($params['id'], $req->campo('nombre'), $sesion['id']);
    Sci_Response::json(200, $r);
}, 'ADMIN');

$router->put('/catalogos/instituciones/{id}', function (Sci_Request $req, $params, $sesion) {
    $r = CatalogoService::renombrarInstitucion($params['id'], $req->campo('nombre'), $sesion['id']);
    Sci_Response::json(200, $r);
}, 'ADMIN');

$router->put('/catalogos/sedes/{id}', function (Sci_Request $req, $params, $sesion) {
    $r = CatalogoService::renombrarSede($params['id'], $req->campo('nombre'), $sesion['id']);
    Sci_Response::json(200, $r);
}, 'ADMIN');

$router->put('/catalogos/areas/{id}', function (Sci_Request $req, $params, $sesion) {
    $r = CatalogoService::renombrarArea($params['id'], $req->campo('nombre'), $sesion['id']);
    Sci_Response::json(200, $r);
}, 'ADMIN');
