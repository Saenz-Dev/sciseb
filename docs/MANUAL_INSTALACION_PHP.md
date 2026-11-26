# Manual de instalación — SCI-SEB (versión PHP, rama `migracion-php`)

Esta guía es para el hosting compartido real de la Secretaría: **PHP 5.3.3** fijo, **MySQL 5.1.x**,
acceso solo por **FTP** (sin SSH), y un panel que **no usa `public_html`** al estilo cPanel sino
`web` (ya sirve un WordPress existente) y `private` (fuera del docroot, hermana de `web`). Si en
algún momento consigue acceso SSH a un VPS, use en cambio `docs/MANUAL_INSTALACION.md` (la versión
Java) — es la opción más barata en esfuerzo y más segura.

## 0. Antes de empezar

- Confirme la versión real de PHP en el panel y de MySQL (`phpMyAdmin` → pestaña SQL →
  `SELECT VERSION();`). Ya confirmado: PHP 5.3.3, MySQL 5.1.x.
- Reúna: usuario/clave de FTP, usuario/clave de phpMyAdmin, y el nombre que el proveedor le dio a
  la base de datos y al usuario de MySQL (en este caso `c32sciseb`).

## 1. Estructura de carpetas real: qué va dónde

En este hosting, la raíz de la cuenta tiene (entre otras) las carpetas `web/` (ya con un WordPress
adentro) y `private/` (fuera del docroot — pensada justamente para archivos que no deben verse por
navegador). Por eso la app **no** queda en la raíz del dominio sino en una subcarpeta:

```
private/sci-seb-app/   →  contenido de sci-seb-app/ del repo (backend, NO accesible por web)
web/siuce/              →  contenido de public_html/ del repo (queda en https://sedboyaca.gov.co/siuce/)
```

En su cliente FTP (FileZilla):
1. Entre a la carpeta `private/` (sibling de `web/`) y cree ahí una carpeta `sci-seb-app`.
2. Suba el **contenido** de `sci-seb-app/` del repo dentro de `private/sci-seb-app/`
   (incluido ya `config/config.php`, ver paso 2).
3. Entre a `web/` y cree ahí una carpeta nueva `siuce` (o el nombre que prefiera — si cambia el
   nombre, no hace falta tocar ningún archivo del proyecto, `js/api.js` y `api/index.php` se
   adaptan solos a la carpeta donde queden).
4. Suba el **contenido** de `public_html/` del repo dentro de `web/siuce/`.

**No toque nada más dentro de `web/`** — ahí sigue viviendo el WordPress existente, intacto.

### Conviviendo con el WordPress que ya está en `web/`

El `.htaccess` de WordPress en la raíz de `web/` redirige cualquier URL que no sea un archivo real
a su `/index.php` — eso incluye por error las rutas de nuestra API (`/siuce/api/...`). Ya se agregó
la solución (confirmado y probado: el WordPress sigue funcionando normal): dos líneas **antes** del
bloque `# BEGIN WordPress` / `# END WordPress` de ese `.htaccess`, para que no se toquen con una
futura regeneración automática del bloque de WordPress:

```apache
RewriteEngine On
RewriteRule ^siuce/ - [L]

# BEGIN WordPress
...
# END WordPress
```

Si alguna vez cambia el nombre de la subcarpeta (de `siuce` a otra cosa), actualice ese
`RewriteRule ^siuce/ - [L]` con el nombre nuevo.

## 2. Configuración (`sci-seb-app/config/config.php`)

Este archivo **no está en el repo** (tiene secretos). Se crea localmente a partir de
`sci-seb-app/config/config.sample.php` y se sube ya relleno dentro de
`private/sci-seb-app/config/`:

- `db.dsn`/`db.usuario`/`db.clave`: los reales de la base `c32sciseb` (sección 3).
- `jwt.secreto`: un valor nuevo de 32+ bytes, generado una sola vez para este despliegue (no
  reutilizar el de desarrollo local).
- `cors.origenes`: `array('https://sedboyaca.gov.co')` — el origen (protocolo+dominio, sin la
  subcarpeta `/siuce`, el navegador nunca manda la ruta en la cabecera `Origin`).
