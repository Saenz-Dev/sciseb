-- Datos de ejemplo (instituciones ficticias). Solo con el perfil "demo".
INSERT INTO municipio (nombre, id_provincia) VALUES
    ('Tunja',          (SELECT id_provincia FROM provincia WHERE nombre = 'Centro')),
    ('Duitama',        (SELECT id_provincia FROM provincia WHERE nombre = 'Tundama')),
    ('Sogamoso',       (SELECT id_provincia FROM provincia WHERE nombre = 'Sugamuxi')),
    ('Soatá',          (SELECT id_provincia FROM provincia WHERE nombre = 'Norte')),
    ('Chiquinquirá',   (SELECT id_provincia FROM provincia WHERE nombre = 'Occidente')),
    ('Puerto Boyacá',  (SELECT id_provincia FROM provincia WHERE nombre = 'Puerto Boyacá'));

INSERT INTO institucion_educativa (nombre, id_municipio) VALUES
    ('IE Ejemplo Uno',    (SELECT id_municipio FROM municipio WHERE nombre = 'Tunja')),
    ('IE Ejemplo Dos',    (SELECT id_municipio FROM municipio WHERE nombre = 'Duitama')),
    ('IE Ejemplo Tres',   (SELECT id_municipio FROM municipio WHERE nombre = 'Sogamoso')),
    ('IE Ejemplo Cuatro', (SELECT id_municipio FROM municipio WHERE nombre = 'Soatá'));

INSERT INTO sede (nombre, id_institucion) VALUES
    ('Sede Principal', (SELECT id_institucion FROM institucion_educativa WHERE nombre = 'IE Ejemplo Uno')),
    ('Sede Rural',     (SELECT id_institucion FROM institucion_educativa WHERE nombre = 'IE Ejemplo Uno')),
    ('Sede Principal', (SELECT id_institucion FROM institucion_educativa WHERE nombre = 'IE Ejemplo Dos')),
    ('Sede Principal', (SELECT id_institucion FROM institucion_educativa WHERE nombre = 'IE Ejemplo Tres')),
    ('Sede Principal', (SELECT id_institucion FROM institucion_educativa WHERE nombre = 'IE Ejemplo Cuatro'));
