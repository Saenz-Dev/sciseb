<?php
/**
 * Puerto de la lectura CSV de ImportacionService.java. Usa fgetcsv() sobre un stream en memoria
 * (no explode() por líneas) para que los campos entre comillas con saltos de línea se lean bien,
 * igual que Apache Commons CSV.
 */
class Sci_Csv
{
    /** Quita el BOM UTF-8 si está, y si el resto no es UTF-8 válido, reintenta como windows-1252
     *  (típico de un CSV guardado por Excel en español con "CSV (delimitado por comas)"). */
    public static function decodificar($bytes)
    {
        $ini = (strlen($bytes) >= 3 && substr($bytes, 0, 3) === "\xEF\xBB\xBF") ? 3 : 0;
        $texto = substr($bytes, $ini);
        if (mb_check_encoding($texto, 'UTF-8')) return $texto;
        $convertido = @iconv('Windows-1252', 'UTF-8', $texto);
        return $convertido !== false ? $convertido : $texto;
    }

    public static function detectarDelimitador($primeraLinea)
    {
        $puntoComa = substr_count($primeraLinea, ';');
        $coma = substr_count($primeraLinea, ',');
        return $puntoComa > $coma ? ';' : ',';
    }

    /**
     * @return array('encabezados'=>array de nombres en minúscula/trim, 'filas'=>array de arrays
     *               asociativos encabezado=>valor-trim-o-null). Ignora líneas vacías.
     */
    public static function leer($texto, $delimitador)
    {
        $stream = fopen('php://temp', 'r+b');
        fwrite($stream, $texto);
        rewind($stream);

        $filaEncabezado = fgetcsv($stream, 0, $delimitador);
        if ($filaEncabezado === false) {
            fclose($stream);
            return array('encabezados' => array(), 'filas' => array());
        }
        $encabezados = array();
        foreach ($filaEncabezado as $h) {
            $encabezados[] = strtolower(trim((string) $h));
        }

        $filas = array();
        while (($fila = fgetcsv($stream, 0, $delimitador)) !== false) {
            if ($fila === array(null)) continue; // línea vacía
            $asociativo = array();
            foreach ($encabezados as $i => $nombre) {
                $valor = isset($fila[$i]) ? trim((string) $fila[$i]) : null;
                $asociativo[$nombre] = ($valor === '' ? null : $valor);
            }
            $filas[] = $asociativo;
        }
        fclose($stream);
        return array('encabezados' => $encabezados, 'filas' => $filas);
    }

    /** CSV con BOM UTF-8, delimitador ';' y CRLF — igual que la exportación en Java. */
    public static function escribir($encabezados, $filas)
    {
        $out = "\xEF\xBB\xBF";
        $out .= self::linea($encabezados);
        foreach ($filas as $f) $out .= self::linea($f);
        return $out;
    }

    private static function linea($campos)
    {
        $out = array();
        foreach ($campos as $c) {
            $c = (string) $c;
            if (preg_match('/[;"\r\n]/', $c)) {
                $c = '"' . str_replace('"', '""', $c) . '"';
            }
            $out[] = $c;
        }
        return implode(';', $out) . "\r\n";
    }
}
