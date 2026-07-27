<?php

namespace Services;

class AnalyticsService
{
    private const SESSION_KEY = 'analytics_eventos_pendentes';
    private const PARAMETROS = [
        'sign_up' => ['signup_method'],
        'login' => ['login_method'],
        'meta_connection_completed' => [],
        'trial_started' => [],
    ];

    public static function registrar($evento, array $dados = [])
    {
        if(session_status() !== PHP_SESSION_ACTIVE || !array_key_exists($evento, self::PARAMETROS)) return false;
        $permitidos = array_flip(self::PARAMETROS[$evento]);
        $dados = array_intersect_key($dados, $permitidos);
        $registro = ['evento'=>$evento, 'dados'=>$dados];
        $chave = hash('sha256', json_encode($registro));
        $fila = $_SESSION[self::SESSION_KEY] ?? [];
        $fila[$chave] = $registro;
        $_SESSION[self::SESSION_KEY] = $fila;
        return true;
    }

    public static function consumir()
    {
        if(session_status() !== PHP_SESSION_ACTIVE) return [];
        $fila = array_values($_SESSION[self::SESSION_KEY] ?? []);
        unset($_SESSION[self::SESSION_KEY]);
        return $fila;
    }
}
