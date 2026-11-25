<?php
/**
 * Puerto de AuditoriaService.java. Solo inserta — nunca hay ni habrá un camino de edición o
 * borrado (coincide con el REVOKE UPDATE/DELETE sobre esta tabla que exige basedatos/README.md
 * para el usuario de la app en el servidor MySQL 5.1 real, ya que ahí no hay triggers SIGNAL).
 * El "detalle" nunca debe llevar datos personales, solo ids y nombres de campo.
 */
class AuditoriaService
{
    public static function registrar($idUsuario, $idCaso, $accion, $detalle = null)
    {
        $pdo = Sci_Db::conexion();
        $stmt = $pdo->prepare(
            'INSERT INTO auditoria (id_usuario, id_caso, accion, detalle, fecha) VALUES (?, ?, ?, ?, NOW())'
        );
        $stmt->execute(array($idUsuario, $idCaso, $accion, $detalle));
    }

    /** Listado filtrable, paginado (tamaño máx. 100), para GET /api/auditoria (solo ADMIN). */
    public static function consultar($filtro)
    {
        $pdo = Sci_Db::conexion();

        $condiciones = array();
        $params = array();

        if (!empty($filtro['casoId'])) {
            $condiciones[] = 'au.id_caso = ?';
            $params[] = (int) $filtro['casoId'];
        }
        if (!empty($filtro['usuarioId'])) {
            $condiciones[] = 'au.id_usuario = ?';
            $params[] = (int) $filtro['usuarioId'];
        }
        if (!empty($filtro['desde'])) {
            $condiciones[] = 'au.fecha >= ?';
            $params[] = $filtro['desde'] . ' 00:00:00';
        }
        if (!empty($filtro['hasta'])) {
            $condiciones[] = 'au.fecha < DATE_ADD(?, INTERVAL 1 DAY)';
            $params[] = $filtro['hasta'] . ' 00:00:00';
        }

        $where = count($condiciones) > 0 ? ('WHERE ' . implode(' AND ', $condiciones)) : '';

        $tamano = max(1, min((int) (isset($filtro['tamano']) ? $filtro['tamano'] : 30), 100));
        $pagina = max(0, (int) (isset($filtro['pagina']) ? $filtro['pagina'] : 0));

        $stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM auditoria au $where");
        $stmtTotal->execute($params);
        $total = (int) $stmtTotal->fetchColumn();

        $sql = "SELECT au.id_auditoria, au.fecha, u.nombre AS usuario, ar.nombre AS area,
                       au.accion, au.id_caso, au.detalle
                  FROM auditoria au
                  JOIN usuario u ON u.id_usuario = au.id_usuario
                  JOIN area ar   ON ar.id_area   = u.id_area
                  $where
                 ORDER BY au.fecha DESC, au.id_auditoria DESC
                 LIMIT $tamano OFFSET " . ($pagina * $tamano);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $filas = $stmt->fetchAll();

        $contenido = array();
        foreach ($filas as $f) {
            $contenido[] = array(
                'fecha'   => $f['fecha'],
                'usuario' => $f['usuario'],
                'area'    => $f['area'],
                'accion'  => $f['accion'],
                'casoId'  => $f['id_caso'] !== null ? (int) $f['id_caso'] : null,
                'detalle' => $f['detalle'],
            );
        }

        return array(
            'contenido' => $contenido,
            'total'     => $total,
            'pagina'    => $pagina,
            'tamano'    => $tamano,
        );
    }
}
