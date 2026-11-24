# Manual de instalación — SCI-SEB

Esta guía cubre dos escenarios: instalación local para desarrollo/pruebas, e instalación en un
**servidor Linux con Apache y MySQL/MariaDB** para producción.

## 0. Antes de empezar: cómo encaja Apache en esta aplicación

SCI-SEB **no es una aplicación PHP** que Apache ejecute directamente (como lo haría con
`mod_php`). Es una aplicación **Java Spring Boot** que trae su propio servidor web embebido
(Tomcat) y escucha sola en un puerto (por defecto el 8080). Apache, en este esquema, cumple otro
papel — el habitual en producción para cualquier aplicación Java —: queda **delante** de la
aplicación como **proxy inverso**, y se encarga de:

* Atender el puerto 80/443 (el que el público usa) y la terminación de HTTPS (certificado SSL).
* Redirigir (`ProxyPass`) las peticiones hacia `http://127.0.0.1:8080`, donde corre la aplicación.
* Sumar una capa adicional de cabeceras de seguridad, compresión, etc., si se desea.

```
Internet → Apache (puerto 443, HTTPS) → proxy_pass → Spring Boot/Tomcat (puerto 8080, interno)
                                                            ↓
                                                      MySQL/MariaDB (puerto 3306)
```

Sobre **MySQL**: la aplicación se desarrolló y probó contra **MariaDB** (10.6+), pero MariaDB es un
reemplazo binario de MySQL y el conector usado (`mariadb-java-client`) es compatible con ambos. Si
el servidor ya tiene **MySQL** instalado (5.7+ u 8.0+) en vez de MariaDB, los pasos de este manual
funcionan igual — solo cambian los nombres de los paquetes a instalar (se indica en cada caso).

> **Antes de seguir, confirme la versión real del servidor** (en phpMyAdmin, pestaña SQL:
> `SELECT VERSION();`). Algunos hospedajes compartidos todavía ofrecen **MySQL 5.1** (de 2008-2009,
> sin soporte desde 2013). Esa versión no entiende `SIGNAL SQLSTATE` (los triggers de
> `01_esquema.sql`) ni `utf8mb4`, y dará un error de sintaxis al cargar el esquema normal. Si es su
> caso, **pida al proveedor que actualice a MariaDB 10.x o MySQL 5.7+/8.0** — un motor sin parches
> de seguridad hace 13 años es un riesgo serio para datos sensibles (Ley 1581 de 2012) — y mientras
> tanto siga la sección 2.2-bis de este manual en vez de la 2.2/2.3 normales.

## 1. Requisitos del servidor

* Linux (Ubuntu/Debian o RHEL/CentOS; esta guía usa comandos de Ubuntu/Debian, con la alternativa
  `dnf`/`yum` donde cambia).
