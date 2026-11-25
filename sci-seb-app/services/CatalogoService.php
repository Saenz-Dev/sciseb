<?php
/**
 * Puerto de CatalogoService.java. CRUD jerárquico de geografía (provincia -> municipio ->
 * institución -> sede); provincia no es creable por la API (solo sembrada). Área solo se puede
 * renombrar. Unicidad de nombre siempre insensible a mayúsculas y acotada al padre (o global para
 * área). Cada mutación audita CREAR_CATALOGO/EDITAR_CATALOGO con solo el tipo+id, nunca el nombre.
 */
class CatalogoService
{
    public static function listar()
    {
        $pdo = Sci_Db::conexion();
        return array(
            'provincias'      => self::items($pdo, 'provincia', 'id_provincia', 'nombre'),
            'clasificaciones' => self::items($pdo, 'clasificacion_siuce', 'id_clasificacion', 'nombre'),
            'areas'           => self::items($pdo, 'area', 'id_area', 'nombre'),
            'pasos'           => self::items($pdo, 'paso_ruta', 'id_paso', 'nombre'),
        );
    }

    private static function items($pdo, $tabla, $colId, $colNombre)
    {
        $stmt = $pdo->query("SELECT $colId AS id, $colNombre AS nombre FROM $tabla ORDER BY $colNombre");
        $filas = $stmt->fetchAll();
        $resultado = array();
        foreach ($filas as $f) {
            $resultado[] = array('id' => (int) $f['id'], 'nombre' => $f['nombre']);
        }
        return $resultado;
    }

    public static function municipios($provinciaId)
    {
        return self::hijos('municipio', 'id_municipio', 'id_provincia', $provinciaId);
    }

    public static function instituciones($municipioId)
    {
        return self::hijos('institucion_educativa', 'id_institucion', 'id_municipio', $municipioId);
    }

    public static function sedes($institucionId)
    {
        return self::hijos('sede', 'id_sede', 'id_institucion', $institucionId);
    }

    private static function hijos($tabla, $colId, $colPadre, $padreId)
    {
        $pdo = Sci_Db::conexion();
        if (empty($padreId)) return array();
        $stmt = $pdo->prepare("SELECT $colId AS id, nombre, $colPadre AS padre FROM $tabla WHERE $colPadre = ? ORDER BY nombre");
        $stmt->execute(array((int) $padreId));
        $resultado = array();
        foreach ($stmt->fetchAll() as $f) {
            $resultado[] = array('id' => (int) $f['id'], 'nombre' => $f['nombre'], 'padreId' => (int) $f['padre']);
        }
        return $resultado;
    }

    private static function validarNombre($nombre, $max = 150)
    {
        $nombre = is_string($nombre) ? trim(preg_replace('/\s+/u', ' ', $nombre)) : '';
        if ($nombre === '' || mb_strlen($nombre, 'UTF-8') < 2 || mb_strlen($nombre, 'UTF-8') > $max) {
            throw Sci_Api_Exception::validacion(array('nombre' => "Debe tener entre 2 y $max caracteres."));
        }
        return $nombre;
    }

    public static function crearMunicipio($nombre, $provinciaId, $idUsuario)
    {
        $nombre = self::validarNombre($nombre);
        $pdo = Sci_Db::conexion();
        $prov = $pdo->prepare('SELECT id_provincia FROM provincia WHERE id_provincia = ?');
        $prov->execute(array((int) $provinciaId));
        if ($prov->fetch() === false) throw Sci_Api_Exception::solicitudInvalida('La provincia indicada no existe.');

        $existe = $pdo->prepare('SELECT id_municipio FROM municipio WHERE id_provincia = ? AND LOWER(nombre) = LOWER(?)');
        $existe->execute(array((int) $provinciaId, $nombre));
        if ($existe->fetch() !== false) throw Sci_Api_Exception::validacion(array('nombre' => 'Ya existe un municipio con ese nombre en esta provincia.'));

        $pdo->prepare('INSERT INTO municipio (nombre, id_provincia) VALUES (?, ?)')->execute(array($nombre, (int) $provinciaId));
        $id = (int) $pdo->lastInsertId();
        AuditoriaService::registrar($idUsuario, null, 'CREAR_CATALOGO', 'municipio:' . $id);
        return array('id' => $id, 'nombre' => $nombre, 'padreId' => (int) $provinciaId);
    }

    public static function renombrarMunicipio($id, $nombre, $idUsuario)
    {
        return self::renombrarHijo('municipio', 'id_municipio', 'id_provincia', $id, $nombre, $idUsuario);
    }

