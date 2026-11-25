<?php
class Sci_Util
{
    /**
     * Comparación en tiempo constante. hash_equals() no existe hasta PHP 5.6, y un === o strcmp()
     * normal reintroduce un canal lateral de temporización — usado para firmas JWT y hashes.
     */
    public static function compararSeguro($a, $b)
    {
        if (!is_string($a) || !is_string($b) || strlen($a) !== strlen($b)) {
            return false;
        }
        $resultado = 0;
        for ($i = 0, $n = strlen($a); $i < $n; $i++) {
            $resultado |= ord($a[$i]) ^ ord($b[$i]);
        }
        return $resultado === 0;
    }
}
