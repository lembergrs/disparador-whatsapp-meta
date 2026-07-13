<?php

require_once __DIR__ . '/../app/Services/EmbeddedSignupAttemptCoordinator.php';

use Services\EmbeddedSignupAttemptCoordinator;

$assert = function($condition, $message){
    if(!$condition){
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$coordenador = new EmbeddedSignupAttemptCoordinator();

// Callback chega antes do FINISH, e o FINISH aparece dentro da janela.
$chamadas = 0;
$tentativa = $coordenador->aguardarFinish(function() use (&$chamadas){
    $chamadas++;
    return [
        'used_at' => null,
        'finish' => $chamadas >= 3 ? ['ids' => ['waba_id' => '111', 'phone_number_id' => '222']] : null
    ];
}, 500, 10);
$assert(!empty($tentativa['finish']['ids']['waba_id']), 'FINISH dentro da janela é observado pelo callback');
$assert($chamadas >= 3, 'callback reconsultou a tentativa antes de prosseguir');

// FINISH não chega: retorna a última tentativa sem bloquear indefinidamente.
$inicio = microtime(true);
$tentativaSemFinish = $coordenador->aguardarFinish(function(){
    return ['used_at' => null, 'finish' => null];
}, 60, 10);
$duracaoMs = (microtime(true) - $inicio) * 1000;
$assert(is_array($tentativaSemFinish) && $tentativaSemFinish['finish'] === null, 'sem FINISH retorna tentativa após timeout curto');
$assert($duracaoMs < 500, 'sem FINISH não bloqueia indefinidamente');

// Callback repetido depois de consumo definitivo.
try{
    $coordenador->aguardarFinish(function(){
        return ['used_at' => time(), 'finish' => ['ids' => ['waba_id' => '111']]];
    }, 100, 10);
    $assert(false, 'callback repetido deveria falhar');
}catch(Exception $e){
    $assert(strpos($e->getMessage(), 'utilizado') !== false, 'callback repetido após consumo é rejeitado');
}

echo "Embedded signup attempt coordinator tests passed\n";
