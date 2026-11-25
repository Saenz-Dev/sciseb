<?php
/**
 * El frontend siempre manda JSON (Content-Type: application/json) salvo en la subida de CSV
 * (multipart/form-data). PHP no llena $_POST para PUT/PATCH/DELETE, así que el cuerpo se lee
 * siempre de php://input, nunca de los superglobales (salvo $_FILES, el único uso legítimo).
 */
class Sci_Request
{
    public $metodo;
    public $ruta;       // path sin query string, p.ej. "/casos/8"
    public $query;      // array de query params
    private $cuerpoCrudo;
    private $cuerpoJson; // cacheado, puede ser null si no es JSON válido o está vacío

    public function __construct($metodo, $uriCompleta)
    {
        $this->metodo = strtoupper($metodo);
        $partes = explode('?', $uriCompleta, 2);
        $this->ruta = $partes[0];
        $this->query = array();
        if (isset($partes[1])) {
            parse_str($partes[1], $this->query);
        }
        $this->cuerpoCrudo = file_get_contents('php://input');
        $this->cuerpoJson = null;
    }

    public function query($nombre, $porDefecto = null)
    {
        return isset($this->query[$nombre]) && $this->query[$nombre] !== '' ? $this->query[$nombre] : $porDefecto;
    }

    public function cuerpo()
    {
        if ($this->cuerpoJson === null && $this->cuerpoCrudo !== '') {
            $decodificado = json_decode($this->cuerpoCrudo, true);
            $this->cuerpoJson = is_array($decodificado) ? $decodificado : array();
        }
        return $this->cuerpoJson === null ? array() : $this->cuerpoJson;
    }

    public function campo($nombre, $porDefecto = null)
    {
        $c = $this->cuerpo();
        return isset($c[$nombre]) ? $c[$nombre] : $porDefecto;
    }

    /** Cabecera Authorization con la cadena de respaldo que necesitan varios hostings cPanel. */
    public function cabeceraAutorizacion()
    {
        if (function_exists('getallheaders')) {
            $cabeceras = getallheaders();
            foreach ($cabeceras as $nombre => $valor) {
                if (strcasecmp($nombre, 'Authorization') === 0 && $valor !== '') {
                    return $valor;
                }
            }
        }
        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            return $_SERVER['HTTP_AUTHORIZATION'];
        }
        if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            return $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }
        if (function_exists('apache_request_headers')) {
            $cabeceras = apache_request_headers();
            foreach ($cabeceras as $nombre => $valor) {
                if (strcasecmp($nombre, 'Authorization') === 0 && $valor !== '') {
                    return $valor;
                }
            }
        }
        return null;
    }

    public function token()
    {
        $cabecera = $this->cabeceraAutorizacion();
        if ($cabecera === null) return null;
        if (stripos($cabecera, 'Bearer ') === 0) {
            return trim(substr($cabecera, 7));
        }
        return null;
    }
}
