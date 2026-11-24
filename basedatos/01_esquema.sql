-- SCI-SEB · Esquema inicial (normalizado hasta 3FN, ver DAD sección 3.7)
-- Para MariaDB (10.6+). Este script se ejecuta UNA SOLA VEZ, a mano, para crear las tablas
-- (no se usa ninguna herramienta de migración como Flyway: es más fácil de entender así).
-- Ver basedatos/README.md para el paso a paso.

CREATE TABLE area (
    id_area  INT AUTO_INCREMENT PRIMARY KEY,
    nombre   VARCHAR(80) NOT NULL UNIQUE
);

CREATE TABLE rol (
    id_rol  INT AUTO_INCREMENT PRIMARY KEY,
    nombre  VARCHAR(30) NOT NULL UNIQUE
);

CREATE TABLE usuario (
    id_usuario         INT AUTO_INCREMENT PRIMARY KEY,
    nombre             VARCHAR(120) NOT NULL,
    correo             VARCHAR(120) NOT NULL UNIQUE,
    clave_hash         VARCHAR(100) NOT NULL,
    id_area            INT NOT NULL REFERENCES area (id_area) ON DELETE RESTRICT,
    id_rol             INT NOT NULL REFERENCES rol (id_rol) ON DELETE RESTRICT,
    estado             VARCHAR(30) NOT NULL DEFAULT 'PENDIENTE_CAMBIO_CLAVE'
                       CHECK (estado IN ('PENDIENTE_CAMBIO_CLAVE', 'ACTIVO', 'BLOQUEADO', 'INACTIVO')),
    intentos_fallidos  INT NOT NULL DEFAULT 0,
    ultimo_acceso      DATETIME
);

CREATE TABLE provincia (
    id_provincia  INT AUTO_INCREMENT PRIMARY KEY,
    nombre        VARCHAR(60) NOT NULL UNIQUE
);

CREATE TABLE municipio (
    id_municipio  INT AUTO_INCREMENT PRIMARY KEY,
    nombre        VARCHAR(80) NOT NULL,
    id_provincia  INT NOT NULL REFERENCES provincia (id_provincia) ON DELETE RESTRICT,
    UNIQUE (nombre, id_provincia)
);

CREATE TABLE institucion_educativa (
    id_institucion  INT AUTO_INCREMENT PRIMARY KEY,
    nombre          VARCHAR(150) NOT NULL,
    id_municipio    INT NOT NULL REFERENCES municipio (id_municipio) ON DELETE RESTRICT,
    UNIQUE (nombre, id_municipio)
);

CREATE TABLE sede (
    id_sede         INT AUTO_INCREMENT PRIMARY KEY,
    nombre          VARCHAR(150) NOT NULL,
    id_institucion  INT NOT NULL REFERENCES institucion_educativa (id_institucion) ON DELETE RESTRICT,
    UNIQUE (nombre, id_institucion)
);

CREATE TABLE clasificacion_siuce (
    id_clasificacion  INT AUTO_INCREMENT PRIMARY KEY,
    nombre            VARCHAR(20) NOT NULL UNIQUE CHECK (nombre IN ('Tipo I', 'Tipo II', 'Tipo III'))
);

CREATE TABLE presunto_agresor (
    id_agresor        INT AUTO_INCREMENT PRIMARY KEY,
    nombre            VARCHAR(120) NOT NULL,
    cedula            VARCHAR(12) NOT NULL UNIQUE CHECK (cedula REGEXP '^[0-9]{5,12}$'),
    area_asignatura   VARCHAR(80)
);

CREATE TABLE caso (
    id                    BIGINT AUTO_INCREMENT PRIMARY KEY,
    id_caso               VARCHAR(5) CHECK (id_caso IS NULL OR id_caso REGEXP '^[0-9]{5}$'),
    -- Opcionales: un caso se puede registrar primero y completarse (agresor, sede, clasificación) después.
    id_agresor            INT REFERENCES presunto_agresor (id_agresor) ON DELETE RESTRICT,
    -- Nombre del presunto agresor cuando aún no se conoce su cédula (ver comentario en Caso.java):
    -- queda aquí como borrador hasta que se complete la cédula y se pueda crear el presunto_agresor real.
    nombre_agresor_borrador VARCHAR(120),
    id_sede               INT REFERENCES sede (id_sede) ON DELETE RESTRICT,
    id_clasificacion      INT REFERENCES clasificacion_siuce (id_clasificacion) ON DELETE RESTRICT,
    ruta_activa           BOOLEAN NOT NULL DEFAULT FALSE,
    n_radicado            VARCHAR(30) CHECK (n_radicado REGEXP '^[A-Za-z0-9/_. -]*$'),
    resolucion_seb        VARCHAR(20) CHECK (resolucion_seb REGEXP '^[0-9]*$'),
    resolucion_rectoral   VARCHAR(20) CHECK (resolucion_rectoral REGEXP '^[0-9]*$'),
    ubicacion_agresor     TEXT,
    fecha_ocurrencia      DATE,
    lugar_ocurrencia      VARCHAR(120),
    disciplinario         VARCHAR(30) CHECK (disciplinario REGEXP '^[A-Za-z0-9/_. -]*$'),
    decision_comite       TEXT,
    penal                 VARCHAR(30) CHECK (penal REGEXP '^[0-9]*$'),
    estado                VARCHAR(15) NOT NULL DEFAULT 'ACTIVO' CHECK (estado IN ('ACTIVO', 'ARCHIVADO')),
    version               INT NOT NULL DEFAULT 0,
    creado_por            INT NOT NULL REFERENCES usuario (id_usuario) ON DELETE RESTRICT,
    fecha_creacion        DATETIME NOT NULL,
    editado_por           INT NOT NULL REFERENCES usuario (id_usuario) ON DELETE RESTRICT,
    fecha_ultima_edicion  DATETIME NOT NULL
);
-- A diferencia de Postgres, en MariaDB un UNIQUE KEY normal ya admite varios NULL
-- (no hace falta un índice parcial con WHERE, que MariaDB no soporta).
CREATE UNIQUE INDEX ux_caso_id_caso ON caso (id_caso);
CREATE INDEX idx_caso_agresor ON caso (id_agresor);
CREATE INDEX idx_caso_sede ON caso (id_sede);
CREATE INDEX idx_caso_estado ON caso (estado);
CREATE INDEX idx_caso_ultima_edicion ON caso (fecha_ultima_edicion DESC);