- `admin.correo`/`admin.clave`: el correo y clave del primer administrador (la clave real se la
  pedirá cambiar en el primer ingreso; este valor solo se usa una vez, en el paso 5).

## 3. Base de datos (por phpMyAdmin, sin SSH)

Siga exactamente la sección **"Servidor con MySQL 5.1"** de `basedatos/README.md`, con estos
archivos (preparados para esta versión sin triggers modernos):

1. Cree la base de datos y el usuario (si el proveedor no lo hizo ya) con el `CREATE DATABASE`/
   `CREATE USER`/`GRANT`/`REVOKE` de esa sección — por phpMyAdmin, pestaña SQL.
2. Ejecute en orden, cada uno como una pestaña SQL nueva sobre la base de datos:
   - `basedatos/01_esquema_mysql51.sql`
   - `basedatos/02_datos_iniciales.sql`
   - `basedatos/04_import_preview_php.sql` (**nueva**, solo para la versión PHP)
   - `basedatos/03_datos_demo_opcional.sql` (opcional, solo para probar con datos ficticios)
3. Verifique el `REVOKE`: conectado como el usuario de la app (no como root), intente
   `UPDATE auditoria SET detalle='prueba' WHERE id_auditoria=1;` — debe fallar con "command denied".
   Esto es lo que protege la auditoría de ediciones, ya que en MySQL 5.1 no hay triggers para eso.

## 4. Primer administrador (sin SSH: Cron Jobs del panel)

`scripts/bootstrap_admin.php` crea el primer usuario ADMIN. Java lo hacía solo al arrancar; en PHP
hay que correrlo una vez. Sin SSH, use **Cron Jobs** (o el equivalente de su panel):

1. Agregue un cron job nuevo.
2. Comando (ajuste la ruta real de su cuenta, visible en el administrador de archivos del panel):
   `php /ruta/completa/private/sci-seb-app/scripts/bootstrap_admin.php >> /ruta/completa/bootstrap.log 2>&1`
3. Prográmelo para correr **una sola vez**, dentro del próximo minuto.
4. Espere un minuto, revise `bootstrap.log`. Debe decir "Administrador inicial creado: ...". Si
   dice "la tabla usuario ya tiene datos", ya existe y no hizo falta.
5. **Borre el cron job** (ya cumplió su propósito; no debe quedar corriendo cada minuto).

## 5. Primer ingreso y verificación

1. Abra `https://sedboyaca.gov.co/siuce/` — debe cargar `index.html` (y el resto del sitio
   WordPress en la raíz debe seguir funcionando igual que antes).
2. Inicie sesión con el correo/clave de `admin.correo`/`admin.clave` del paso 2.
3. Le va a pedir cambiar la clave (por diseño: `PENDIENTE_CAMBIO_CLAVE`).
4. Recorra las pantallas: catálogos, un caso de prueba, importar/exportar CSV.

## 6. Si algo no funciona

- **Página en blanco o error 500 genérico**: revise el log de errores de PHP del panel.
- **La API responde 404 en todo `/siuce/api/...`**: Apache no tiene `mod_rewrite` activo bajo
  `web/siuce/api/`, o no se subió `web/siuce/api/.htaccess`.
- **El WordPress de la raíz se rompió o empezó a redirigir raro**: revise que las dos líneas
  agregadas al `.htaccess` de `web/` queden **antes** de `# BEGIN WordPress`, y que no haya quedado
  ningún error de sintaxis en esas dos líneas.
- **Login funciona pero el resto da 401 todo el tiempo**: la cabecera `Authorization` se está
  perdiendo; confirme que `web/siuce/api/.htaccess` se subió completo, y que el hosting no la
  filtra en otro nivel (algunos paneles con PHP como `php-cgi` necesitan `CGIPassAuth`, que no se
  puede activar desde FTP — pídaselo al proveedor si pasa esto).
- **Error de conexión a la base de datos**: revise `private/sci-seb-app/config/config.php` — más
  común, un error de tipeo en usuario/clave/nombre de base de datos.
