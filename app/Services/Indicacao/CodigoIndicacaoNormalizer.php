<?php
namespace Services\Indicacao;
class CodigoIndicacaoNormalizer
{
    public static function normalizar($codigo): string
    {
        $codigo = strtoupper(trim((string)$codigo));
        return preg_replace('/[^A-Z0-9]/', '', $codigo);
    }
}
