<?php

if(PHP_SAPI !== 'cli'){
    http_response_code(403);
    exit('Worker disponível apenas via CLI.');
}

require __DIR__ . '/config/config.php';
require __DIR__ . '/vendor/autoload.php';

configurarLogsWorkerCli();

spl_autoload_register(function($class){
    $class = str_replace('\\', '/', $class);
    $file = __DIR__ . '/app/' . $class . '.php';

    if(file_exists($file)){
        require_once $file;
    }
});

use Services\WorkerService;

function configurarLogsWorkerCli()
{
    $diretorio = __DIR__ . '/storage/logs';

    if(!is_dir($diretorio)){
        mkdir($diretorio, 0770, true);
    }

    ini_set('log_errors', '1');
    ini_set('error_log', $diretorio . '/worker-error.log');
}

function registrarSaidaWorker(array $resumo)
{
    echo json_encode($resumo, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
}

function sanitizarMensagemWorker($mensagem)
{
    $mensagem = preg_replace('/(access_token|token|authorization|bearer|senha|password|secret)[^,;\s]*/i', '$1=***', (string) $mensagem);
    $mensagem = preg_replace('/[\r\n\t]+/', ' ', $mensagem);

    return trim(substr($mensagem, 0, 600));
}

function adquirirWorkerLock($ttlSegundos = 600)
{
    $diretorio = __DIR__ . '/storage';

    if(!is_dir($diretorio)){
        mkdir($diretorio, 0770, true);
    }

    $arquivo = $diretorio . '/worker.lock';
    $handle = fopen($arquivo, 'c+');

    if(!$handle){
        error_log('Não foi possível criar lock do worker.');
        return false;
    }

    if(!flock($handle, LOCK_EX | LOCK_NB)){
        clearstatcache(true, $arquivo);
        $idade = is_file($arquivo) ? time() - filemtime($arquivo) : 0;
        $mensagem = $idade > $ttlSegundos
            ? "Worker lock ativo há mais de {$ttlSegundos}s. Encerrando para evitar concorrência."
            : 'Worker já em execução. Encerrando.';

        error_log($mensagem);
        fclose($handle);
        return false;
    }

    ftruncate($handle, 0);
    fwrite($handle, (string) getmypid());
    fflush($handle);

    return [$handle, $arquivo];
}

function liberarWorkerLock($lock)
{
    if(!$lock || !is_array($lock)){
        return;
    }

    [$handle, $arquivo] = $lock;

    if(is_resource($handle)){
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    if(is_file($arquivo)){
        @unlink($arquivo);
    }
}

$workerLock = adquirirWorkerLock(600);

if(!$workerLock){
    exit(0);
}

register_shutdown_function(function() use (&$workerLock){
    liberarWorkerLock($workerLock);
});

$exitCode = 0;

try{
    $service = new WorkerService([
        'modo_teste' => false,
        'limite_campanhas' => 50,
        'limite_disparo_manual' => 20,
        'timeout_processando_minutos' => WORKER_PROCESSING_TIMEOUT_MINUTES
    ]);

    $resumo = $service->executarCiclo();
    registrarSaidaWorker($resumo);

    if(!empty($resumo['excecoes'])){
        $exitCode = 1;
    }
}catch(Throwable $e){
    error_log('Falha inesperada no worker: ' . sanitizarMensagemWorker($e->getMessage()));
    $exitCode = 1;
}

liberarWorkerLock($workerLock);
$workerLock = null;

exit($exitCode);
