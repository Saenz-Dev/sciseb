<?php
/**
 * Puerto 1:1 de util/CasoValidador.java. Mismas reglas, mismos mensajes, para que el formulario
 * (validadores.js, ya existente, sin tocar) y el backend coincidan exactamente.
 *
 * Nota sobre \p{L}: en vez de depender del modificador /u con la clase Unicode \p{L} (no
 * garantizada en todos los builds de PCRE de un PHP 5.3.3 de hosting antiguo), se usa un rango
 * de caracteres latinos explícito — esta app solo necesita nombres en español, nunca otros
 * alfabetos, así que el rango cubre sobradamente el caso real sin depender de esa garantía.
 */
class Sci_Validator
{
    const LETRA = "A-Za-zÁÉÍÓÚÑÜáéíóúñüÀ-ÖØ-öø-ÿ";
    const FECHA_MINIMA = '2000-01-01';

    private static function patronNombre() { return '/^[' . self::LETRA . '][' . self::LETRA . " .'-]{2,119}$/u"; }
    private static function patronId() { return '/^[0-9]{5}$/'; }
    private static function patronCedula() { return '/^[0-9]{5,12}$/'; }
    private static function patronIniciales() { return '/^([A-ZÁÉÍÓÚÑ]\.?){1,5}$/u'; }
    private static function patronAlfanum() { return '/^[A-Za-z0-9\/_. -]{1,30}$/'; }
    private static function patronResolucion() { return '/^[0-9]{1,20}$/'; }
    private static function patronPenal() { return '/^[0-9]{1,30}$/'; }

    /** Quita espacios sobrantes; el texto vacío se vuelve null. */
    public static function limpiar($s)
    {
        if ($s === null) return null;
        $t = trim(preg_replace('/\s+/u', ' ', (string) $s));
        return $t === '' ? null : $t;
    }

    public static function normalizarIniciales($s)
    {
        $t = self::limpiar($s);
        return $t === null ? null : mb_strtoupper(str_replace(' ', '', $t), 'UTF-8');
    }

