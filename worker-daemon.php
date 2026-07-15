<?php

if(PHP_SAPI !== 'cli'){
    http_response_code(403);
    exit('Worker daemon disponível apenas via CLI.');
}

require __DIR__ . '/config/config.php';
require __DIR__ . '/vendor/autoload.php';

spl_autoload_register(function($class){
    $class = str_replace('\\', '/', $class);
    $file = __DIR__ . '/app/' . $class . '.php';

    if(file_exists($file)){
        require_once $file;
    }
});

use Services\WorkerDaemonRunner;

$runner = new WorkerDaemonRunner();
exit($runner->run());
