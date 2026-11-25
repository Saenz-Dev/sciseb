<?php
/** @var Sci_Router $router */

$router->post('/auth/login', function (Sci_Request $req, $params, $sesion) {
    $correo = $req->campo('correo');
    $clave = $req->campo('clave');
    if (!is_string($correo) || !is_string($clave) || $correo === '' || $clave === '') {
        throw Sci_Api_Exception::solicitudInvalida('Debe indicar correo y clave.');
    }
    $resultado = AuthService::login($correo, $clave);
    Sci_Response::json(200, $resultado);
}, 'PUBLIC');

$router->get('/auth/yo', function (Sci_Request $req, $params, $sesion) {
    Sci_Response::json(200, AuthService::yo($sesion));
}, 'AUTH');

$router->post('/auth/cambiar-clave', function (Sci_Request $req, $params, $sesion) {
    $resultado = AuthService::cambiarClave($sesion, $req->campo('claveActual'), $req->campo('claveNueva'));
    Sci_Response::json(200, $resultado);
}, 'AUTH');