    /**
     * $d es un array asociativo con las mismas claves que el record Datos de Java: idCaso,
     * presuntoAgresor, victimas (array), cedula, areaAsignatura, nRadicado, resolucionSeb,
     * resolucionRectoral, ubicacionAgresor, fechaOcurrencia (Y-m-d o null), lugarOcurrencia,
     * disciplinario, decisionComite, penal.
     *
     * @return array campo => mensaje; vacío significa válido.
     */
    public static function validar($d, $hoy)
    {
        $e = array();

        if ($d['idCaso'] !== null && !preg_match(self::patronId(), $d['idCaso'])) {
            $e['idCaso'] = 'El ID del caso debe tener exactamente 5 números (por ejemplo 00123).';
        }

        if ($d['presuntoAgresor'] !== null && !preg_match(self::patronNombre(), $d['presuntoAgresor'])) {
            $e['presuntoAgresor'] = 'Escriba el nombre del presunto agresor (solo letras, de 3 a 120 caracteres).';
        }

        if ($d['victimas'] !== null) {
            foreach ($d['victimas'] as $v) {
                if ($v === null || !preg_match(self::patronIniciales(), $v)) {
                    $e['victimas'] = 'Escriba solo las iniciales de cada presunta víctima (de 1 a 5 letras, por ejemplo J.P.). Nunca el nombre.';
                    break;
                }
            }
        }

        if ($d['cedula'] !== null && !preg_match(self::patronCedula(), $d['cedula'])) {
            $e['cedula'] = 'La cédula debe tener entre 5 y 12 números, sin puntos ni espacios.';
        }

        if ($d['areaAsignatura'] !== null && mb_strlen($d['areaAsignatura'], 'UTF-8') > 80) {
            $e['areaAsignatura'] = 'El área o asignatura no puede superar 80 caracteres.';
        }

        if ($d['nRadicado'] !== null && !preg_match(self::patronAlfanum(), $d['nRadicado'])) {
            $e['nRadicado'] = 'El radicado solo puede tener letras, números y los signos / _ . - (máximo 30).';
        }

        if ($d['resolucionSeb'] !== null && !preg_match(self::patronResolucion(), $d['resolucionSeb'])) {
            $e['resolucionSeb'] = 'La resolución SEB debe contener solo números.';
        }

        if ($d['resolucionRectoral'] !== null && !preg_match(self::patronResolucion(), $d['resolucionRectoral'])) {
            $e['resolucionRectoral'] = 'La resolución rectoral debe contener solo números.';
        }

        if ($d['ubicacionAgresor'] !== null && mb_strlen($d['ubicacionAgresor'], 'UTF-8') > 500) {
            $e['ubicacionAgresor'] = 'La ubicación no puede superar 500 caracteres.';
        }

        if ($d['fechaOcurrencia'] !== null) {
            if ($d['fechaOcurrencia'] > $hoy) {
                $e['fechaOcurrencia'] = 'La fecha de ocurrencia no puede ser futura.';
            } elseif ($d['fechaOcurrencia'] < self::FECHA_MINIMA) {
                $e['fechaOcurrencia'] = 'La fecha de ocurrencia no es válida.';
            }
        }

        if ($d['lugarOcurrencia'] !== null && mb_strlen($d['lugarOcurrencia'], 'UTF-8') > 120) {
            $e['lugarOcurrencia'] = 'El lugar de ocurrencia no puede superar 120 caracteres.';
        }

        if ($d['disciplinario'] !== null && !preg_match(self::patronAlfanum(), $d['disciplinario'])) {
            $e['disciplinario'] = 'El dato disciplinario solo puede tener letras, números y los signos / _ . - (máximo 30).';
        }

        if ($d['decisionComite'] !== null && mb_strlen($d['decisionComite'], 'UTF-8') > 2000) {
            $e['decisionComite'] = 'La decisión del comité no puede superar 2000 caracteres.';
        }

        if ($d['penal'] !== null && !preg_match(self::patronPenal(), $d['penal'])) {
            $e['penal'] = 'El número penal debe contener solo números (máximo 30).';
        }

        return $e;
    }

    /** Muestra solo los últimos 3 dígitos: 12345678 -> *****678. */
    public static function enmascararCedula($cedula)
    {
        if ($cedula === null || strlen($cedula) < 4) return '****';
        return str_repeat('*', strlen($cedula) - 3) . substr($cedula, -3);
    }

    /** Evita inyección de fórmulas al abrir un CSV/Excel exportado. */
    public static function neutralizarFormula($s)
    {
        if ($s === null || $s === '') return $s;
        $c = $s[0];
        return ($c === '=' || $c === '+' || $c === '-' || $c === '@' || $c === "\t" || $c === "\r") ? ("'" . $s) : $s;
    }

    // strtr() de dos cadenas mapea byte a byte: con tildes en UTF-8 (2 bytes) corrompería la
    // cadena, por eso se usa str_replace con arrays de caracteres multibyte completos.
    private static function tabla()
    {
        static $desde = null, $hacia = null;
        if ($desde === null) {
            $desde = array('Á','É','Í','Ó','Ú','Ñ','Ü','á','é','í','ó','ú','ñ','ü',
                            'À','È','Ì','Ò','Ù','à','è','ì','ò','ù','Â','Ê','Î','Ô','Û','â','ê','î','ô','û');
            $hacia = array('A','E','I','O','U','N','U','a','e','i','o','u','n','u',
                            'A','E','I','O','U','a','e','i','o','u','A','E','I','O','U','a','e','i','o','u');
        }
        return array($desde, $hacia);
    }

    private static function sinTildes($s)
    {
        list($desde, $hacia) = self::tabla();
        $s = str_replace($desde, $hacia, (string) $s);
        return mb_strtolower(trim($s), 'UTF-8');
    }

    public static function mismoNombre($a, $b)
    {
        return self::sinTildes($a) === self::sinTildes($b);
    }
}
