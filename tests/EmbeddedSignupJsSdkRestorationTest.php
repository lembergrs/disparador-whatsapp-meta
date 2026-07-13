<?php

$view = file_get_contents(__DIR__ . '/../app/Views/configuracao/meta.php');
$controller = file_get_contents(__DIR__ . '/../app/Controllers/ConfiguracaoController.php');

$assert = function($condition, $message){
    if(!$condition){
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$listenerDeclaration = strpos($view, 'function sessionInfoListener(event)');
$listenerRegistration = strpos($view, 'window.addEventListener(\'message\', sessionInfoListener)');
$assert($listenerDeclaration !== false, 'sessionInfoListener está declarado');
$assert($listenerRegistration !== false, 'sessionInfoListener está registrado');
$assert($listenerDeclaration < $listenerRegistration, 'sessionInfoListener é declarado antes do addEventListener');

foreach([
    'tentativaAtiva',
    'signupState',
    'signupRequestId',
    'finishPayload',
    'oauthCode',
    'envioFinalizacaoEmAndamento',
    'coordenacaoTimer',
    'COORDENACAO_TIMEOUT_MS'
] as $variavel){
    $assert(
        preg_match('/(let|const)\s+' . preg_quote($variavel, '/') . '\b/', $view) === 1,
        $variavel . ' está declarada no frontend'
    );
}

$assert(strpos($controller, 'function finalizarEmbeddedSignup(') !== false, 'finalizarEmbeddedSignup existe no controller');
$assert(strpos($controller, 'function processarEmbeddedSignupCode(') !== false, 'processarEmbeddedSignupCode existe no controller');
$assert(strpos($view, 'finalizarEmbeddedSignup') !== false, 'frontend chama finalizarEmbeddedSignup');
$assert(strpos($view, 'FB.login') !== false, 'frontend usa FB.login');
$assert(strpos($view, 'window.open') === false, 'frontend não usa window.open');
$assert(strpos($view, 'business.facebook.com/messaging/whatsapp/onboard') === false, 'frontend não monta URL manual da Meta');
$assert(strpos($view, 'exibirModalEmbeddedSignupMeta') === false, 'frontend não chama modal removido');
$assert(strpos($view, 'btnReabrirEmbeddedSignupMeta') === false, 'frontend não mantém botão de reabrir popup');
$assert(strpos($controller, 'business{id}') === false, 'controller preserva remoção de business{id}');

echo "Embedded signup JS SDK restoration tests passed\n";
