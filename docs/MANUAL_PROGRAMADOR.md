# Manual del programador — SCI-SEB

Este manual explica cómo está construido el sistema por dentro, para quien vaya a mantenerlo,
corregirlo o ampliarlo.

## 1. Arquitectura general

| Capa | Tecnología |
|---|---|
| Backend | Java 21+ (probado con JDK 25) · Spring Boot 3.3 · Spring Data JPA / Hibernate · Spring Security (JWT + BCrypt) |
| Base de datos | MariaDB 10.6 o superior |
| Frontend | HTML + CSS + JavaScript plano, con jQuery 3.7.1 (servido localmente). Sin build, sin Node, sin framework |
| Archivos | Apache Commons CSV (importar/exportar CSV) · Apache POI (exportar Excel) |
| Autenticación | JWT (`io.jsonwebtoken`/jjwt), sin estado en el servidor (`SessionCreationPolicy.STATELESS`) |

Es una aplicación **monolítica de un solo proceso**: Spring Boot arranca un Tomcat embebido en el
puerto 8080 que sirve a la vez la API REST (`/api/**`) y el frontend estático
(`src/main/resources/static/`). No hay un backend y un frontend separados, ni un paso de compilación
del HTML/CSS/JS: basta con editar el archivo y recargar el navegador (en desarrollo) o volver a
construir el `.jar` (en producción).

No se usa ninguna herramienta de migración de esquema (ni Flyway ni Liquibase): el esquema se crea
una sola vez, a mano, con los scripts de `basedatos/`. Hibernate corre con
`ddl-auto: none` y nunca toca el esquema.

## 2. Estructura del repositorio

```
pom.xml
basedatos/
  01_esquema.sql              esquema completo (17 tablas, índices, triggers) — se corre UNA VEZ
  02_datos_iniciales.sql      catálogos fijos (roles, áreas, clasificación SIUCE, provincias, los 12 pasos)
  03_datos_demo_opcional.sql  instituciones/sedes ficticias, solo para ambientes de prueba
  exportaciones/              copias de la base real (mariadb-dump); no se versionan, ver su README
docs/
  MANUAL_USUARIO.md
  MANUAL_PROGRAMADOR.md       (este archivo)
  MANUAL_INSTALACION.md
src/main/java/com/sciseb/
  SciSebApplication.java      punto de entrada Spring Boot
  config/     seguridad (JwtFilter, JwtService, SecurityConfig), AppProperties (application.yml
              tipado), Sesion (record con los datos del usuario autenticado), AuditoriaConfig,
              ApiException (errores de negocio)
  domain/     entidades JPA (una clase por tabla)
  repo/       interfaces Spring Data JPA (una por entidad consultada directamente)
  service/    reglas de negocio — aquí vive casi toda la lógica
  web/        controladores REST (@RestController) y el manejador global de errores
  util/       CasoValidador: validación de campos de un caso, sin ninguna dependencia de Spring
              (se puede probar con JUnit puro y se reutiliza igual en el registro manual y en la
              importación CSV)
  dto/        Dto.java: todos los DTO de entrada/salida como `record` de Java (sin MapStruct; la
              conversión entidad → DTO se hace a mano en los servicios)
src/main/resources/
  application.yml             configuración (ver sección 5)
  static/                     el frontend completo (ver sección 6)
src/test/java/...             pruebas JUnit 5
```

## 3. Modelo de datos (resumen)

Ver el diagrama completo de tablas, columnas y relaciones directamente en `basedatos/01_esquema.sql`
(está comentado). Lo más importante para entender el código:

* **`caso`** es la tabla central. Sus relaciones con `presunto_agresor`, `sede` y
  `clasificacion_siuce` son **opcionales** (`id_agresor`, `id_sede`, `id_clasificacion` admiten
  `NULL`): un caso se puede registrar incompleto y completarse después editándolo.