    public static function crearInstitucion($nombre, $municipioId, $idUsuario)
    {
        $nombre = self::validarNombre($nombre);
        $pdo = Sci_Db::conexion();
        $padre = $pdo->prepare('SELECT id_municipio FROM municipio WHERE id_municipio = ?');
        $padre->execute(array((int) $municipioId));
        if ($padre->fetch() === false) throw Sci_Api_Exception::solicitudInvalida('El municipio indicado no existe.');

        $existe = $pdo->prepare('SELECT id_institucion FROM institucion_educativa WHERE id_municipio = ? AND LOWER(nombre) = LOWER(?)');
        $existe->execute(array((int) $municipioId, $nombre));
        if ($existe->fetch() !== false) throw Sci_Api_Exception::validacion(array('nombre' => 'Ya existe una institución con ese nombre en este municipio.'));

        $pdo->prepare('INSERT INTO institucion_educativa (nombre, id_municipio) VALUES (?, ?)')->execute(array($nombre, (int) $municipioId));
        $id = (int) $pdo->lastInsertId();
        AuditoriaService::registrar($idUsuario, null, 'CREAR_CATALOGO', 'institucion:' . $id);
        return array('id' => $id, 'nombre' => $nombre, 'padreId' => (int) $municipioId);
    }

    public static function renombrarInstitucion($id, $nombre, $idUsuario)
    {
        return self::renombrarHijo('institucion_educativa', 'id_institucion', 'id_municipio', $id, $nombre, $idUsuario);
    }

    public static function crearSede($nombre, $institucionId, $idUsuario)
    {
        $nombre = self::validarNombre($nombre);
        $pdo = Sci_Db::conexion();
        $padre = $pdo->prepare('SELECT id_institucion FROM institucion_educativa WHERE id_institucion = ?');
        $padre->execute(array((int) $institucionId));
        if ($padre->fetch() === false) throw Sci_Api_Exception::solicitudInvalida('La institución indicada no existe.');

        $existe = $pdo->prepare('SELECT id_sede FROM sede WHERE id_institucion = ? AND LOWER(nombre) = LOWER(?)');
        $existe->execute(array((int) $institucionId, $nombre));
        if ($existe->fetch() !== false) throw Sci_Api_Exception::validacion(array('nombre' => 'Ya existe una sede con ese nombre en esta institución.'));

        $pdo->prepare('INSERT INTO sede (nombre, id_institucion) VALUES (?, ?)')->execute(array($nombre, (int) $institucionId));
        $id = (int) $pdo->lastInsertId();
        AuditoriaService::registrar($idUsuario, null, 'CREAR_CATALOGO', 'sede:' . $id);
        return array('id' => $id, 'nombre' => $nombre, 'padreId' => (int) $institucionId);
    }

    public static function renombrarSede($id, $nombre, $idUsuario)
    {
        return self::renombrarHijo('sede', 'id_sede', 'id_institucion', $id, $nombre, $idUsuario);
    }

    private static function renombrarHijo($tabla, $colId, $colPadre, $id, $nombre, $idUsuario)
    {
        $nombre = self::validarNombre($nombre);
        $pdo = Sci_Db::conexion();
        $stmt = $pdo->prepare("SELECT $colPadre AS padre FROM $tabla WHERE $colId = ?");
        $stmt->execute(array((int) $id));
        $fila = $stmt->fetch();
        if ($fila === false) throw Sci_Api_Exception::noEncontrado();
        $padreId = (int) $fila['padre'];

        $existe = $pdo->prepare("SELECT $colId FROM $tabla WHERE $colPadre = ? AND LOWER(nombre) = LOWER(?) AND $colId <> ?");
        $existe->execute(array($padreId, $nombre, (int) $id));
        if ($existe->fetch() !== false) throw Sci_Api_Exception::validacion(array('nombre' => 'Ya existe otro registro con ese nombre en el mismo padre.'));

        $pdo->prepare("UPDATE $tabla SET nombre = ? WHERE $colId = ?")->execute(array($nombre, (int) $id));
        AuditoriaService::registrar($idUsuario, null, 'EDITAR_CATALOGO', "$tabla:$id");
        return array('id' => (int) $id, 'nombre' => $nombre, 'padreId' => $padreId);
    }

    public static function renombrarArea($id, $nombre, $idUsuario)
    {
        $nombre = self::validarNombre($nombre, 80);
        $pdo = Sci_Db::conexion();
        $stmt = $pdo->prepare('SELECT id_area FROM area WHERE id_area = ?');
        $stmt->execute(array((int) $id));
        if ($stmt->fetch() === false) throw Sci_Api_Exception::noEncontrado();

        $existe = $pdo->prepare('SELECT id_area FROM area WHERE LOWER(nombre) = LOWER(?) AND id_area <> ?');
        $existe->execute(array($nombre, (int) $id));
        if ($existe->fetch() !== false) throw Sci_Api_Exception::validacion(array('nombre' => 'Ya existe otra área con ese nombre.'));

        $pdo->prepare('UPDATE area SET nombre = ? WHERE id_area = ?')->execute(array($nombre, (int) $id));
        AuditoriaService::registrar($idUsuario, null, 'EDITAR_CATALOGO', 'area:' . $id);
        return array('id' => (int) $id, 'nombre' => $nombre);
    }
}
