# Base de datos: puesta en marcha (sin herramienta de migración)

El proyecto ya **no usa Flyway**. Estos tres archivos son scripts SQL normales que se ejecutan **una sola vez,
a mano**, contra MariaDB. Spring Boot solo se conecta y usa las tablas que ya existen (`ddl-auto: none`).

## Orden de ejecución

1. `01_esquema.sql` — crea todas las tablas, llaves foráneas y los triggers de auditoría. **Obligatorio.**
2. `02_datos_iniciales.sql` — catálogos fijos: roles, áreas, clasificación Tipo I/II/III, las 15 provincias de
   Boyacá y los 12 pasos de la ruta de atención. **Obligatorio** (la aplicación no arranca bien sin esto).
3. `03_datos_demo_opcional.sql` — municipios, instituciones y sedes **ficticias** de ejemplo, solo para probar
   el sistema. **Opcional:** no lo ejecute en producción; en producción el Administrador registra los catálogos
   reales desde la pantalla *Catálogos*.

## Cómo ejecutarlos

### Opción A: con HeidiSQL, DBeaver, phpMyAdmin o similar (más fácil si no usa la terminal)

1. Conéctese a su servidor MariaDB (usuario `root` o el que tenga).
2. Cree la base de datos `c32sciseb` (cotejamiento `utf8mb4`).
3. Abra cada archivo `.sql` en ese orden y ejecútelo sobre la base `c32sciseb`.

### Opción B: por línea de comandos

```bash
# 1. Crear la base de datos y el usuario de la aplicación (una sola vez)
mysql -u root -p -e "
  CREATE DATABASE c32sciseb CHARACTER SET utf8mb4;
  CREATE USER 'c32sciseb'@'localhost' IDENTIFIED BY 'ESCRIBA_AQUI_UNA_CLAVE_FUERTE';
  -- Mínimo privilegio: la aplicación solo lee/escribe filas, nunca crea ni borra tablas
  -- (eso se hace a mano, como root, con los scripts de esta carpeta).
  GRANT SELECT, INSERT, UPDATE, DELETE ON c32sciseb.* TO 'c32sciseb'@'localhost';
  FLUSH PRIVILEGES;
"

# 2. Crear las tablas y cargar los catálogos
mysql -u root -p c32sciseb < basedatos/01_esquema.sql
mysql -u root -p c32sciseb < basedatos/02_datos_iniciales.sql

# 3. (Opcional) datos de ejemplo ficticios
mysql -u root -p c32sciseb < basedatos/03_datos_demo_opcional.sql
```

(Si usa el cliente `mariadb` en vez de `mysql`, el comando es idéntico, solo cambia el nombre del ejecutable.)

## Servidor con MySQL 5.1 (hospedajes muy antiguos)

Antes de nada, confirme la versión real del servidor (en phpMyAdmin: pestaña **SQL** → ejecute
`SELECT VERSION();`; por línea de comandos: `mysql -V` o `SELECT VERSION();`).

Si muestra **5.0.x o 5.1.x**, ese MySQL es de 2008-2009 y no soporta `SIGNAL SQLSTATE` (los
triggers de `01_esquema.sql`) ni `utf8mb4`. **Avise al proveedor de hospedaje y pida que lo
actualicen** — un MySQL sin parches de seguridad desde 2013 es un riesgo serio para un sistema con
datos sensibles (Ley 1581 de 2012). Mientras tanto, para poder trabajar:

1. Use `utf8` en vez de `utf8mb4` al crear la base:
   ```sql
   CREATE DATABASE c32sciseb CHARACTER SET utf8 COLLATE utf8_unicode_ci;
   CREATE USER 'c32sciseb'@'localhost' IDENTIFIED BY 'ESCRIBA_AQUI_UNA_CLAVE_FUERTE';
   GRANT SELECT, INSERT, UPDATE, DELETE ON c32sciseb.* TO 'c32sciseb'@'localhost';
   -- auditoria no se puede proteger con un trigger SIGNAL en MySQL 5.1 (necesita 5.5+), así que
   -- se protege quitándole a este mismo usuario el permiso de editarla o borrarla:
   REVOKE UPDATE, DELETE ON c32sciseb.auditoria FROM 'c32sciseb'@'localhost';
   FLUSH PRIVILEGES;
   ```
2. Use `basedatos/01_esquema_mysql51.sql` en vez de `basedatos/01_esquema.sql` (es igual, pero sin
   los dos triggers `SIGNAL`, que no se pueden crear en esta versión):
   ```bash
   mysql -u root -p c32sciseb < basedatos/01_esquema_mysql51.sql
   mysql -u root -p c32sciseb < basedatos/02_datos_iniciales.sql
   ```
3. Verifique que el `REVOKE` funcionó — conectado como el usuario `c32sciseb` (no como root), esto
   debe fallar con "command denied":
   ```sql
   UPDATE auditoria SET detalle = 'prueba' WHERE id_auditoria = 1;
   ```

## Si necesita empezar de nuevo

Como no hay Flyway llevando un historial de versiones, para reiniciar todo basta con borrar y recrear la base:

```sql
DROP DATABASE c32sciseb;
CREATE DATABASE c32sciseb CHARACTER SET utf8mb4;
```

y volver a ejecutar los pasos 2 y 3 de arriba.