* **`caso.nombre_agresor_borrador`**: cuando se escribe el nombre del presunto agresor pero no se
  conoce todavía su cédula, el nombre se guarda aquí en vez de crear un registro en
  `presunto_agresor` (esa tabla exige cédula `NOT NULL UNIQUE`). Ver `CasoService.persistirNuevo` /
  `actualizar` y la entidad `Caso.java` para el porqué. El DTO expone esto como
  `presuntoAgresor` (con el nombre) + `agresorBorrador: true`, para que el frontend lo muestre igual
  que un agresor normal pero con una insignia.
* **`presunto_agresor`** se reutiliza por cédula (`AgresorRepository.findByCedula`): si dos casos
  tienen la misma cédula, comparten el mismo registro de agresor (y por lo tanto su área/asignatura
  se actualiza para ambos). Si el nombre no coincide, `CasoService.resolverAgresor` lanza un
  conflicto (`NOMBRE_DISTINTO`) salvo que el cliente confirme explícitamente
  (`confirmarNombreDistinto`).
* **`auditoria`** tiene dos triggers (`trg_auditoria_sin_editar`, `trg_auditoria_sin_borrar`) que
  impiden `UPDATE`/`DELETE` a nivel de base de datos — no solo a nivel de aplicación.
* **Cédulas e iniciales llevan `CHECK` por expresión regular** directamente en el esquema
  (`REGEXP`), como segunda línea de defensa además de la validación en Java/JS.

## 4. Capas del backend

### 4.1 `util.CasoValidador`

Clase estática, sin Spring, con las reglas de un caso (`validar(Datos, LocalDate)` →
`Map<String,String>` de campo→mensaje de error). Se usa igual desde:

* `CasoService.crear` / `actualizar` (registro manual).
* `ImportacionService` (cada fila del CSV se valida con las mismas reglas).

El frontend tiene una copia en JavaScript (`static/js/validadores.js`) que replica exactamente estas
reglas para dar error inmediato sin esperar al servidor — **pero el servidor siempre vuelve a
validar**; el JS es solo una comodidad, nunca la única barrera.

Reglas clave (todas sobre campos ya opcionales salvo que se diga lo contrario):

* Todo caso es válido sin ningún dato del presunto agresor.
* Si se da nombre y cédula, ambos deben tener formato válido.
* No hay más regla de "pareja obligatoria" entre nombre y cédula: pueden darse por separado (ver
  borradores, sección 3 de este manual y sección 3.3 del manual de usuario).

### 4.2 `service.CasoService`

El servicio más grande del sistema. Métodos a conocer:

| Método | Para qué |
|---|---|
| `listar` / `resumen` | listado paginado con filtros (`CasoSpecs`) |
| `obtener` / `detalle` | detalle completo de un caso |
| `crear` / `actualizar` | registro y edición; validan, resuelven el presunto agresor, y crean/actualizan la entidad |
| `persistirNuevo` | lógica compartida entre el registro manual y la importación CSV: crea el `Caso`, resuelve/crea el agresor, agrega víctimas y los 12 pasos pendientes |
| `resolverAgresor` | reutiliza por cédula o crea un `PresuntoAgresor`; lanza `NOMBRE_DISTINTO` si hace falta confirmar |
| `buscarAgresor` | autocompletado del formulario (`GET /api/agresores/buscar`): busca por cédula o por nombre |
| `cambiarRuta` / `actualizarPaso` | ruta de atención (12 pasos) |
| `archivar` / `reactivar` | cambia `estado` (pide motivo, queda en auditoría) |

`CasoSpecs` construye el `Specification<Caso>` de Criteria API para el listado: nótese el uso de
`JoinType.LEFT` en los `join` de `agresor` y de la cadena `sede → institución → municipio` — un
`INNER JOIN` (el valor por defecto) excluiría del listado cualquier caso sin esos datos asignados,
que es justamente lo que no queremos dado que esos campos son opcionales.

### 4.3 Seguridad (`config/`)

* **`JwtFilter`**: filtro que lee el header `Authorization: Bearer <token>`, valida el JWT, carga al
  usuario y lo deja en el contexto de seguridad como un `Sesion` (record con id, correo, nombre,
  área y rol) — es lo que se inyecta con `@AuthenticationPrincipal Sesion s` en los controladores.
  En cada petición autenticada, si al token le queda poco tiempo, el filtro agrega la cabecera
  `X-Token-Renovado` con un token nuevo (el frontend lo detecta y reemplaza el suyo).
