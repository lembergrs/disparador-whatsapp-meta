<?php

if(PHP_SAPI !== 'cli'){ http_response_code(403); exit('Comando disponível apenas via CLI.'); }
require __DIR__ . '/config/config.php';
require __DIR__ . '/vendor/autoload.php';

use Models\TarefaAgendada;
use Services\Tasks\TaskDispatcher;
use Services\Tasks\TaskExecutionService;
use Services\Tasks\TaskProcessor;
use Services\Tasks\TaskRegistry;

$lockPath = TASK_SCHEDULER_LOCK_FILE;
$diretorioLock = dirname($lockPath);
if(!is_dir($diretorioLock)) mkdir($diretorioLock, 0770, true);
$lock = fopen($lockPath, 'c+');
if(!$lock || !flock($lock, LOCK_EX | LOCK_NB)){
    fwrite(STDERR, "Task Scheduler já está em execução.\n");
    exit(0);
}

$liberar = function() use (&$lock, $lockPath){
    if(is_resource($lock)){ flock($lock, LOCK_UN); fclose($lock); }
    if(is_file($lockPath)) @unlink($lockPath);
};
register_shutdown_function($liberar);

try{
    $logFile = TASK_SCHEDULER_LOG_FILE;
    if(!is_dir(dirname($logFile))) mkdir(dirname($logFile), 0770, true);
    $logger = function(array $linha) use ($logFile){
        error_log(json_encode($linha, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, 3, $logFile);
    };
    $repositorio = new TarefaAgendada();
    $processador = new TaskProcessor($repositorio,new TaskDispatcher(new TaskRegistry()),null,TASK_SCHEDULER_LEASE_MINUTES,$logger);
    $execucao = new TaskExecutionService($processador, TASK_SCHEDULER_BATCH_SIZE);
    $resumo = $execucao->processarSobDemanda(TASK_SCHEDULER_BATCH_SIZE);
    echo 'Processadas: ' . $resumo['processadas'] . PHP_EOL;
    echo 'Concluídas: ' . $resumo['concluidas'] . PHP_EOL;
    echo 'Retry: ' . $resumo['retry'] . PHP_EOL;
    echo 'Falhas: ' . $resumo['falhas'] . PHP_EOL;
    $exitCode = $resumo['falhas'] > 0 ? 1 : 0;
}catch(Throwable $e){
    error_log('Task Scheduler: falha operacional controlada.');
    $exitCode = 1;
}

$liberar();
exit($exitCode);
