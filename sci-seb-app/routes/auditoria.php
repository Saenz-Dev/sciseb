<?php
/** @var Sci_Router $router */

$router->get('/auditoria', function (Sci_Request $req, $params, $sesion) {
    $resultado = AuditoriaService::consultar(array(
        'casoId'    => $req->query('casoId'),
        'usuarioId' => $req->query('usuarioId'),
        'desde'     => $req->query('desde'),
        'hasta'     => $req->query('hasta'),
        'pagina'    => $req->query('pagina', 0),
        'tamano'    => $req->query('tamano', 30),
    ));
    Sci_Response::json(200, $resultado);
}, 'ADMIN');