* **`SecurityConfig`**: sin estado (`STATELESS`), CSRF desactivado (la API no usa cookies de
  sesión), CSP estricta (`default-src 'self'`, ver sección 6.3), HSTS activado. Los endpoints bajo
  `/api/**` requieren autenticación salvo `/api/auth/login`; los de administración
  (`/api/usuarios/**`, `/api/auditoria`, catálogos de escritura) exigen además
  `@PreAuthorize("hasRole('ADMIN')")`.
* **Claves**: BCrypt con factor de costo 10. Las claves temporales se generan al azar y obligan a
  cambiarla en el primer ingreso (`EstadoUsuario.PENDIENTE_CAMBIO_CLAVE`).

### 4.4 Errores (`config.ApiException` + `web.ApiExceptionHandler`)

Todos los errores de negocio se lanzan como `ApiException` con un **código** corto (por ejemplo
`VALIDACION`, `NOMBRE_DISTINTO`, `CASO_ARCHIVADO`, `CONFLICTO_VERSION`) y un mensaje para mostrar al
usuario. `ApiExceptionHandler` los convierte a la respuesta JSON uniforme `ErrorRes(codigo, mensaje,
errores)`, donde `errores` es el mapa campo→mensaje cuando aplica (errores de validación). El
frontend (`api.js`) arma una `ApiError` con esos mismos tres datos y cada pantalla decide qué hacer
según el `codigo` (por ejemplo, el formulario de caso resalta en rojo los campos que vienen en
`errores`).

### 4.5 Control de concurrencia optimista

`Caso.version` es un `@Version` de JPA. Además, `CasoService.actualizar` exige que el `version` que
mande el cliente coincida con el de la base antes de aplicar los cambios (si no, `CONFLICTO_VERSION`
— "otra persona modificó este caso mientras usted lo editaba"). Esto evita que dos personas
sobrescriban sin darse cuenta los cambios de la otra.

## 5. Configuración (`application.yml`)

Todo lo configurable se expone como variable de entorno con un valor por defecto para desarrollo
(ver el archivo completo y el "Manual de instalación" para la lista de variables en producción).
Se tipa con `AppProperties` (`@ConfigurationProperties(prefix = "app")`), así que un error de
tipeo en el YAML falla al arrancar, no en tiempo de ejecución.

## 6. Frontend

### 6.1 Principio general

Cada pantalla es un `.html` suelto en `static/`, sin plantillas de servidor (no hay Thymeleaf ni
JSP): el HTML ya trae el markup final y JavaScript lo llena con los datos que pide a la API.

| Archivo | Pantalla |
|---|---|
| `index.html` | ingreso |
| `cambiar-clave.html` | cambio de clave obligatorio |
| `panel.html` | indicadores y últimos casos |
| `casos.html` | listado, filtros, exportar |
| `caso-form.html` | registrar/editar caso (`?id=` para editar) |
| `caso-detalle.html` | datos, ruta de atención, acciones, evidencias (`?id=`) |
| `importar.html` | importación CSV |
| `usuarios.html` | administración de usuarios |
| `catalogos.html` | áreas, municipios, instituciones, sedes |
| `auditoria.html` | consulta de auditoría |

### 6.2 JavaScript compartido (`static/js/`)

* **`sesion.js`**: guarda el token en `sessionStorage`, expone `Sesion.token`/`Sesion.esAdmin`,
  cierra la sesión y redirige a `index.html` cuando el token expira o el servidor responde 401.
* **`api.js`**: un único cliente (`Api.get/post/put/patch/del/subir/descargar`) sobre `$.ajax`;
  arma la URL con query params, agrega el header `Authorization`, detecta `X-Token-Renovado`, y
  convierte cualquier respuesta de error en una `ApiError` uniforme.
* **`ui.js`**: utilidades de interfaz compartidas (menú superior según el rol, avisos tipo "toast",
  diálogos de confirmación reutilizables).
* **`formato.js`**: formateo de fechas/horas para mostrar.
* **`validadores.js`**: copia en JS de `CasoValidador` (ver 4.1).
* **`catalogos.js`**: carga y llena los `<select>` en cascada (provincia → municipio → institución →
  sede).
