-- SCI-SEB (rama migracion-php) · Tabla nueva, solo para el backend PHP.
--
-- La app Java guardaba la vista previa de importación CSV en memoria del propio proceso
-- (un ConcurrentHashMap con TTL de 15 minutos). En PHP cada solicitud es un proceso nuevo, así
-- que ese estado no sobrevive entre la llamada de vista previa y la de confirmación — por eso
-- se persiste aquí, con el mismo contrato: un token de un solo uso, dueño (usuario), vencimiento,
-- y se purga perezosamente en cada llamada de vista-previa/confirmar/cancelar.
--
-- Compatible con MySQL 5.1 (el mismo criterio que basedatos/01_esquema_mysql51.sql): sin CHECK
-- ni triggers SIGNAL.

CREATE TABLE import_preview (
    token             VARCHAR(36) PRIMARY KEY,
    id_usuario        INT NOT NULL REFERENCES usuario (id_usuario) ON DELETE RESTRICT,
    archivo           VARCHAR(150) NOT NULL,
    total             INT NOT NULL,
    rechazadas_count  INT NOT NULL,
    rechazadas_json   LONGTEXT NOT NULL,
    pendientes_json   LONGTEXT NOT NULL,
    fecha_expira      DATETIME NOT NULL
) ENGINE=InnoDB;

CREATE INDEX idx_import_preview_expira ON import_preview (fecha_expira);
