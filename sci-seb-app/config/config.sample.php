<?php
// Copie este archivo a config.php (fuera de git) y rellene valores reales.
// No usar el cierre "?>" en ningún archivo PHP del proyecto (ver notas de la fase 0 del plan):
// un espacio o salto de línea después de él corrompe la salida JSON.

return array(

    'db' => array(
        'dsn'      => 'mysql:host=localhost;dbname=c32sciseb;charset=utf8',
        'usuario'  => 'c32sciseb',
        'clave'    => 'ESCRIBA_AQUI_UNA_CLAVE_FUERTE',
    ),

    'jwt' => array(
        // Debe tener 32 bytes o más. Generar con: php -r "echo bin2hex(random_bytes(32));"
        'secreto'            => 'CAMBIAR_POR_UN_SECRETO_DE_32_BYTES_O_MAS',
        'expiracion_minutos' => 30,
    ),

    'cors' => array(
        // Lista separada por comas de orígenes permitidos.
        'origenes' => array('http://localhost:8080'),
    ),

    'admin' => array(
        // Usados solo por scripts/bootstrap_admin.php la primera vez (tabla usuario vacía).
        'correo' => 'admin@sciseb.local',
        'clave'  => 'Cambiar123*',
    ),

    'importacion' => array(
        'max_filas'            => 2000,
        'minutos_vista_previa' => 15,
    ),

    'exportacion' => array(
        'max_filas' => 5000,
    ),

);
