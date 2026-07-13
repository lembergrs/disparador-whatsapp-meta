<?php

require_once __DIR__ . '/../app/Services/EmbeddedSignupFlowService.php';

use Services\EmbeddedSignupFlowService;

$assert = function($condition, $message){
    if(!$condition){
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$calls = [];
$service = new EmbeddedSignupFlowService(function($endpoint, array $params = [], $accessToken = null, $method = 'GET') use (&$calls){
    $calls[] = compact('endpoint', 'params', 'accessToken', 'method');
    return ['success' => true];
}, '123');

$assert($service->assinarAppNaWaba('999', 'token') === true, 'assinatura válida retorna true');
$assert(($calls[0]['endpoint'] ?? '') === '999/subscribed_apps', 'endpoint subscribed_apps correto');
$assert(($calls[0]['method'] ?? '') === 'POST', 'subscribed_apps é chamado via POST');
$assert(($calls[0]['accessToken'] ?? '') === 'token', 'token é repassado ao helper Graph');

try{
    $invalidService = new EmbeddedSignupFlowService(function(){ return ['success' => false]; }, '123');
    $invalidService->assinarAppNaWaba('999', 'token');
    $assert(false, 'resposta success=false deve falhar');
}catch(Exception $e){
    $assert(strpos($e->getMessage(), 'assinatura') !== false, 'erro de assinatura é explícito');
}

try{
    $invalidService = new EmbeddedSignupFlowService(function(){ return ['id' => '999']; }, '123');
    $invalidService->assinarAppNaWaba('999', 'token');
    $assert(false, 'resposta sem success=true deve falhar');
}catch(Exception $e){
    $assert(strpos($e->getMessage(), 'assinatura') !== false, 'resposta inválida impede conexão');
}

$debug = [
    'data' => [
        'is_valid' => true,
        'app_id' => '123',
        'scopes' => ['whatsapp_business_messaging'],
        'granular_scopes' => [
            [
                'scope' => 'whatsapp_business_management',
                'target_ids' => ['111', '222']
            ]
        ]
    ]
];

$assert($service->validarDebugToken($debug) === $debug, 'permissões vindas de data.scopes e granular_scopes são aceitas');
[$wabaIds, $businessId] = $service->extrairWabaIdsDoDebugToken($debug);
$assert($wabaIds === ['111', '222'], 'target_ids da WABA vêm de granular_scopes');
$assert($businessId === null, 'business_id ausente é opcional');

$assert($service->definirStatusConexao([
    'operational_status' => 'CONNECTED',
    'code_verification_status' => 'VERIFIED',
    'name_status' => 'APPROVED'
]) === 'conectado', 'status saudável fica conectado');

$assert($service->definirStatusConexao([
    'operational_status' => 'CONNECTED',
    'code_verification_status' => 'PENDING',
    'name_status' => 'APPROVED'
]) === 'requer_acao', 'pendência de verificação requer ação');

$assert($service->definirStatusConexao([
    'operational_status' => 'FLAGGED'
]) === 'requer_acao', 'bloqueio/flag operacional requer ação');

$assert($service->definirStatusConexao([]) === 'conectado', 'campos opcionais ausentes não bloqueiam');

echo "Embedded signup flow service tests passed\n";
