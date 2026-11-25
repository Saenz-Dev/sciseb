<?php
/**
 * Puerto de CasoSpecs.java: construye el WHERE dinámico de GET /api/casos y /api/casos/exportar.
 * LEFT JOIN en toda la cadena geográfica porque un caso sin agresor/sede asignados todavía debe
 * seguir apareciendo en el listado (ver Caso.nombreAgresorBorrador).
 */
class Sci_CasoQuery
{
    const JOINS =
        'FROM caso c
         LEFT JOIN presunto_agresor ag ON c.id_agresor = ag.id_agresor
         LEFT JOIN sede se             ON c.id_sede = se.id_sede
         LEFT JOIN institucion_educativa ie ON se.id_institucion = ie.id_institucion
         LEFT JOIN municipio mu        ON ie.id_municipio = mu.id_municipio
         LEFT JOIN provincia pr        ON mu.id_provincia = pr.id_provincia
         LEFT JOIN clasificacion_siuce cl ON c.id_clasificacion = cl.id_clasificacion';

    private static function escaparLike($s)
    {
        return str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), $s);
    }

    /** @return array($whereSql, $params) — $whereSql ya incluye "WHERE ..." o es cadena vacía. */
    public static function build($filtro)
    {
        $condiciones = array();
        $params = array();

        $q = isset($filtro['q']) ? trim((string) $filtro['q']) : '';
        if ($q !== '') {
            $like = '%' . self::escaparLike(mb_strtolower($q, 'UTF-8')) . '%';
            $condiciones[] = "(LOWER(c.id_caso) LIKE ? ESCAPE '\\\\'
                OR LOWER(ag.nombre) LIKE ? ESCAPE '\\\\'
                OR LOWER(c.nombre_agresor_borrador) LIKE ? ESCAPE '\\\\'
                OR ag.cedula LIKE ? ESCAPE '\\\\'
                OR LOWER(ie.nombre) LIKE ? ESCAPE '\\\\'
                OR LOWER(mu.nombre) LIKE ? ESCAPE '\\\\'
                OR LOWER(c.n_radicado) LIKE ? ESCAPE '\\\\')";
            for ($i = 0; $i < 7; $i++) $params[] = $like;
        }

        if (!empty($filtro['provinciaId'])) {
            $condiciones[] = 'mu.id_provincia = ?';
            $params[] = (int) $filtro['provinciaId'];
        }
        if (!empty($filtro['clasificacionId'])) {
            $condiciones[] = 'c.id_clasificacion = ?';
            $params[] = (int) $filtro['clasificacionId'];
        }

        $ruta = isset($filtro['ruta']) ? strtolower((string) $filtro['ruta']) : '';
        if ($ruta === 'activa') {
            $condiciones[] = 'c.ruta_activa = 1';
        } elseif ($ruta === 'inactiva') {
            $condiciones[] = 'c.ruta_activa = 0';
        }

        if (!empty($filtro['borrador'])) {
            $condiciones[] = 'c.id_agresor IS NULL AND c.nombre_agresor_borrador IS NOT NULL';
        }

        $estado = isset($filtro['estado']) ? trim((string) $filtro['estado']) : '';
        $estado = $estado === '' ? 'ACTIVO' : strtoupper($estado);
        if ($estado !== 'TODOS') {
            $condiciones[] = 'c.estado = ?';
            $params[] = $estado === 'ARCHIVADO' ? 'ARCHIVADO' : 'ACTIVO';
        }

        $where = count($condiciones) > 0 ? ('WHERE ' . implode(' AND ', $condiciones)) : '';
        return array($where, $params);
    }
}
