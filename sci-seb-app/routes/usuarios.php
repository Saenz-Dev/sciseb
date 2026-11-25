<?php
/** @var Sci_Router $router */
// Todas ADMIN-only, igual que @PreAuthorize("hasRole('ADMIN')") a nivel de clase en Java.

$router->get('/usuarios', function (Sci_Request $req, $params, $sesion) {
    Sci_Response::json(200, UsuarioService::listar());
}, 'ADMIN');

$router->post('/usuarios', function (Sci_Request $req, $params, $sesion) {
    Sci_Response::json(201, UsuarioService::crear($sesion, $req->cuerpo()));
}, 'ADMIN');

$router->patch('/usuarios/{id}/estado', function (Sci_Request $req, $params, $sesion) {
    Sci_Response::json(200, UsuarioService::cambiarEstado($sesion, $params['id'], $req->campo('estado')));
}, 'ADMIN');

$router->post('/usuarios/{id}/restablecer-clave', function (Sci_Request $req, $params, $sesion) {
    Sci_Response::json(200, UsuarioService::restablecerClave($sesion, $params['id']));
}, 'ADMIN');
