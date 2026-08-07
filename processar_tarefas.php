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

$verbose = in_array('--verbose', $argv ?? [], true);
$saida = new TaskSchedulerCliOutput();
$logger = new TaskSchedulerLogger(TASK_SCHEDULER_LOG_FILE);

$lockPath = TASK_SCHEDULER_LOCK_FILE;
$diretorioLock = dirname($lockPath);
if(!is_dir($diretorioLock)) mkdir($diretorioLock, 0770, true);
$lock = fopen($lockPath, 'c+');
if(!$lock || !flock($lock, LOCK_EX | LOCK_NB)){
    if($verbose) fwrite(STDERR, "Task Scheduler já está em execução.\n");
    exit(0);
}

$liberar = function() use (&$lock, $lockPath){
    if(is_resource($lock)){ flock($lock, LOCK_UN); fclose($lock); }
    if(is_file($lockPath)) @unlink($lockPath);
};
register_shutdown_function($liberar);

try{
    $repositorio = new TarefaAgendada();
    $processador = new TaskProcessor($repositorio,new TaskDispatcher(new TaskRegistry()),null,TASK_SCHEDULER_LEASE_MINUTES,$logger);
    $execucao = new TaskExecutionService($processador, TASK_SCHEDULER_BATCH_SIZE);
    $resumo = $execucao->processarSobDemanda(TASK_SCHEDULER_BATCH_SIZE);
    $texto = $saida->formatar($resumo, $verbose);
    if($texto !== '') echo $texto;
    $exitCode = 0;
}catch(Throwable $e){
    try{
        $logger->operacional('falha_operacional_scheduler');
    }catch(Throwable $logError){
        if($verbose) fwrite(STDERR, "Task Scheduler: falha operacional e falha ao registrar log.\n");
    }
    $exitCode = 1;
}

$liberar();
exit($exitCode);
