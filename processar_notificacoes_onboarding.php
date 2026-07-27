<?php

if(PHP_SAPI !== 'cli'){ http_response_code(403); exit('Comando disponível apenas via CLI.'); }
require __DIR__ . '/config/config.php';
require __DIR__ . '/vendor/autoload.php';

$lockPath = __DIR__ . '/storage/notificacoes-onboarding.lock';
$lock = fopen($lockPath, 'c+');
if(!$lock || !flock($lock, LOCK_EX | LOCK_NB)){ fwrite(STDERR, "Rotina já está em execução.\n"); exit(0); }

try{
    $resumo = (new Services\NotificacaoOnboardingProcessor())->executar(100);
    echo json_encode($resumo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    $exitCode = 0;
}catch(Throwable $e){
    error_log('Falha na rotina de notificações de onboarding: erro controlado.');
    $exitCode = 1;
}
flock($lock, LOCK_UN); fclose($lock); @unlink($lockPath); exit($exitCode);
