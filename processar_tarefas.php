<?php

if(PHP_SAPI !== 'cli'){ http_response_code(403); exit('Comando disponível apenas via CLI.'); }
require __DIR__ . '/config/config.php';
require __DIR__ . '/vendor/autoload.php';

use Models\TarefaAgendada;
use Services\Tasks\TaskDispatcher;
use Services\Tasks\TaskExecutionService;
use Services\Tasks\TaskProcessor;
use Services\Tasks\TaskRegistry;
use Services\Tasks\TaskSchedulerCliOutput;
use Services\Tasks\TaskSchedulerLogger;

$verbose = in_array('--verbose', array_slice($argv, 1), true);
$lock = null;
$possuiLock = false;
$logger = null;
$lockPath = TASK_SCHEDULER_LOCK_FILE;

$liberar = function() use (&$lock, &$possuiLock, $lockPath){
    if(is_resource($lock)){ flock($lock, LOCK_UN); fclose($lock); }
    if($possuiLock && is_file($lockPath)) @unlink($lockPath);
    $possuiLock = false;
};
register_shutdown_function($liberar);

try{
    $logger = new TaskSchedulerLogger(TASK_SCHEDULER_LOG_FILE);
    $diretorioLock = dirname($lockPath);
    if(!is_dir($diretorioLock) && !mkdir($diretorioLock, 0770, true) && !is_dir($diretorioLock)) throw new RuntimeException('Falha ao criar diretório de lock.');
    $lock = fopen($lockPath, 'c+');
    if(!$lock) throw new RuntimeException('Falha ao abrir lock.');
    if(!flock($lock, LOCK_EX | LOCK_NB)){ fclose($lock); $lock = null; exit(0); }
    $possuiLock = true;
    $repositorio = new TarefaAgendada();
    $processador = new TaskProcessor($repositorio,new TaskDispatcher(new TaskRegistry()),null,TASK_SCHEDULER_LEASE_MINUTES,$logger);
    $execucao = new TaskExecutionService($processador, TASK_SCHEDULER_BATCH_SIZE);
    $resumo = $execucao->processarSobDemanda(TASK_SCHEDULER_BATCH_SIZE);
    echo TaskSchedulerCliOutput::resumo($resumo, $verbose);
    $exitCode = 0;
}catch(Throwable $e){
    try{
        if(!$logger) $logger = new TaskSchedulerLogger(TASK_SCHEDULER_LOG_FILE);
        $logger->erroOperacional($e);
    }catch(Throwable $falhaLog){
        error_log('Task Scheduler: falha operacional; log oficial indisponível.');
    }
    $exitCode = 1;
}

$liberar();
exit($exitCode);
