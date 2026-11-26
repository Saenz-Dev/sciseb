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

    public function construir()
    {
        $local = '';
        $central = '';
        $offset = 0;

        foreach (array_keys($this->archivos) as $nombre) {
            $contenido = $this->archivos[$nombre];
            $crc = crc32($contenido);
            $tamano = strlen($contenido);
            $nombreBin = $nombre;

            $cabeceraLocal = "PK\x03\x04"
                . pack('v', 20)       // versión necesaria
                . pack('v', 0)        // flags
                . pack('v', 0)        // método: 0 = sin comprimir
                . pack('v', 0)        // hora
                . pack('v', 0x21)     // fecha (1-1-1980, valor mínimo válido)
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
                . pack('v', 0) . pack('v', 0x21)
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