CREATE TABLE caso_victima (
    id_victima  BIGINT AUTO_INCREMENT PRIMARY KEY,
    id_caso     BIGINT NOT NULL REFERENCES caso (id) ON DELETE CASCADE,
    iniciales   VARCHAR(10) NOT NULL CHECK (iniciales REGEXP '^[A-ZÁÉÍÓÚÑ.]{1,10}$')
);
CREATE INDEX idx_caso_victima_caso ON caso_victima (id_caso);

CREATE TABLE paso_ruta (
    id_paso  INT PRIMARY KEY,
    nombre   VARCHAR(100) NOT NULL
);

CREATE TABLE caso_paso (
    id_caso          BIGINT NOT NULL REFERENCES caso (id) ON DELETE RESTRICT,
    id_paso          INT NOT NULL REFERENCES paso_ruta (id_paso) ON DELETE RESTRICT,
    efectuado        BOOLEAN NOT NULL DEFAULT FALSE,
    id_usuario       INT REFERENCES usuario (id_usuario) ON DELETE RESTRICT,
    fecha_efectuado  DATETIME,
    nota             TEXT,
    PRIMARY KEY (id_caso, id_paso)
);

CREATE TABLE accion_caso (
    id_accion           INT AUTO_INCREMENT PRIMARY KEY,
    id_caso             BIGINT NOT NULL REFERENCES caso (id) ON DELETE RESTRICT,
    id_usuario          INT NOT NULL REFERENCES usuario (id_usuario) ON DELETE RESTRICT,
    texto               TEXT NOT NULL CHECK (char_length(texto) BETWEEN 1 AND 2000),
    fecha_registro      DATETIME NOT NULL,
    fecha_modificacion  DATETIME
);
CREATE INDEX idx_accion_caso ON accion_caso (id_caso);

CREATE TABLE enlace_evidencia (
    id_enlace       INT AUTO_INCREMENT PRIMARY KEY,
    id_caso         BIGINT NOT NULL REFERENCES caso (id) ON DELETE RESTRICT,
    id_usuario      INT NOT NULL REFERENCES usuario (id_usuario) ON DELETE RESTRICT,
    titulo          VARCHAR(150) NOT NULL,
    url             VARCHAR(500) NOT NULL CHECK (url LIKE 'https://%'),
    activo          BOOLEAN NOT NULL DEFAULT TRUE,
    fecha_registro  DATETIME NOT NULL
);
CREATE INDEX idx_enlace_caso ON enlace_evidencia (id_caso);

CREATE TABLE auditoria (
    id_auditoria  BIGINT AUTO_INCREMENT PRIMARY KEY,
    id_usuario    INT NOT NULL REFERENCES usuario (id_usuario) ON DELETE RESTRICT,
    id_caso       BIGINT,
    accion        VARCHAR(40) NOT NULL,
    detalle       TEXT,
    fecha         DATETIME NOT NULL
);
CREATE INDEX idx_auditoria_fecha ON auditoria (fecha DESC);
CREATE INDEX idx_auditoria_caso ON auditoria (id_caso);

-- Los registros de auditoría no se editan ni se borran. MariaDB no permite un solo trigger
-- "BEFORE UPDATE OR DELETE" como Postgres: hace falta uno por evento. Cada uno es una sola
-- sentencia SIGNAL (sin bloque BEGIN/END ni DELIMITER) para que Flyway los parta por ";" sin
-- configuración adicional.
CREATE TRIGGER trg_auditoria_sin_editar
    BEFORE UPDATE ON auditoria
    FOR EACH ROW
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Los registros de auditoría no se pueden modificar ni eliminar';

CREATE TRIGGER trg_auditoria_sin_borrar
    BEFORE DELETE ON auditoria
    FOR EACH ROW
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Los registros de auditoría no se pueden modificar ni eliminar';

CREATE TABLE importacion_csv (
    id_importacion    INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario        INT NOT NULL REFERENCES usuario (id_usuario) ON DELETE RESTRICT,
    nombre_archivo    VARCHAR(150) NOT NULL,
    fecha             DATETIME NOT NULL,
    filas_total       INT NOT NULL,
    filas_importadas  INT NOT NULL,
    filas_rechazadas  INT NOT NULL,
    CHECK (filas_total = filas_importadas + filas_rechazadas)
);
