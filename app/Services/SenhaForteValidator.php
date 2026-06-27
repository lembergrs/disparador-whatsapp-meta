<?php

namespace Services;

class SenhaForteValidator
{
    public const TAMANHO_MINIMO = 8;

    public static function validar($senha)
    {
        $senha = (string) $senha;

        return [
            'minimo' => strlen($senha) >= self::TAMANHO_MINIMO,
            'maiuscula' => (bool) preg_match('/[A-Z]/', $senha),
            'minuscula' => (bool) preg_match('/[a-z]/', $senha),
            'numero' => (bool) preg_match('/\d/', $senha),
            'especial' => (bool) preg_match('/[^A-Za-z0-9]/', $senha)
        ];
    }

    public static function forte($senha)
    {
        return !in_array(false, self::validar($senha), true);
    }

    public static function mensagem()
    {
        return 'A senha deve ter no mínimo 8 caracteres, incluindo letra maiúscula, letra minúscula, número e caractere especial.';
    }
}
