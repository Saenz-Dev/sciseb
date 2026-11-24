-- Catálogos fijos del sistema

INSERT INTO rol (nombre) VALUES ('ADMIN'), ('USUARIO');

-- Nombres de referencia: el Administrador puede cambiarlos desde la pantalla Catálogos
INSERT INTO area (nombre) VALUES
    ('Convivencia Escolar'),
    ('Inspección y Vigilancia'),
    ('Control Interno Disciplinario'),
    ('Oficina Jurídica');

INSERT INTO clasificacion_siuce (nombre) VALUES ('Tipo I'), ('Tipo II'), ('Tipo III');

INSERT INTO provincia (nombre) VALUES
    ('Centro'), ('Cubará'), ('Gutiérrez'), ('Norte'), ('Valderrama'),
    ('La Libertad'), ('Tundama'), ('Sugamuxi'), ('Lengupá'), ('Neira'),
    ('Oriente'), ('Márquez'), ('Ricaurte'), ('Occidente'), ('Puerto Boyacá');

-- Los 12 pasos del protocolo de ruta de atención
INSERT INTO paso_ruta (id_paso, nombre) VALUES
    (1,  'Atención Inmediata en Salud'),
    (2,  'Notificación al Rector'),
    (3,  'Comunicación a Padres y Acudientes'),
    (4,  'Aviso a la Policía de Infancia y Adolescencia'),
    (5,  'Restablecimiento de Derechos (Autoridades Administrativas)'),
    (6,  'Denuncia ante la Fiscalía General'),
    (7,  'Reporte a Control Interno Disciplinario'),
    (8,  'Intervención del Comité Escolar de Convivencia'),
    (9,  'Acciones de Prevención y Mitigación'),
    (10, 'Medidas de Protección y No Revictimización'),
    (11, 'Reporte en SIUCE y Autoridades Educativas'),
    (12, 'Seguimiento Final');
