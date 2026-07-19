<?php

namespace Services;

class TelefoneService
{
    public static function somenteDigitos($telefone){ return preg_replace('/\D+/', '', (string) $telefone); }

    public static function normalizar($telefone, $ddiPadrao = '55')
    {
        $n = self::somenteDigitos($telefone);
        if($n === '') return '';
        if(substr($n, 0, 2) === '55') $n = substr($n, 2);
        if(strlen($n) === 10){
            $ddd = substr($n, 0, 2); $local = substr($n, 2);
            if(self::pareceCelularOitoDigitos($local)) $local = '9' . $local;
            return $ddiPadrao . $ddd . $local;
        }
        if(strlen($n) === 11){ return $ddiPadrao . $n; }
        if(strlen($n) > 11 && substr($n, 0, 2) !== $ddiPadrao){ return $n; }
        return substr($n, 0, 2) === $ddiPadrao ? $n : $ddiPadrao . $n;
    }

    public static function variantes($telefone)
    {
        $canonico = self::normalizar($telefone);
        $variantes = [$canonico];
        if(substr($canonico, 0, 2) === '55'){
            $n = substr($canonico, 2);
            $variantes[] = $n;
            if(strlen($n) === 11 && substr($n, 2, 1) === '9'){
                $semNono = substr($n, 0, 2) . substr($n, 3);
                $variantes[] = '55' . $semNono;
                $variantes[] = $semNono;
            }elseif(strlen($n) === 10 && self::pareceCelularOitoDigitos(substr($n, 2))){
                $comNono = substr($n, 0, 2) . '9' . substr($n, 2);
                $variantes[] = '55' . $comNono;
                $variantes[] = $comNono;
            }
        }
        return array_values(array_unique(array_filter($variantes)));
    }

    private static function pareceCelularOitoDigitos($local)
    {
        return strlen($local) === 8 && in_array($local[0], ['6','7','8','9'], true);
    }
}
