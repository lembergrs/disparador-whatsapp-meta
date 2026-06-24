<?php

require_once __DIR__ . '/../config/env.php';

configurarErrosAplicacao();

$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $https,
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();

require_once __DIR__ . '/../vendor/autoload.php';

require_once __DIR__ . '/../config/config.php';

spl_autoload_register(function ($class) {

    $class = str_replace('\\', '/', $class);

    $arquivo = __DIR__ . "/../app/" . $class . ".php";

    if(file_exists($arquivo)){
        require_once $arquivo;
    }

});

use Core\Router;

$router = new Router();

$router->dispatch();