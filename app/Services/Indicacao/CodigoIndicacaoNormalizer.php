<?php

namespace Services\Indicacao;

class CodigoIndicacaoNormalizer
{
    public static function normalizar($codigo): string
    {
        $codigo = trim((string)$codigo);
        if($codigo === '') return '';

        $codigo = function_exists('mb_strtoupper') ? mb_strtoupper($codigo, 'UTF-8') : strtoupper($codigo);
        if(function_exists('iconv')){
            $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $codigo);
            if($ascii !== false) $codigo = $ascii;
        }

        return preg_replace('/[^A-Z0-9-]+/', '', $codigo) ?: '';
    }
}
