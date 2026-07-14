<?php

defined('WORKER_MAX_ATTEMPTS') || define('WORKER_MAX_ATTEMPTS', 5);
defined('WORKER_RETRY_DELAY_SECONDS') || define('WORKER_RETRY_DELAY_SECONDS', 30);
defined('WORKER_RETRY_MAX_DELAY_SECONDS') || define('WORKER_RETRY_MAX_DELAY_SECONDS', 1800);
defined('WORKER_RETRY_JITTER_SECONDS') || define('WORKER_RETRY_JITTER_SECONDS', 15);
require_once __DIR__ . '/../app/Services/WorkerRetryPolicyService.php';

use Services\WorkerRetryPolicyService;

$assert = function($condition, $message){
    if(!$condition){
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$policy = new WorkerRetryPolicyService();

for($tentativa = 1; $tentativa <= 5; $tentativa++){
    $delay = $policy->calcularDelay($tentativa);
    $base = WORKER_RETRY_DELAY_SECONDS * (2 ** max(0, $tentativa - 1));
    $esperadoMin = min($base, WORKER_RETRY_MAX_DELAY_SECONDS);
    $esperadoMax = min($base, WORKER_RETRY_MAX_DELAY_SECONDS) + WORKER_RETRY_JITTER_SECONDS;

    $assert($delay >= $esperadoMin, "delay mínimo da tentativa {$tentativa}");
    $assert($delay <= $esperadoMax, "delay máximo/jitter da tentativa {$tentativa}");
}

$delayAlto = $policy->calcularDelay(20);
$assert($delayAlto <= WORKER_RETRY_MAX_DELAY_SECONDS + WORKER_RETRY_JITTER_SECONDS, 'delay respeita teto máximo com jitter');

$temporario = $policy->classificarRetorno([
    'http_code' => 429,
    'error' => ['code' => 613, 'message' => 'rate limit']
]);
$assert(!$temporario['sucesso'], 'retorno 429 não é sucesso');
$assert($temporario['retry'] === true, 'retorno 429 permite retry');
$assert($temporario['tipo_resultado'] === WorkerRetryPolicyService::ERRO_TEMPORARIO, 'retorno 429 é temporário');

$definitivo = $policy->classificarRetorno([
    'http_code' => 400,
    'error' => ['code' => 131026, 'message' => 'Invalid parameter']
]);
$assert(!$definitivo['sucesso'], 'retorno 400 não é sucesso');
$assert($definitivo['retry'] === false, 'retorno 400 não permite retry por padrão');
$assert($definitivo['tipo_resultado'] === WorkerRetryPolicyService::ERRO_DEFINITIVO, 'retorno 400 é definitivo por padrão');

$sucesso = $policy->classificarRetorno(['messages' => [['id' => 'wamid.TESTE']]]);
$assert($sucesso['sucesso'] === true, 'retorno com message_id é sucesso');
$assert($sucesso['message_id'] === 'wamid.TESTE', 'message_id preservado');

$assert($policy->atingiuMaximo(WORKER_MAX_ATTEMPTS) === true, 'max attempts atingido');
$assert($policy->atingiuMaximo(WORKER_MAX_ATTEMPTS - 1) === false, 'max attempts ainda não atingido');

// Valida que o SQL de próxima tentativa é gerado como expressão DATE_ADD segura para uso interno.
$sql = $policy->proximaTentativaSql(2);
$assert(strpos($sql, 'DATE_ADD(NOW(), INTERVAL ') === 0, 'expressão SQL de próxima tentativa');
$assert(substr($sql, -8) === ' SECOND)', 'expressão SQL usa segundos');

echo "WorkerRetryPolicyService tests passed\n";
