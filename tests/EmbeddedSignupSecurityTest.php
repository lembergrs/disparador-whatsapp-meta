<?php

$controller = file_get_contents(__DIR__ . '/../app/Controllers/ConfiguracaoController.php');
$flowService = file_get_contents(__DIR__ . '/../app/Services/EmbeddedSignupFlowService.php');
$model = file_get_contents(__DIR__ . '/../app/Models/MetaConta.php');
$view = file_get_contents(__DIR__ . '/../app/Views/configuracao/meta.php');
$doc = file_get_contents(__DIR__ . '/../docs/embedded-signup-finalizacao.md');

$assert = function($condition, $message){
    if(!$condition){
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$assert(strpos($controller, 'iniciarEmbeddedSignup') !== false, 'endpoint de início existe');
$assert(strpos($controller, 'registrarEmbeddedSignupFinish') !== false, 'endpoint FINISH existe');
$assert(strpos($controller, "'used_at'") !== false, 'state é de uso único');
$assert(strpos($controller, 'expires_at') !== false, 'tentativa expira');
$assert(strpos($controller, 'CURLOPT_SSL_VERIFYHOST => 2') !== false, 'TLS valida host');
$assert(strpos($controller, 'CURLOPT_SSL_VERIFYPEER => true') !== false, 'TLS valida peer');
$assert(strpos($controller, 'validarDebugToken') !== false, 'debug_token é validado');
$assert(strpos($flowService, 'subscribed_apps') !== false, 'assinatura da WABA implementada');
$assert(strpos($controller, 'count($wabaIds) !== 1') !== false, 'não escolhe primeira WABA silenciosamente');
$assert(strpos($controller, 'count($telefones) !== 1') !== false, 'não escolhe primeiro telefone silenciosamente');
$assert(substr_count($model, 'salvarOuAtualizarEmbeddedSignup') >= 1, 'persistência idempotente existe');
$assert(strpos($view, 'finalizarEmbeddedSignup') !== false, 'frontend envia code e FINISH para finalização');
$assert(strpos($view, 'tentativaAtiva') !== false, 'frontend evita múltiplos cliques');
$assert(strpos($view, 'No próximo passo, este botão será ligado') === false, 'texto desatualizado removido');
$assert(strpos($doc, '`MTA_Token` continua armazenado como texto') !== false, 'risco de token documentado');
$embeddedSavePos = strpos($controller, 'salvarOuAtualizarEmbeddedSignup');
$embeddedTrialPos = strpos($controller, 'iniciarTrialSePendente', $embeddedSavePos);
$assert($embeddedSavePos !== false && $embeddedTrialPos !== false && $embeddedTrialPos > $embeddedSavePos, 'trial só após persistência do Embedded Signup');
$assert(strpos($controller, "statusConexao === 'conectado'") !== false, 'trial depende de status conectado');
$assert(strpos($controller, 'access_token') !== false, 'código usa token internamente');
$assert(strpos($controller, 'unset($dados[\'access_token\']') !== false, 'logs removem access_token');

echo "Embedded signup security checks passed\n";