* JDK 21 o superior (probado con JDK 25).
* Maven 3.9+ (solo para compilar; en el servidor de producción basta con el `.jar` ya compilado).
* MariaDB 10.6+ o MySQL 8.0+.
* Apache HTTP Server 2.4+ con los módulos `proxy` y `proxy_http`.
* Un dominio o subdominio apuntando al servidor, y (recomendado) un certificado HTTPS (por ejemplo
  con Let's Encrypt / Certbot).

## 2. Instalar la base de datos en el servidor

### 2.1 Instalar MariaDB (recomendado)

```bash
# Ubuntu/Debian
sudo apt update
sudo apt install -y mariadb-server mariadb-client

# RHEL/CentOS/Rocky
sudo dnf install -y mariadb-server mariadb

sudo systemctl enable --now mariadb
sudo mysql_secure_installation   # fija la clave de root, quita usuarios anónimos, etc.
```

### 2.1-bis Alternativa: instalar MySQL en vez de MariaDB

```bash
# Ubuntu/Debian
sudo apt update
sudo apt install -y mysql-server

sudo systemctl enable --now mysql
sudo mysql_secure_installation
```

Todo lo que sigue (creación de la base, usuario, carga del esquema) es **idéntico** para MySQL: el
comando de línea de comandos se llama `mysql` en ambos motores.

### 2.2 Crear la base de datos y el usuario de la aplicación

Conéctese como root (`mysql -u root -p` o `sudo mysql` si usa autenticación por socket) y ejecute:

```sql
CREATE DATABASE c32sciseb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'c32sciseb'@'localhost' IDENTIFIED BY 'ESCRIBA_AQUI_UNA_CLAVE_FUERTE';

-- Mínimo privilegio: la aplicación solo necesita leer y escribir filas en tiempo de ejecución.
-- Nunca crea, altera ni borra tablas (eso se hace a mano, como root, una sola vez — sección 2.3).
-- Si una clave llegara a filtrarse, con esto no se podría borrar ni alterar el esquema con ella.
GRANT SELECT, INSERT, UPDATE, DELETE ON c32sciseb.* TO 'c32sciseb'@'localhost';
FLUSH PRIVILEGES;
```

> **Nota:** `c32sciseb` es el nombre real que el proveedor de hospedaje asignó tanto a la base de
> datos como al usuario para este servidor (es común que el hospedaje anteponga un prefijo de
> cuenta, como `c32`). Si en otro servidor le asignan un nombre distinto, use ese en su lugar —
> **la base de datos y el usuario de MySQL/MariaDB no tienen que llamarse igual que la
> aplicación** (`sciseb`), que es el nombre del servicio Linux/Apache (secciones 4 y 5) y no
> cambia.

> **Importante:** reemplace `ESCRIBA_AQUI_UNA_CLAVE_FUERTE` por una clave real, larga (20+
> caracteres) y distinta en cada ambiente — por ejemplo generada con `openssl rand -base64 24`.
> Esa misma clave se usará luego en la variable de entorno `DB_PASSWORD` (sección 4.2).
> Si la aplicación Java y MariaDB corren en **el mismo servidor** (lo normal en esta arquitectura),
> deje el usuario restringido a `'localhost'` como arriba — así la base no queda expuesta a la red
> aunque alguien conociera la clave.
>
> Los scripts de `basedatos/` (carga inicial del esquema, y cualquier `ALTER TABLE` futuro) se
> ejecutan **como `root`**, nunca con el usuario `c32sciseb` — por eso este usuario no necesita más
> privilegios que los de arriba.

### 2.2-bis Si el servidor tiene MySQL 5.1 (muy antiguo)

Use esto **en vez de** la sección 2.2, solo si `SELECT VERSION();` mostró 5.0.x o 5.1.x:

```sql
CREATE DATABASE c32sciseb CHARACTER SET utf8 COLLATE utf8_unicode_ci;
CREATE USER 'c32sciseb'@'localhost' IDENTIFIED BY 'ESCRIBA_AQUI_UNA_CLAVE_FUERTE';
GRANT SELECT, INSERT, UPDATE, DELETE ON c32sciseb.* TO 'c32sciseb'@'localhost';

-- MySQL 5.1 no soporta SIGNAL (necesita 5.5+), así que la tabla auditoria no se puede proteger
-- con un trigger. Se protege quitándole a este usuario el permiso de editarla o borrarla:
REVOKE UPDATE, DELETE ON c32sciseb.auditoria FROM 'c32sciseb'@'localhost';
FLUSH PRIVILEGES;
```

Y en la sección 2.3, cargue `basedatos/01_esquema_mysql51.sql` en vez de `01_esquema.sql` (ver
`basedatos/README.md`, sección "Servidor con MySQL 5.1", para más detalle).

### 2.3 Cargar el esquema (una sola vez)

Copie la carpeta `basedatos/` del proyecto al servidor (o clone el repositorio completo) y ejecute,
**en este orden**:

```bash
mysql -u root -p c32sciseb < basedatos/01_esquema.sql
mysql -u root -p c32sciseb < basedatos/02_datos_iniciales.sql
```

(Si el servidor tiene MySQL 5.1 — sección 2.2-bis —, use `basedatos/01_esquema_mysql51.sql` en vez
de `01_esquema.sql`.)

Esto crea las 17 tablas, los índices, los dos triggers que protegen la auditoría (o, en MySQL 5.1,
el `REVOKE` de la sección 2.2-bis cumple ese mismo papel), y carga los catálogos fijos (roles, áreas
de referencia, clasificación SIUCE, las 15 provincias de Boyacá y los 12 pasos de la ruta de
atención). **No** ejecute `03_datos_demo_opcional.sql` en producción —ese script solo carga
instituciones y sedes ficticias para ambientes de prueba.

En producción, el Administrador carga las instituciones y sedes reales desde la pantalla
**Catálogos** una vez que el sistema está en línea.

### 2.4 Verificar

```bash
mysql -u c32sciseb -p c32sciseb -e "SHOW TABLES;"
```

Debe listar 17 tablas (`area`, `usuario`, `caso`, `auditoria`, etc.).

## 3. Compilar la aplicación

En su máquina de desarrollo (o en el propio servidor, si tiene Maven instalado ahí):

```bash
mvn clean package -DskipTests
```

Esto genera `target/sci-seb-1.0.0.jar` — un `.jar` ejecutable que ya incluye el servidor Tomcat
embebido y todas las dependencias. Es el único archivo que hace falta copiar al servidor de
producción (no hace falta copiar el código fuente ni Maven allá, salvo que prefiera compilar en el
propio servidor).

```bash
scp target/sci-seb-1.0.0.jar usuario@servidor:/opt/sciseb/sci-seb.jar
```

## 4. Configurar y arrancar la aplicación en el servidor

### 4.1 Crear un usuario de sistema dedicado (recomendado)

```bash
sudo useradd -r -s /sbin/nologin sciseb
sudo mkdir -p /opt/sciseb
sudo chown sciseb:sciseb /opt/sciseb
```

### 4.2 Variables de entorno de producción

Cree `/opt/sciseb/sciseb.env` (léalo con cuidado, contiene secretos — permisos `600`):

```bash
DB_URL=jdbc:mariadb://localhost:3306/c32sciseb
DB_USER=c32sciseb
DB_PASSWORD=ESCRIBA_AQUI_LA_MISMA_CLAVE_FUERTE_DEL_PASO_2.2

# Mínimo 32 caracteres, generado al azar — por ejemplo con: openssl rand -base64 48
JWT_SECRET=GENERE_UN_SECRETO_LARGO_Y_UNICO_AQUI

# Dominio público real de la aplicación (sin barra final)
CORS_ORIGENES=https://sciseb.sedboyaca.gov.co

# Administrador inicial: se crea solo la primera vez que arranca con la base vacía
ADMIN_CORREO=nucleoseducativos@sedboyaca.gov.co
ADMIN_CLAVE=UnaClaveTemporalFuerte123*   # genere una propia (ver sección anterior) — esto es solo un ejemplo de formato
```

```bash
sudo chmod 600 /opt/sciseb/sciseb.env
sudo chown sciseb:sciseb /opt/sciseb/sciseb.env
```

### 4.3 Servicio systemd

Cree `/etc/systemd/system/sciseb.service`:

```ini
[Unit]
Description=SCI-SEB
After=network.target mariadb.service

[Service]
User=sciseb
Group=sciseb
EnvironmentFile=/opt/sciseb/sciseb.env
ExecStart=/usr/bin/java -jar /opt/sciseb/sci-seb.jar
SuccessExitStatus=143
Restart=on-failure
RestartSec=5

[Install]
WantedBy=multi-user.target
```

Si usa MySQL en vez de MariaDB, cambie `After=mariadb.service` por `After=mysql.service` (o
`mysqld.service`, según la distribución).

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now sciseb
sudo systemctl status sciseb
sudo journalctl -u sciseb -f     # ver los registros en vivo
```

La aplicación queda escuchando en `127.0.0.1:8080` (no hace falta abrirlo a la red: solo Apache lo
necesita, en el mismo servidor).

## 5. Configurar Apache como proxy inverso

### 5.1 Habilitar los módulos necesarios

```bash
sudo a2enmod proxy proxy_http headers ssl
sudo systemctl restart apache2
```

(En RHEL/CentOS los módulos de proxy suelen venir activos por defecto en `httpd`; revise
`/etc/httpd/conf.modules.d/`.)

### 5.2 VirtualHost

Cree `/etc/apache2/sites-available/sciseb.conf` (Ubuntu/Debian; en RHEL sería
`/etc/httpd/conf.d/sciseb.conf`):

```apache
<VirtualHost *:80>
    ServerName sciseb.sedboyaca.gov.co

    # Redirige todo a HTTPS una vez tenga el certificado (ver 5.3)
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</VirtualHost>

<VirtualHost *:443>
    ServerName sciseb.sedboyaca.gov.co

    SSLEngine on
    SSLCertificateFile      /etc/letsencrypt/live/sciseb.sedboyaca.gov.co/fullchain.pem
    SSLCertificateKeyFile   /etc/letsencrypt/live/sciseb.sedboyaca.gov.co/privkey.pem

    ProxyPreserveHost On
    ProxyPass        / http://127.0.0.1:8080/
    ProxyPassReverse / http://127.0.0.1:8080/

    ErrorLog  ${APACHE_LOG_DIR}/sciseb_error.log
    CustomLog ${APACHE_LOG_DIR}/sciseb_access.log combined
</VirtualHost>
```

```bash
sudo a2ensite sciseb.conf
sudo apache2ctl configtest
sudo systemctl reload apache2
```

### 5.3 Certificado HTTPS con Let's Encrypt (recomendado)

```bash
sudo apt install -y certbot python3-certbot-apache
sudo certbot --apache -d sciseb.sedboyaca.gov.co
```

Certbot ajusta el `VirtualHost` automáticamente y programa la renovación. La aplicación ya envía la
cabecera HSTS (`Strict-Transport-Security`), así que una vez activo HTTPS, el navegador exigirá
HTTPS en visitas futuras — confirme que el certificado quede bien instalado **antes** de anunciar la
URL a los usuarios.

## 6. Primer ingreso

1. Abra `https://sciseb.sedboyaca.gov.co` (o la URL que configuró).
2. Ingrese con el correo y la clave de `ADMIN_CORREO`/`ADMIN_CLAVE` (sección 4.2).
3. El sistema obliga a cambiar esa clave de inmediato — hágalo y guarde la nueva clave en un lugar
   seguro (no la clave temporal del paso anterior).
4. Desde **Catálogos**, cargue los municipios, instituciones y sedes reales del departamento.
5. Desde **Usuarios**, cree las cuentas del resto del personal.

## 7. Copias de seguridad

Programe una tarea periódica (por ejemplo con `cron`) que corra `mariadb-dump` (o `mysqldump`) y
guarde el resultado en un lugar **distinto** al servidor:

```bash
# /etc/cron.d/sciseb-backup
0 2 * * * sciseb /usr/bin/mariadb-dump -u c32sciseb -p'LA_CLAVE' --routines --triggers \
  --single-transaction --default-character-set=utf8mb4 c32sciseb \
  | gzip > /var/backups/sciseb/c32sciseb_$(date +\%Y\%m\%d).sql.gz
```

> En MySQL 5.1 (sección 2.2-bis) cambie `--default-character-set=utf8mb4` por `=utf8` en el
> comando de arriba — esa versión no entiende `utf8mb4`.

Vea también `basedatos/exportaciones/README.md` (en el repositorio) para el procedimiento de
restauración y una nota sobre por qué estos archivos son confidenciales (contienen datos
personales protegidos por la Ley 1581 de 2012) y nunca deben subirse a un repositorio público.

## 8. Actualizar la aplicación (nueva versión)

```bash
# 1. Compile la nueva versión (en su máquina de desarrollo)
mvn clean package -DskipTests

# 2. Si la nueva versión necesita cambios de esquema, aplíquelos a mano primero
#    (ALTER TABLE ...), ya que no hay migración automática

# 3. Copie el nuevo .jar y reinicie el servicio
scp target/sci-seb-1.0.0.jar usuario@servidor:/opt/sciseb/sci-seb.jar
sudo systemctl restart sciseb
```

## 9. Endurecimiento del servidor (datos sensibles — Ley 1581 de 2012)

Como el sistema maneja nombres, cédulas y procesos disciplinarios/penales, no basta con que la
aplicación esté bien escrita: el **servidor** donde corre también debe estar asegurado. Esta
sección complementa las secciones 2, 4 y 5 (no las repite).

### 9.1 Firewall: solo deje entrar lo necesario

Únicamente SSH (idealmente desde IPs conocidas) y Apache (80/443) deben verse desde internet. Ni el
puerto 8080 (aplicación) ni el 3306 (base de datos) deben estar expuestos — ya quedan solo en
`localhost` siguiendo las secciones 4 y 2, pero el firewall es una segunda barrera por si algo se
configura mal más adelante:

```bash
# Ubuntu/Debian (ufw)
sudo ufw default deny incoming
sudo ufw allow OpenSSH
sudo ufw allow 'Apache Full'   # 80 y 443
sudo ufw enable
sudo ufw status

# RHEL/CentOS/Rocky (firewalld)
sudo firewall-cmd --permanent --add-service=ssh
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --permanent --add-service=https
sudo firewall-cmd --reload
```

### 9.2 MariaDB/MySQL: que no escuche en la red

Confirme que la base de datos solo acepta conexiones locales (`bind-address`), para que ni
siquiera un error de firewall la deje alcanzable desde afuera:

```ini
# /etc/mysql/mariadb.conf.d/50-server.cnf (MariaDB) o /etc/my.cnf.d/ (MySQL)
[mysqld]
bind-address = 127.0.0.1
```

```bash
sudo systemctl restart mariadb   # o mysql
```

`mysql_secure_installation` (sección 2.1) ya debió quitar los usuarios anónimos, la base `test` y
el acceso remoto de `root` — si no está seguro de haberlo corrido, vuelva a ejecutarlo.

### 9.3 SSH

```bash
# /etc/ssh/sshd_config
PermitRootLogin no
PasswordAuthentication no      # solo llaves públicas
```

Instale `fail2ban` para bloquear IPs con intentos repetidos de fuerza bruta contra SSH:

```bash
sudo apt install -y fail2ban
sudo systemctl enable --now fail2ban
```

(La aplicación ya tiene su propio bloqueo de cuentas tras 5 intentos fallidos de clave — ver el
Manual del programador, sección 4.3 — esto protege además el acceso al propio servidor.)

### 9.4 Archivos y permisos

* `sciseb.env` (sección 4.2) con permisos `600` y dueño `sciseb` — ya indicado, pero verifíquelo:
  `ls -l /opt/sciseb/sciseb.env` debe mostrar `-rw-------`.
* El usuario de sistema `sciseb` (sección 4.1) no debe tener shell de inicio de sesión
  (`-s /sbin/nologin`, ya incluido) ni permisos de `sudo`.
* Las copias de seguridad (sección 7) y cualquier exportación manual
  (`basedatos/exportaciones/`) deben quedar con permisos restringidos y, si se guardan fuera del
  servidor, **cifradas** en tránsito y en reposo — por ejemplo:

  ```bash
  gpg --symmetric --cipher-algo AES256 sciseb_20261120.sql.gz
  ```

### 9.5 Mantenimiento

* Actualice el sistema operativo y Apache con regularidad
  (`sudo apt update && sudo apt upgrade`, con sus ventanas de mantenimiento habituales).
  Revise también las versiones de las dependencias de `pom.xml` cada cierto tiempo (Spring Boot,
  jjwt, Apache POI) por si salen parches de seguridad.
* Revise `sudo journalctl -u sciseb` y los registros de Apache (`/var/log/apache2/`) periódicamente
  por actividad anómala.
* La pantalla **Auditoría** del sistema (solo Administrador) es la fuente de verdad de quién hizo
  qué — revísela periódicamente, no solo cuando haya un incidente.

## 10. Lista de verificación de seguridad antes de salir a producción

- [ ] `JWT_SECRET` cambiado por uno largo y generado al azar (no el valor de desarrollo).
- [ ] `ADMIN_CLAVE` cambiada después del primer ingreso.
- [ ] Clave del usuario `c32sciseb` de la base de datos fuerte y distinta por ambiente.
- [ ] HTTPS activo y funcionando (Apache con certificado válido).
- [ ] El puerto 8080 (Spring Boot/Tomcat) **no** expuesto directamente a internet — solo accesible
      desde `localhost` para que Apache lo alcance.
- [ ] El puerto 3306 (MariaDB/MySQL) **no** expuesto a internet — solo accesible desde `localhost`.
- [ ] Copias de seguridad programadas y probadas (restaurar una copia en un ambiente de prueba al
      menos una vez), y **cifradas** si se guardan fuera del servidor.
- [ ] `basedatos/exportaciones/` y cualquier copia de la base fuera del control de versiones.
- [ ] `GRANT` del usuario `c32sciseb` limitado a `SELECT, INSERT, UPDATE, DELETE` (sin privilegios de
      esquema: `CREATE`/`DROP`/`ALTER`/`GRANT`).
- [ ] `bind-address = 127.0.0.1` en MariaDB/MySQL, y `mysql_secure_installation` ejecutado.
- [ ] Firewall activo (`ufw`/`firewalld`): solo SSH y 80/443 abiertos desde afuera.
- [ ] SSH sin acceso de `root` y sin contraseñas (solo llave pública); `fail2ban` activo.
- [ ] Permisos `600` en `/opt/sciseb/sciseb.env` y dueño el usuario de sistema `sciseb` (sin shell,
      sin `sudo`).
- [ ] Plan para mantener el sistema operativo, Apache y las dependencias de `pom.xml` actualizados.
