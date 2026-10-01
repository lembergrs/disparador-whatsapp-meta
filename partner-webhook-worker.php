<?php

if(PHP_SAPI !== 'cli'){
    http_response_code(403);
    exit('Worker Partner disponível apenas via CLI.');
}

require __DIR__ . '/config/config.php';
require __DIR__ . '/vendor/autoload.php';

spl_autoload_register(function($class){
    $class=str_replace('\\','/',$class);
    $file=__DIR__.'/app/'.$class.'.php';
    if(file_exists($file)) require_once $file;
});

use Services\PartnerWebhookDeliveryService;

$logDir=__DIR__.'/storage/logs';
if(!is_dir($logDir)) mkdir($logDir,0770,true);
ini_set('log_errors','1');
ini_set('error_log',$logDir.'/partner-webhook-worker-error.log');

$lockFile=__DIR__.'/storage/partner-webhook-worker.lock';
$lock=fopen($lockFile,'c+');
if(!$lock || !flock($lock,LOCK_EX|LOCK_NB)){
    if(is_resource($lock)) fclose($lock);
    exit(0);
}

$running=true;
if(function_exists('pcntl_async_signals') && function_exists('pcntl_signal')){
    pcntl_async_signals(true);
    pcntl_signal(SIGTERM,function() use (&$running){$running=false;});
    pcntl_signal(SIGINT,function() use (&$running){$running=false;});
}

register_shutdown_function(function() use ($lock,$lockFile){
    if(is_resource($lock)){flock($lock,LOCK_UN);fclose($lock);}
    if(is_file($lockFile)) @unlink($lockFile);
});

$service=new PartnerWebhookDeliveryService();

while($running){
    try{
        $resumo=$service->processarPendentes(50);
        if(!empty($resumo['reservados'])){
            file_put_contents($logDir.'/partner-webhook-worker.log',json_encode([
                'data'=>date('Y-m-d H:i:s'),
                'resumo'=>$resumo
            ],JSON_UNESCAPED_UNICODE).PHP_EOL,FILE_APPEND);
            continue;
        }
    }catch(Throwable $e){
        error_log('Falha no worker Partner: '.preg_replace('/[\r\n\t]+/',' ',substr($e->getMessage(),0,600)));
        sleep(2);
        continue;
    }

    usleep(500000);
}

exit(0);
