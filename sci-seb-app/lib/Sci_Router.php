<?php
/**
 * Router mínimo sin framework. Los parámetros de ruta son tipados (numéricos) para que rutas
 * fijas como /casos/exportar y rutas con parámetro como /casos/{id} no choquen sin depender del
 * orden de registro.
 */
class Sci_Router
{
    private $rutas = array(); // array de array('metodo','regex','auth','manejador')

    public function get($patron, $manejador, $auth = 'AUTH') { $this->agregar('GET', $patron, $manejador, $auth); }
    public function post($patron, $manejador, $auth = 'AUTH') { $this->agregar('POST', $patron, $manejador, $auth); }
    public function put($patron, $manejador, $auth = 'AUTH') { $this->agregar('PUT', $patron, $manejador, $auth); }
    public function patch($patron, $manejador, $auth = 'AUTH') { $this->agregar('PATCH', $patron, $manejador, $auth); }
    public function delete($patron, $manejador, $auth = 'AUTH') { $this->agregar('DELETE', $patron, $manejador, $auth); }

    private function agregar($metodo, $patron, $manejador, $auth)
    {
        $regex = preg_replace_callback('/\{([a-zA-Z]+)\}/', array($this, 'tipoParametro'), $patron);
        $regex = '#^' . $regex . '$#u';
        $this->rutas[] = array(
            'metodo'    => $metodo,
            'regex'     => $regex,
            'auth'      => $auth, // PUBLIC | AUTH | ADMIN
            'manejador' => $manejador,
        );
    }

    private function tipoParametro($coincidencia)
    {
        $nombre = $coincidencia[1];
        // numero (1-12), id/idAccion/idEnlace/padreId/idImportacion -> enteros; token -> cualquier
        // caracter sin barra (UUID).
        if ($nombre === 'numero') {
            return '(?P<' . $nombre . '>[0-9]{1,2})';
        }
        if ($nombre === 'token') {
            return '(?P<' . $nombre . '>[^/]+)';
        }
        return '(?P<' . $nombre . '>[0-9]+)';
    }

    /** @return array('ruta'=>array, 'params'=>array) o null si ninguna ruta coincide */
    public function encontrar($metodo, $ruta)
    {
        $ruta = rtrim($ruta, '/');
        if ($ruta === '') $ruta = '/';
        foreach ($this->rutas as $r) {
            if ($r['metodo'] !== $metodo) continue;
            if (preg_match($r['regex'], $ruta, $m)) {
                $params = array();
                foreach ($m as $clave => $valor) {
                    if (!is_int($clave)) $params[$clave] = $valor;
                }
                return array('ruta' => $r, 'params' => $params);
            }
        }
        return null;
    }
}
