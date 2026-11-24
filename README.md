# SCI-SEB · Seguimiento de Casos Tipo II y III (SIUCE)

Aplicación web para la Secretaría de Educación de Boyacá que implementa el *Documento de Análisis y Diseño* (DAD):
base principal de casos, ruta de atención de 12 pasos, historial de acciones, enlaces de evidencia (sin subir
archivos), importación desde CSV, exportación a CSV/Excel, usuarios por área y auditoría inmutable.

| Capa | Tecnología |
|---|---|
| Backend | Java 25 · Spring Boot 3.3 · Spring Data JPA / Hibernate · Spring Security (JWT + BCrypt) |
| Base de datos | MariaDB 10.6 o superior |
| Frontend | HTML + CSS + JavaScript plano, con jQuery para las llamadas a la API y el DOM (sin build, sin Node) |
| Archivos | Apache Commons CSV (importar/exportar CSV) · Apache POI (Excel) |

El frontend son páginas HTML sueltas en `src/main/resources/static/`: Spring Boot las sirve directamente como
archivos estáticos, sin paso de compilación. Para cambiar algo en la interfaz basta con editar el `.html` o el
`.js` correspondiente y recargar el navegador.

## Manuales

* [Manual de usuario](docs/MANUAL_USUARIO.md) — cómo usar cada pantalla.
* [Manual del programador](docs/MANUAL_PROGRAMADOR.md) — arquitectura, estructura del código, cómo agregar algo nuevo.
* [Manual de instalación](docs/MANUAL_INSTALACION.md) — instalación paso a paso en un servidor Linux con Apache y MySQL/MariaDB, incluida la puesta en marcha de la base de datos y copias de seguridad.

## Estado de verificación (léase primero)

| Parte | Verificado | Cómo |
|---|---|---|
| Scripts SQL (`basedatos/01`, `02`, `03`) | Sí | Se ejecutaron contra una MariaDB real (`mysql ... < basedatos/01_esquema.sql`, etc.); las 17 tablas y los catálogos (roles, áreas, provincias, pasos) quedaron cargados correctamente |
| Arranque del backend (`mvn spring-boot:run`) | Sí | Compila con `mvn compile` y arranca sin errores contra MariaDB (Hikari conecta, Hibernate valida el mapeo, Tomcat sirve en el puerto 8080) |
| Login (`POST /api/auth/login`) | Sí | Probado con el administrador inicial (`admin@sciseb.local` / `Cambiar123*`): responde 200 con el token JWT y los datos del usuario leídos de MariaDB |
| Páginas estáticas (`index.html`, `panel.html`, `casos.html`, CSS, JS) | Parcial | Se confirmó que Spring Boot las sirve (HTTP 200) y cargan sus `.css`/`.js`; **no** se probó el flujo completo clic a clic en un navegador real |
| `CasoValidador` (reglas de los campos, Java) | Sí (heredado) | Compilado con `javac` y probado con casos válidos e inválidos en una verificación anterior; no se tocó su lógica |
| Validaciones JS del formulario (`static/js/validadores.js`) | **No** | Son la traducción directa de `CasoValidador.java`; revisadas a mano, no probadas interactuando con el formulario |
| Resto de las pantallas y flujos (casos, ruta de atención, importar CSV, catálogos, usuarios, auditoría) | **No** | El backend que las sirve ya quedó probado (login), pero no se hizo clic a clic en cada pantalla |
| Pruebas JUnit incluidas | **No se ejecutaron** | Están listas para correr con `mvn verify` |

**Primer paso al recibirlo:** abra `http://localhost:8080`, inicie sesión con `admin@sciseb.local` / `Cambiar123*`
y recorra cada pantalla. Si algo no funciona, envíe el mensaje de error tal cual (o lo que ve en pantalla) y se
corrige.

## Requisitos

