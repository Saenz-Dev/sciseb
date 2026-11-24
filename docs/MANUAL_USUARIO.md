# Manual de usuario — SCI-SEB

Sistema de Seguimiento de Casos Internos Tipo II y III reportados en el SIUCE, para la Secretaría
de Educación de Boyacá.

## 1. Ingreso al sistema

1. Abra el navegador en la dirección que le indique el administrador del servidor (por ejemplo
   `http://sciseb.sedboyaca.gov.co` o `http://localhost:8080` si lo está probando localmente).
2. Escriba su **correo** y **clave** y pulse **Ingresar**.
3. **Primer ingreso:** el sistema lo obliga a cambiar la clave temporal antes de dejarlo continuar.
   La nueva clave debe tener mínimo 10 caracteres, con al menos una mayúscula, una minúscula y un
   número.
4. La sesión se cierra sola después de **30 minutos sin actividad** (vuelve a pedir el ingreso). Si
   cierra la pestaña del navegador, también se cierra la sesión.

### Roles

| Rol | Puede |
|---|---|
| **Usuario** | Ver el panel, registrar/editar/consultar casos, ver su ruta de atención, registrar acciones y enlaces de evidencia, importar y exportar casos |
| **Administrador** | Todo lo anterior, además: crear y administrar usuarios, editar los catálogos (áreas, municipios, instituciones, sedes), archivar/reactivar casos, consultar la auditoría, y ver/exportar la cédula completa (los demás roles la ven enmascarada, por ejemplo `*****289`) |

## 2. Panel principal

Al ingresar ve el **Panel**, con:

* Total de casos activos, cuántos tienen la ruta de atención activa, cuántos son Tipo I y cuántos
  Tipo III, y cuántos ya completaron los 12 pasos de la ruta.
* Los casos editados más recientemente, con acceso directo a su detalle.

## 3. Casos

### 3.1 Listado (pantalla "Casos")

* **Buscar**: por ID del caso, nombre del presunto agresor (incluye los que están en borrador),
  cédula, institución, municipio o número de radicado.
* **Filtros**: provincia, clasificación SIUCE, ruta (activa/inactiva), estado (activos/archivados/
  todos) y **"Presunto agresor: solo borradores"** (ver sección 3.3).
* Clic en cualquier fila abre el **detalle** del caso.
* **Exportar** (botones arriba a la derecha): descarga el listado filtrado en CSV o Excel. El
  Administrador exporta la cédula completa; los demás roles la reciben enmascarada. Máximo 5.000
  casos por archivo — si hay más, aplique más filtros.

### 3.2 Registrar o editar un caso

Pulse **Registrar caso** (o **Editar datos** desde el detalle de uno existente). El formulario está
dividido en secciones: Identificación, Presunto agresor y presuntas víctimas, Institución educativa,
Ocurrencia y Proceso.

**Todos los campos son opcionales.** Puede registrar el caso con los datos que tenga en el momento
y completarlo después editándolo — por ejemplo, si todavía no conoce la institución, la sede o la
clasificación SIUCE, déjelas en blanco.

Reglas a tener en cuenta:

* **ID del caso**: 5 números (por ejemplo `00123`). Si lo deja en blanco, puede asignarlo después.
* **Cédula del presunto agresor**: si ya existe un presunto agresor registrado con esa cédula, el
  **nombre se completa solo** al salir del campo (y viceversa: si escribe primero el nombre y ya
  existe, se completa la cédula). Si escribe un nombre pero no conoce la cédula todavía, el caso se
  guarda igual, con ese nombre marcado como **"Borrador"** — vea la sección 3.3.
* **Presuntas víctimas**: solo **iniciales** (por ejemplo `J.P.`), nunca el nombre completo. Puede
  agregar varias con el botón **"+ Agregar víctima"**.
* **Cédula repetida con otro nombre**: si la cédula ya está registrada a nombre de otra persona, el
  sistema se lo advierte y le pide confirmar que es la misma persona antes de continuar (por
  ejemplo, si el nombre se escribió distinto por un error de tildes o apellidos).
* Al guardar un caso nuevo se crean automáticamente los **12 pasos pendientes** de la ruta de
  atención.

### 3.3 Casos en borrador

Si al registrar o editar un caso escribe el nombre del presunto agresor pero **no** su cédula, el
caso se guarda igual — no se pierde el dato — pero queda marcado como **"Borrador"**:

