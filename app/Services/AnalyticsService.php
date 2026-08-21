<?php

namespace Services;

class AnalyticsService
{
    private const SESSION_KEY = 'analytics_eventos_pendentes';
    private const PARAMETROS = [
        'sign_up' => ['method', 'account_type'],
        'cadastro_concluido' => [],
        'login' => ['method'],
        'start_trial' => ['trial_duration_days', 'trial_message_limit', 'trigger'],
        'connect_meta' => ['connection_type', 'first_connection', 'source_area'],
        'first_campaign_created' => ['campaign_type', 'first_campaign'],
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

    public static function prepararPurchase(array $dados)
    {
        $item = is_array($dados['item'] ?? null) ? $dados['item'] : [];
        if(empty($dados['transaction_id']) || !is_numeric($dados['value']) || empty($item['item_id']) || empty($item['item_name'])) return null;
        $valor = round((float) $dados['value'], 2);
        return ['evento'=>'purchase', 'dados'=>[
            'transaction_id'=>(string) $dados['transaction_id'], 'value'=>$valor, 'currency'=>'BRL',
            'items'=>[['item_id'=>(string) $item['item_id'], 'item_name'=>(string) $item['item_name'], 'item_category'=>'subscription', 'price'=>$valor, 'quantity'=>1]],
        ]];
    }
}
