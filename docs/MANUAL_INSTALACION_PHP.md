# Manual de instalación — SCI-SEB (versión PHP, rama `migracion-php`)

Esta guía es para el hosting compartido real: cPanel con **PHP 5.3.3** fijo y **MySQL ~5.1**,
acceso solo por **FTP** (sin SSH). Si en algún momento consigue acceso SSH a un VPS, use en cambio
`docs/MANUAL_INSTALACION.md` (la versión Java) — es la opción más barata en esfuerzo y más segura.

## 0. Antes de empezar

- Confirme la versión real de PHP en el panel (`Select PHP Version` o `MultiPHP Manager`) y de
  MySQL (`phpMyAdmin` → pestaña SQL → `SELECT VERSION();`).
- Reúna: usuario/clave de FTP, usuario/clave de phpMyAdmin (o del panel de bases de datos), y el
  nombre que el proveedor le dio a la base de datos y al usuario de MySQL (ej. `c32sciseb`).

## 1. Estructura de carpetas: qué va dónde

El proyecto tiene dos carpetas en la raíz del repo que se suben a **lugares distintos** del
servidor — esto es lo más importante de todo el despliegue, si se mezclan no funciona:

```
sci-seb-app/   →  FUERA de public_html (p. ej. /home/<usuario>/sci-seb-app/)
public_html/   →  DENTRO de public_html (es la raíz del dominio)
```

`sci-seb-app/` contiene el código PHP (nunca debe quedar accesible por navegador). `public_html/`
contiene las páginas HTML/CSS/JS y la carpeta `api/` que las conecta con `sci-seb-app/`.

En su cliente FTP (FileZilla, etc.):
1. Conéctese y ubique la carpeta raíz de su cuenta (un nivel **arriba** de `public_html`).
2. Cree ahí una carpeta nueva llamada `sci-seb-app`.
3. Suba el **contenido** de `sci-seb-app/` del repo dentro de esa carpeta nueva.
4. Suba el **contenido** de `public_html/` del repo dentro de la carpeta `public_html` que ya
   existe en su cuenta (no la reemplace si ya tiene algo — fusione).

## 2. Configuración (`sci-seb-app/config/config.php`)

Este archivo **no está en el repo** (tiene secretos). Antes de subir, cree uno localmente a partir
de `sci-seb-app/config/config.sample.php` y rellénelo:

- `db.dsn`/`db.usuario`/`db.clave`: los que le dio el proveedor para la base de datos (sección 3).
- `jwt.secreto`: genere uno nuevo de 32+ bytes. Si tiene PHP local: `php -r "echo bin2hex(random_bytes(32));"`.
  Si no, cualquier generador de contraseñas largas (32+ caracteres) sirve.
- `cors.origenes`: la URL real de su dominio, p. ej. `array('https://sueducacion.gov.co')`.
- `admin.correo`/`admin.clave`: el correo y clave del primer administrador (la clave real se la
  pedirá cambiar en el primer ingreso; este valor solo se usa una vez, en el paso 5).

Suba este `config.php` ya relleno a `sci-seb-app/config/` por FTP.

## 3. Base de datos (por phpMyAdmin, sin SSH)

Siga exactamente la sección **"Servidor con MySQL 5.1"** de `basedatos/README.md`, pero con estos
archivos (son los que ya están preparados para esta versión del PHP sin triggers modernos):

1. Cree la base de datos y el usuario (si el proveedor no lo hizo ya) con el `CREATE DATABASE`/
   `CREATE USER`/`GRANT`/`REVOKE` de esa sección — hecho por phpMyAdmin, pestaña SQL.
2. Ejecute en orden, cada uno como una pestaña SQL nueva sobre la base de datos:
   - `basedatos/01_esquema_mysql51.sql`
   - `basedatos/02_datos_iniciales.sql`
   - `basedatos/04_import_preview_php.sql` (**nueva**, solo para la versión PHP)
   - `basedatos/03_datos_demo_opcional.sql` (opcional, solo para probar con datos ficticios)
3. Verifique el `REVOKE`: conectado como el usuario de la app (no como root), intente
   `UPDATE auditoria SET detalle='prueba' WHERE id_auditoria=1;` — debe fallar con "command denied".
   Esto es lo que protege la auditoría de ediciones, ya que en MySQL 5.1 no hay triggers para eso.

## 4. Primer administrador (sin SSH: Cron Jobs de cPanel)

`scripts/bootstrap_admin.php` crea el primer usuario ADMIN. Java lo hacía solo al arrancar; en PHP
hay que correrlo una vez. Sin SSH, use **Cron Jobs** del panel:

1. cPanel → **Cron Jobs** → agregar uno nuevo.
2. Comando: `php /home/<usuario>/sci-seb-app/scripts/bootstrap_admin.php >> /home/<usuario>/bootstrap.log 2>&1`
   (reemplace `<usuario>` por el usuario real de cPanel — view la ruta exacta en el administrador
   de archivos del panel, botón "Home").
3. Programe que corra **una sola vez**, dentro del próximo minuto (por ejemplo minuto/hora actual + 1).
4. Espere un minuto, revise `bootstrap.log` desde el Administrador de Archivos del panel.
   Debe decir "Administrador inicial creado: ...". Si dice "la tabla usuario ya tiene datos", ya
   existe y no hizo falta.
5. **Borre el cron job** (ya cumplió su propósito; no debe quedar corriendo cada minuto).

## 5. Primer ingreso y verificación

1. Abra `https://<su-dominio>/` — debe cargar `index.html`.
2. Inicie sesión con el correo/clave de `admin.correo`/`admin.clave` del paso 2.
3. Le va a pedir cambiar la clave (por diseño: `PENDIENTE_CAMBIO_CLAVE`).
4. Recorra las pantallas igual que indicaba el manual Java: catálogos, un caso de prueba,
   importar/exportar CSV.

## 6. Si algo no funciona

- **Página en blanco o error 500 genérico**: revise el log de errores de PHP del panel
  (cPanel → "Errors" o el archivo `error_log` dentro de `public_html`).
- **La API responde 404 en todo `/api/...`**: su Apache no tiene `mod_rewrite` activo, o
  `public_html/api/.htaccess` no se subió. Pídale al proveedor que confirme `mod_rewrite`.
- **Login funciona pero el resto da 401 todo el tiempo**: la cabecera `Authorization` se está
  perdiendo; confirme que `public_html/api/.htaccess` se subió completo (tiene el `RewriteRule`
  que la rescata) y que el hosting no la filtra en otro nivel (algunos paneles con PHP como
  `php-cgi` necesitan que el proveedor habilite `CGIPassAuth`, que no se puede activar desde FTP).
- **Error de conexión a la base de datos**: revise `sci-seb-app/config/config.php` — más común,
  un error de tipeo en usuario/clave/nombre de base de datos.