* JDK 25 y Maven 3.9 o superior
* MariaDB 10.6 o superior (instalado localmente, por ejemplo con [XAMPP](https://www.apachefriends.org/),
  [Laragon](https://laragon.org/) o el instalador oficial de MariaDB)
* Un navegador moderno (el frontend no necesita Node ni ninguna herramienta adicional)

## Puesta en marcha

El esquema **ya no se crea solo** (no hay Flyway ni ninguna otra herramienta de migración): se crea con un
script SQL que se ejecuta una sola vez, a mano. Así es más fácil de entender qué tablas existen y de dónde
salieron.

```bash
# 1. Cree la base de datos y el usuario en MariaDB (una sola vez)
mysql -u root -p -e "
  CREATE DATABASE sciseb CHARACTER SET utf8mb4;
  CREATE USER 'sciseb'@'localhost' IDENTIFIED BY 'sciseb';
  GRANT ALL PRIVILEGES ON sciseb.* TO 'sciseb'@'localhost';
"

# 2. Cree las tablas y cargue los catálogos (una sola vez; ver basedatos/README.md para más detalle)
mysql -u root -p sciseb < basedatos/01_esquema.sql
mysql -u root -p sciseb < basedatos/02_datos_iniciales.sql

# 3. Arranque el backend: se conecta a las tablas ya creadas y sirve el frontend estático
mvn spring-boot:run
```

Abra **http://localhost:8080** en el navegador: ahí está todo (API + páginas HTML), no hay un segundo servidor
que iniciar ni un paso de compilación del frontend.

* Primer ingreso: **admin@sciseb.local** con la clave **Cambiar123\*** (el sistema obliga a cambiarla de inmediato).
  Cámbiela por variables de entorno antes del primer arranque (`ADMIN_CORREO`, `ADMIN_CLAVE`).
* Para cargar instituciones y sedes **ficticias** de ejemplo, ejecute también
  `mysql -u root -p sciseb < basedatos/03_datos_demo_opcional.sql`. En producción el Administrador registra los
  catálogos reales desde la pantalla *Catálogos* y este paso no se ejecuta.
* Si usa XAMPP/Laragon, inicie el servicio de MariaDB desde su panel antes del paso 1; el puerto por defecto
  (3306) y el usuario/clave de arriba ya coinciden con los valores por defecto de `application.yml`.

## Variables de entorno

| Variable | Para qué sirve | Valor por defecto |
|---|---|---|
| `DB_URL`, `DB_USER`, `DB_PASSWORD` | Conexión a MariaDB | `jdbc:mariadb://localhost:3306/sciseb`, `sciseb`, `sciseb` |
| `JWT_SECRET` | Firma de las sesiones (mínimo 32 caracteres). **Cámbiela en producción** | valor de desarrollo |
| `CORS_ORIGENES` | Orígenes permitidos, separados por coma (normalmente no hace falta: el frontend se sirve desde el mismo origen) | `http://localhost:8080` |
| `ADMIN_CORREO`, `ADMIN_CLAVE` | Administrador inicial (solo si no hay usuarios) | `admin@sciseb.local`, `Cambiar123*` |

## Qué hace

* **Roles:** Administrador (usuarios, catálogos, auditoría, archivar/reactivar casos, exportar cédula completa) y Usuario.
  Cada usuario pertenece a una de las 4 áreas; los nombres de las áreas son de referencia y el Administrador puede renombrarlos.
* **Casos:** ID de 5 números, presunto agresor (reutilizado por cédula), víctima solo con iniciales, institución/sede/municipio/provincia,
  clasificación Tipo II/III, ruta activa/inactiva y los demás campos del DAD. Se guarda quién creó y quién editó por última vez, y cuándo.
  Los casos no se borran: se **archivan**.
* **Ruta de atención:** 12 pasos creados como pendientes con cada caso; al marcarlos queda el área, la fecha y la hora, y admiten una nota.
* **Acciones:** cada área registra lo que hizo con fecha y hora; solo el área autora puede editarla (se guarda la fecha de modificación).
* **Evidencias:** solo **enlaces https** a la carpeta institucional; el sistema no almacena documentos (son datos sensibles).
* **Importar CSV:** plantilla descargable → vista previa fila por fila → confirmación (todo o nada) → informe de filas rechazadas sin datos personales.
  El archivo no se guarda; hasta 5 MB y 2.000 filas; acepta `,` o `;`, UTF-8 o Windows-1252.
* **Exportar:** CSV (con `;` y tildes legibles en Excel) o Excel, con los filtros activos; hasta 5.000 casos.
* **Auditoría:** cada operación queda registrada; la base de datos impide modificar o borrar esos registros.

## Estructura

```
pom.xml
basedatos/      scripts SQL para crear la base a mano (01 esquema, 02 catálogos, 03 demo opcional)
src/main/java/com/sciseb
  config/    seguridad (JWT, CORS), auditoría JPA, propiedades
  domain/    entidades JPA
  repo/      repositorios Spring Data
  service/   reglas de negocio (casos, importación, exportación, usuarios…)
  web/       controladores REST y manejo de errores
  util/      CasoValidador (reglas de validación sin dependencias)
src/main/resources
  application.yml
  static/       frontend: páginas HTML sueltas, servidas directamente (sin build)
    index.html            ingreso
    cambiar-clave.html    cambio de clave obligatorio
    panel.html            indicadores y últimos casos
    casos.html            listado, filtros, exportar
    caso-form.html        registrar / editar caso (?id=)
    caso-detalle.html     datos, ruta de 12 pasos, acciones, evidencias (?id=)
    importar.html         importación CSV (plantilla, vista previa, confirmación)
    usuarios.html         alta y administración de usuarios (Administrador)
    catalogos.html        áreas, municipios, instituciones, sedes (Administrador)
    auditoria.html        consulta de auditoría (Administrador)
    css/estilos.css       estilos compartidos
    js/jquery.min.js      jQuery, copiado localmente (no se carga desde un CDN externo)
    js/                   sesion.js (token y expiración), api.js (llamadas a la API con jQuery),
                           ui.js (menú, avisos, confirmaciones), formato.js, validadores.js, catalogos.js,
                           pagina-*.js (la lógica propia de cada pantalla, una por cada .html)
src/test/java   pruebas JUnit 5
```

## Pruebas

```bash
mvn verify          # JUnit: validador, política de claves temporales, validación de enlaces
```

Las páginas HTML no tienen pruebas automáticas: pruébelas a mano en el navegador siguiendo el flujo de cada
pantalla (ingreso → panel → casos → registrar/editar → ruta de atención → importar → catálogos/usuarios/auditoría
si es Administrador).

## Decisiones y diferencias con el DAD

* **Frontend sin framework ni build:** HTML, CSS y JavaScript plano, con jQuery solo para simplificar las
  llamadas AJAX y la manipulación del DOM. Cada pantalla es un archivo `.html` que carga su propio
  `js/pagina-<nombre>.js`; los demás archivos en `static/js/` son los que se comparten entre pantallas.
* **jQuery servido localmente, no desde un CDN, y sin JavaScript inline en el HTML:** el backend ya enviaba
  una cabecera `Content-Security-Policy` estricta (`default-src 'self'`) por seguridad (datos sensibles, Ley
  1581). En vez de debilitarla, se copió `jquery.min.js` a `static/js/` y se movió la lógica de cada pantalla
  de un `<script>` inline a su propio archivo `.js`, para que todo el frontend cumpla esa política tal como
  está.
* **Sesión guardada en `sessionStorage`** (se borra al cerrar la pestaña), igual que antes: el token JWT se
  renueva en cada petición a la API y la sesión vence a los 30 minutos sin uso.
* **Sin MapStruct:** los DTO son `record` y la conversión se hace a mano; es más simple y fácil de seguir.
* **ID del caso `VARCHAR(5)`** (no entero) para conservar ceros a la izquierda (`00123`).
* **Cédula enmascarada en listas y exportaciones** (solo últimos 3 dígitos); el detalle del caso la muestra completa y el Administrador la exporta completa.
* **Cédula repetida con otro nombre:** en el registro manual el sistema avisa y pide confirmar; en el CSV la fila se rechaza.
* **Enlace repetido:** el sistema pregunta antes de agregarlo de nuevo.
* **Reactivar un usuario** conserva su clave y lo obliga a cambiarla en el siguiente ingreso.
* **Sesión:** vence a los 30 minutos sin uso (el servidor entrega un token renovado en cada petición).
* **HTTPS:** el cifrado debe terminar en el servidor web o balanceador delante de la aplicación (Nginx, Apache, etc.); la aplicación envía la cabecera HSTS.
* **El trigger de auditoría inmutable** se escribió como dos triggers `SIGNAL` (uno `BEFORE UPDATE`, otro
  `BEFORE DELETE`) porque MariaDB, a diferencia de PostgreSQL, no admite un solo trigger para ambos eventos.
* **Sin Flyway ni otra herramienta de migración:** el esquema se crea con un script SQL plano
  (`basedatos/01_esquema.sql`) que se ejecuta una sola vez a mano. Es menos "mágico" y más fácil de leer para
  quien no conozca Flyway; a cambio, cambios futuros al esquema hay que aplicarlos manualmente (con `ALTER
  TABLE` o recreando la base) en vez de con una migración versionada.
* **Pruebas contra la base de datos real** (integración con Testcontainers) no se incluyeron.

## Seguridad y datos personales

La información de menores y de procesos disciplinarios y penales es confidencial (Ley 1581 de 2012). Antes de poner el sistema en producción:
cambie `JWT_SECRET` y la clave del administrador, sirva la aplicación solo por HTTPS, programe copias de seguridad diarias de MariaDB
y restrinja los enlaces de evidencia a las personas autorizadas.
