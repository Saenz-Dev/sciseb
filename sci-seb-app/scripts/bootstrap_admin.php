<?php
/**
 * Puerto de DataInitializer.java. Java lo corría automáticamente al arrancar el proceso Spring
 * Boot; PHP no tiene un "arranque" equivalente (cada request es un proceso nuevo), así que esto
 * se corre una sola vez a mano, por SSH, después de cargar el esquema y los catálogos:
 *
 *   php scripts/bootstrap_admin.php
 *
 * Solo hace algo si la tabla usuario está vacía — seguro de volver a correr por error.
 */

require dirname(__FILE__) . '/../bootstrap.php';
Sci_Config::cargar(dirname(__FILE__) . '/../config/config.php');

$pdo = Sci_Db::conexion();

$totalUsuarios = (int) $pdo->query('SELECT COUNT(*) FROM usuario')->fetchColumn();
if ($totalUsuarios > 0) {
    fwrite(STDOUT, "La tabla usuario ya tiene datos ($totalUsuarios fila(s)) — no se hace nada.\n");
    exit(0);
}

$area = $pdo->query('SELECT id_area, nombre FROM area ORDER BY id_area ASC LIMIT 1')->fetch();
if ($area === false) {
    fwrite(STDERR, "No hay ninguna área cargada todavía. Ejecute primero basedatos/02_datos_iniciales.sql.\n");
    exit(1);
}
$rolAdmin = $pdo->query("SELECT id_rol FROM rol WHERE nombre = 'ADMIN'")->fetch();
if ($rolAdmin === false) {
    fwrite(STDERR, "No existe el rol ADMIN todavía. Ejecute primero basedatos/02_datos_iniciales.sql.\n");
    exit(1);
}

$cfgAdmin = Sci_Config::get('admin');
$correo = $cfgAdmin['correo'];
$clave = $cfgAdmin['clave'];
$hash = Sci_Bcrypt::hash($clave, 10);

$pdo->prepare(
    'INSERT INTO usuario (nombre, correo, clave_hash, id_area, id_rol, estado, intentos_fallidos)
     VALUES (?, ?, ?, ?, ?, \'PENDIENTE_CAMBIO_CLAVE\', 0)'
)->execute(array('Administrador del sistema', $correo, $hash, (int) $area['id_area'], (int) $rolAdmin['id_rol']));

fwrite(STDOUT, "Administrador inicial creado: $correo (área '{$area['nombre']}'). Debe cambiar la clave en el primer ingreso.\n");
