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

$assert($service->registrarPhoneNumber('555', '123456', 'token') === true, 'PIN válido registra com sucesso');
$assert($calls[0]['endpoint'] === '555/register', 'register usa endpoint do phone_number_id');
$assert($calls[0]['method'] === 'POST', 'register usa POST');
$assert($calls[0]['params']['messaging_product'] === 'whatsapp', 'messaging_product correto');
$assert($calls[0]['params']['pin'] === '123456', 'PIN é enviado apenas ao helper Graph');

try{
    $invalidService = new EmbeddedSignupFlowService(function(){ return ['success' => false]; }, '123');
    $invalidService->registrarPhoneNumber('555', '123456', 'token');
    $assert(false, 'resposta success=false deve falhar');
}catch(Exception $e){
    $assert(strpos($e->getMessage(), 'registro operacional') !== false, 'falha de register é explícita e segura');
}

$controller = file_get_contents(__DIR__ . '/../app/Controllers/ConfiguracaoController.php');
$view = file_get_contents(__DIR__ . '/../app/Views/configuracao/meta.php');
$assert(strpos($controller, 'registrarNumeroWhatsApp') !== false, 'endpoint de registrar número existe');
$assert(strpos($controller, "preg_match('/^[0-9]{6}$/', \$pin)") !== false, 'backend valida PIN de 6 dígitos');
$assert(strpos($controller, 'pin_confirmacao') !== false, 'backend exige confirmação do PIN');
$assert(strpos($controller, 'atualizarStatusNumeroWhatsApp') !== false, 'endpoint de atualizar status sem PIN existe');
$assert(strpos($view, 'type="password"') !== false, 'UI usa campo password para PIN');
$assert(strpos($view, 'inputmode="numeric"') !== false, 'UI usa inputmode numeric');
$assert(strpos($view, 'autocomplete="off"') !== false, 'UI desliga autocomplete do PIN');
$assert(strpos($view, 'Concluir registro') !== false, 'UI exibe ação de concluir registro');
$assert(strpos($view, 'name="pin_confirmacao"') !== false, 'UI pede confirmação do PIN');
$assert(strpos($view, 'Atualizar status') !== false, 'UI exibe ação de atualizar status');
$assert(strpos($controller, 'pin\'=>') === false, 'código não loga array com chave pin literal');
$assert(strpos($controller, 'buscarPorCliente($contaId, $clienteId)') !== false, 'endpoint valida vínculo da conta com o cliente autenticado');
$assert(strpos($controller, 'registrarPhoneNumberMeta') < strpos($controller, 'atualizarStatusOperacionalConta'), 'register ocorre antes da reavaliação operacional');
$assert(strpos($controller, 'logMetaEmbeddedSignup') !== false, 'logs continuam centralizados');
$assert(strpos($controller, 'request_id') !== false, 'logs incluem requestId');
$assert(strpos($controller, 'iniciarTrialSePendente') > strpos($controller, "statusConexao === 'conectado'"), 'trial só ocorre após conectado');
$assert(strpos($controller, "'pendente_registro'") !== false, 'Embedded finalizado fica pendente_registro');
$assert(strpos($view, 'window.open') === false, 'fluxo FB.login não usa window.open');
$assert(strpos($view, 'business.facebook.com/messaging/whatsapp/onboard') === false, 'fluxo não usa URL manual');
$assert(strpos($view, 'FB.login') !== false, 'fluxo existente FB.login permanece');

echo "Embedded signup phone register tests passed\n";