* Aparece una insignia naranja **"Borrador"** junto al nombre, tanto en el listado como en el
  detalle del caso.
* Para ver solo estos casos pendientes de completar, use el filtro **"Presunto agresor → Solo
  borradores (sin cédula)"** en la pantalla Casos.
* En cuanto edite el caso y escriba la cédula, el borrador se resuelve solo: si esa cédula ya existe
  en el sistema, se vincula a esa persona; si es nueva, se crea el registro completo con el nombre
  que ya tenía.

### 3.4 Detalle del caso

El detalle tiene 4 pestañas:

* **Datos del caso**: todos los campos registrados, quién lo creó/editó y cuándo.
* **Ruta de atención**: los 12 pasos del protocolo (Atención Inmediata en Salud, Notificación al
  Rector, Comunicación a Padres y Acudientes, Aviso a la Policía de Infancia y Adolescencia,
  Restablecimiento de Derechos, Denuncia ante la Fiscalía, Reporte a Control Interno Disciplinario,
  Intervención del Comité Escolar de Convivencia, Acciones de Prevención y Mitigación, Medidas de
  Protección y No Revictimización, Reporte en SIUCE y Autoridades Educativas, Seguimiento Final).
  Marque cada paso como efectuado a medida que se completa; puede agregarle una nota. Queda
  registrado quién lo marcó y cuándo.
* **Acciones**: bitácora de lo que cada área hizo con el caso (fecha y hora). Solo quien escribió
  una acción puede editarla después (queda marcada la fecha de modificación).
* **Evidencias**: solo **enlaces `https://`** a la carpeta institucional donde están guardados los
  documentos — el sistema **no** almacena archivos, por ser información sensible.

**Archivar un caso**: el Administrador puede archivarlo (pide un motivo, mínimo 5 caracteres). Un
caso archivado no se puede editar hasta que se reactive (también con motivo). Archivar **no borra**
el caso: solo lo saca de los indicadores y del listado por defecto (cámbielo con el filtro Estado).

## 4. Importar casos desde CSV

1. En **Importar CSV**, descargue primero la **plantilla** para tener las columnas correctas.
2. Llénela en Excel o cualquier editor de hojas de cálculo (separador `,` o `;`, UTF‑8 o
   Windows‑1252) y guárdela como `.csv`. Máximo 5 MB y 2.000 filas por archivo.
3. Súbala: el sistema muestra una **vista previa fila por fila**, marcando cuáles son válidas y
   cuáles se rechazarían y por qué (sin mostrar datos personales en el detalle del error).
4. Confirme: se importa **todo o nada** — si hay errores, corrija el archivo y vuelva a intentar.
5. El archivo en sí **no se guarda** en el servidor; solo los casos que confirme.

## 5. Catálogos (Administrador)

Desde **Catálogos**, el Administrador administra:

* **Áreas**: los 4 nombres de referencia (se pueden renombrar, no se crean ni se borran).
* **Municipios, instituciones educativas y sedes**: se agregan en cascada (provincia → municipio →
  institución → sede) y se pueden renombrar. No se usan datos ficticios en producción: los carga el
  Administrador según la realidad del departamento.

## 6. Usuarios (Administrador)

* **Crear usuario**: nombre, correo, área y rol. El sistema genera una **clave temporal** que se le
  debe entregar a la persona; en su primer ingreso el sistema la obliga a cambiarla.
* **Restablecer clave**: genera una nueva clave temporal para alguien que la olvidó.
* **Cambiar estado**: Activo, Bloqueado o Inactivo. Un usuario reactivado conserva su clave anterior
  y debe cambiarla en el siguiente ingreso.

## 7. Auditoría (Administrador)

Registro de **todo** lo que ocurre en el sistema (quién, qué, cuándo, y sobre qué caso si aplica).
Se puede filtrar por caso, usuario y rango de fechas. **No se puede editar ni borrar** ningún
registro de auditoría — es una garantía de trazabilidad para procesos disciplinarios.

## 8. Buenas prácticas y datos sensibles

* La información de menores y de procesos disciplinarios/penales es **confidencial** (Ley 1581 de
  2012, protección de datos personales). No comparta capturas de pantalla, exporte solo lo
  necesario, y cierre la sesión al terminar (o espere a que expire sola).
* Use **iniciales**, nunca nombres completos, al registrar víctimas.
* Los enlaces de evidencia deben apuntar solo a la carpeta institucional con acceso restringido —
  nunca a un lugar de acceso público.