* **`pagina-<nombre>.js`**: la lógica propia de cada pantalla (una por cada `.html`); es donde está
  todo el código específico de esa pantalla, para no mezclar todo en un solo archivo gigante.

### 6.3 Content-Security-Policy y por qué jQuery está vendorizado

El backend envía `Content-Security-Policy: default-src 'self'; ...` (ver `SecurityConfig`) a
propósito, por tratarse de datos sensibles. Esto bloquea scripts cargados desde un CDN externo y
cualquier `<script>` inline. Por eso:

* `static/js/jquery.min.js` es una copia local de jQuery, **no** se carga desde `cdnjs` ni ningún
  otro CDN.
* Ninguna página tiene JavaScript inline: toda la lógica está en archivos `.js` externos
  (`pagina-*.js`).

Si en el futuro hace falta permitir algo nuevo (por ejemplo un mapa externo), **no** se debe
debilitar la CSP sin antes evaluar el riesgo — es una decisión de seguridad explícita, no un
descuido.

### 6.4 Estilos compartidos (`css/estilos.css`)

Un solo archivo CSS para todo el sitio, con variables (`:root`) para colores. Dos reglas globales
importantes que corrigieron bugs reales, para no repetirlos:

* `[hidden] { display: none !important; }` — sin esto, cualquier clase que fije su propio
  `display` (como `.badge`) le gana en el cascado CSS al atributo HTML `hidden`, y el elemento se ve
  aunque esté "oculto". Si agrega una clase nueva con `display`, verifique que no tape ningún
  elemento que algún JS oculte con `.prop('hidden', true)`.
* `scrollbar-gutter: stable` (con `overflow-y: auto`, no `scroll`) en `html` — reserva siempre el
  espacio de la barra de desplazamiento para que la página no se corra horizontalmente al cambiar
  entre pestañas con contenido de distinto alto (por ejemplo, Ruta de atención vs. Acciones en el
  detalle de un caso).

## 7. Pruebas

```bash
mvn test     # o mvn verify
```

* `CasoValidadorTest`: reglas de validación puras (sin Spring).
* `UsuarioServiceTest` y similares: lógica de servicios con mocks donde aplica.
* `CasoServiceCamposOpcionalesTest` (`@SpringBootTest` + `@Transactional`): prueba contra la base de
  datos **real** configurada en `application.yml` — por eso hace falta tener MariaDB corriendo para
  ejecutar `mvn test`. Cada prueba corre dentro de una transacción que Spring revierte sola al
  terminar, así que no deja datos de prueba en la base.

No hay pruebas de integración con Testcontainers ni pruebas automáticas del frontend (HTML/JS): las
pantallas se prueban a mano en el navegador.

## 8. Cómo agregar algo nuevo (guía rápida)

**Un campo nuevo en `caso`:**
1. `ALTER TABLE` en una base existente (o editar `basedatos/01_esquema.sql` si es para instalaciones
   nuevas) — no hay migración automática.
2. Agregar el campo a la entidad `Caso.java`.
3. Agregarlo a `CasoReq`, `CasoResumen`/`CasoDetalle` en `Dto.java` según dónde deba aparecer.
4. Si necesita validación, agregarla a `CasoValidador.Datos`/`validar` **y** a `validadores.js`.
5. Agregarlo al formulario (`caso-form.html` + `pagina-caso-form.js`) y al detalle
   (`caso-detalle.html` + `pagina-caso-detalle.js`) si corresponde.

**Un endpoint nuevo:** agréguelo al controlador correspondiente en `web/`, con el DTO de
entrada/salida en `Dto.java` y la lógica en el `service/` correspondiente — los controladores no
deben tener lógica de negocio, solo traducir HTTP ↔ servicio.

**Un filtro nuevo en el listado de casos:** agregar el campo a `CasoFiltro` (record), el predicado
correspondiente en `CasoSpecs.desde`, el parámetro en `CasoController.listar`/`exportar`, y el
control correspondiente en `casos.html`/`pagina-casos.js`.
