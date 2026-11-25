<?php
/**
 * Puerto de ExportacionService.java — solo CSV (se descartó .xlsx: sin Composer no hay forma
 * razonable de generar OOXML real sin escribir un lector/escritor de ZIP+XML a mano).
 */
class ExportacionService
{
    // PHP no admite const con valor array hasta la 5.6 — propiedad estática en vez de const.
    public static $ENCABEZADOS = array('ID del caso', 'Presunto agresor', 'Cédula', 'Presunta víctima',
        'Institución educativa', 'Sede', 'Área / asignatura', 'Municipio', 'Provincia',
        'Clasificación SIUCE', 'Ruta', 'N° de radicado', 'Resolución SEB', 'Resolución rectoral',
        'Ubicación', 'Fecha de ocurrencia', 'Lugar de ocurrencia', 'Disciplinario',
        'Decisión del comité', 'Penal', 'Estado', 'Pasos efectuados (de 12)', 'Creado por',
        'Fecha de creación', 'Editado por', 'Fecha de última edición');

    private static function n($s)
    {
        return $s === null ? '' : Sci_Validator::neutralizarFormula($s);
    }

    /** @return array('contenido'=>string, 'nombre'=>string, 'tipo'=>string) */
    public static function exportar($sesion, $filtro, $formato)
    {
        if (strcasecmp($formato, 'csv') !== 0) {
            throw Sci_Api_Exception::solicitudInvalida('El formato debe ser csv.');
        }
        $pdo = Sci_Db::conexion();
        list($where, $params) = Sci_CasoQuery::build($filtro);

        $stmtTotal = $pdo->prepare('SELECT COUNT(*) ' . Sci_CasoQuery::JOINS . ' ' . $where);
        $stmtTotal->execute($params);
        $total = (int) $stmtTotal->fetchColumn();
        $max = (int) Sci_Config::get('exportacion')['max_filas'];
        if ($total > $max) {
            throw new Sci_Api_Exception(422, 'DEMASIADOS_CASOS',
                "Hay $total casos con estos filtros y el máximo por archivo es $max. Aplique más filtros.");
        }

        $sql = 'SELECT c.*, ag.nombre AS ag_nombre, ag.cedula AS ag_cedula, ag.area_asignatura AS ag_area,
                       se.nombre AS se_nombre, ie.nombre AS ie_nombre, mu.nombre AS mu_nombre, pr.nombre AS pr_nombre,
                       cl.nombre AS cl_nombre, cre.nombre AS creado_por_nombre, edi.nombre AS editado_por_nombre,
                       (SELECT COUNT(*) FROM caso_paso cp WHERE cp.id_caso = c.id AND cp.efectuado = 1) AS pasos_efectuados
                  ' . Sci_CasoQuery::JOINS . "
                  JOIN usuario cre ON c.creado_por = cre.id_usuario
                  JOIN usuario edi ON c.editado_por = edi.id_usuario
                  $where
                 ORDER BY c.id ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $casosFilas = $stmt->fetchAll();

        $ids = array();
        foreach ($casosFilas as $f) $ids[] = (int) $f['id'];
        $victimasPorCaso = self::victimasPorCasos($ids);

        $filas = array();
        $esAdmin = Sci_Auth::esAdmin($sesion);
        foreach ($casosFilas as $c) {
            $tieneAgresor = $c['ag_nombre'] !== null;
            $cedula = $tieneAgresor ? ($esAdmin ? $c['ag_cedula'] : Sci_Validator::enmascararCedula($c['ag_cedula'])) : null;
            $victimas = isset($victimasPorCaso[(int) $c['id']]) ? implode('; ', $victimasPorCaso[(int) $c['id']]) : '';
            $filas[] = array(
                self::n($c['id_caso']),
                self::n($tieneAgresor ? $c['ag_nombre'] : $c['nombre_agresor_borrador']),
                self::n($cedula),
                $victimas,
                self::n($c['ie_nombre']),
                self::n($c['se_nombre']),
                self::n($tieneAgresor ? $c['ag_area'] : null),
                self::n($c['mu_nombre']),
                self::n($c['pr_nombre']),
                self::n($c['cl_nombre']),
                ((int) $c['ruta_activa']) ? 'Activa' : 'Inactiva',
                self::n($c['n_radicado']),
                self::n($c['resolucion_seb']),
                self::n($c['resolucion_rectoral']),
                self::n($c['ubicacion_agresor']),
                self::n($c['fecha_ocurrencia']),
                self::n($c['lugar_ocurrencia']),
                self::n($c['disciplinario']),
                self::n($c['decision_comite']),
                self::n($c['penal']),
                $c['estado'],
                (string) (int) $c['pasos_efectuados'],
                $c['creado_por_nombre'],
                self::formatoFecha($c['fecha_creacion']),
                $c['editado_por_nombre'],
                self::formatoFecha($c['fecha_ultima_edicion']),
            );
        }

        AuditoriaService::registrar($sesion['id'], null, 'EXPORTAR', count($filas) . ' casos, csv');

        $contenido = Sci_Csv::escribir(self::$ENCABEZADOS, $filas);
        $nombre = 'casos_siuce_' . date('Ymd') . '.csv';
        return array('contenido' => $contenido, 'nombre' => $nombre, 'tipo' => 'text/csv; charset=UTF-8');
    }

    private static function formatoFecha($fechaHoraMysql)
    {
        if ($fechaHoraMysql === null) return '';
        return substr($fechaHoraMysql, 0, 16); // "Y-m-d H:i:s" -> "Y-m-d H:i"
    }

    private static function victimasPorCasos($ids)
    {
        $resultado = array();
        if (count($ids) === 0) return $resultado;
        $pdo = Sci_Db::conexion();
        $marcadores = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT id_caso, iniciales FROM caso_victima WHERE id_caso IN ($marcadores) ORDER BY id_victima");
        $stmt->execute($ids);
        foreach ($stmt->fetchAll() as $f) {
            $idCaso = (int) $f['id_caso'];
            if (!isset($resultado[$idCaso])) $resultado[$idCaso] = array();
            $resultado[$idCaso][] = $f['iniciales'];
        }
        return $resultado;
    }
}
