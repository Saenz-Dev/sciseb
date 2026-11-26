<?php
/**
 * Escritor de ZIP mínimo, sin dependencias (solo funciones núcleo de PHP: crc32, pack). Usa el
 * método STORED (sin comprimir) a propósito: evita depender de que la extensión zlib/zip esté
 * habilitada en el hosting real — un .xlsx de hasta 5000 filas sin comprimir es perfectamente
 * manejable, y así el generador funciona en cualquier PHP sin configuración adicional.
 */
class Sci_Zip
{
    private $archivos = array(); // nombre => contenido

    public function agregar($nombre, $contenido)
    {
        $this->archivos[$nombre] = $contenido;
    }

    /**
     * Fecha/hora DOS (16 bits cada una) de "ahora". Un valor fijo de 0x0000/0x0000 (equivalente a
     * "sin fecha", antes de 1980-01-01) es aceptado por Python/.NET pero Excel lo trata como
     * indicio de paquete corrupto y descarta el contenido sin avisar el motivo — confirmado
     * comparando byte a byte contra un .xlsx idéntico salvo esos 4 bytes.
     */
    private static function fechaHoraDos()
    {
        $t = getdate();
        $anio = $t['year'] < 1980 ? 1980 : $t['year'];
        $fecha = (($anio - 1980) << 9) | ($t['mon'] << 5) | $t['mday'];
        $hora = ($t['hours'] << 11) | ($t['minutes'] << 5) | intval($t['seconds'] / 2);
        return array($hora, $fecha);
    }

    public function construir()
    {
        $local = '';
        $central = '';
        $offset = 0;
        list($horaDos, $fechaDos) = self::fechaHoraDos();

        foreach (array_keys($this->archivos) as $nombre) {
            $contenido = $this->archivos[$nombre];
            $crc = crc32($contenido);
            $tamano = strlen($contenido);
            $nombreBin = $nombre;

            $cabeceraLocal = "PK\x03\x04"
                . pack('v', 20)       // versión necesaria
                . pack('v', 0)        // flags
                . pack('v', 0)        // método: 0 = sin comprimir
                . pack('v', $horaDos)
                . pack('v', $fechaDos)
                . pack('V', $crc)
                . pack('V', $tamano)  // tamaño comprimido = tamaño real (STORED)
                . pack('V', $tamano)
                . pack('v', strlen($nombreBin))
                . pack('v', 0)        // extra
                . $nombreBin;

            $local .= $cabeceraLocal . $contenido;

            $cabeceraCentral = "PK\x01\x02"
                . pack('v', 20) . pack('v', 20)
                . pack('v', 0) . pack('v', 0)
                . pack('v', $horaDos) . pack('v', $fechaDos)
                . pack('V', $crc)
                . pack('V', $tamano)
                . pack('V', $tamano)
                . pack('v', strlen($nombreBin))
                . pack('v', 0) . pack('v', 0)
                . pack('v', 0) . pack('v', 0)
                . pack('V', 0)
                . pack('V', $offset)
                . $nombreBin;

            $central .= $cabeceraCentral;
            $offset += strlen($cabeceraLocal) + $tamano;
        }

        $finCentral = "PK\x05\x06"
            . pack('v', 0) . pack('v', 0)
            . pack('v', count($this->archivos))
            . pack('v', count($this->archivos))
            . pack('V', strlen($central))
            . pack('V', $offset)
            . pack('v', 0);

        return $local . $central . $finCentral;
    }
}
