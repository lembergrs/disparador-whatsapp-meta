<?php

session_start();

require_once '../vendor/autoload.php';

require_once '../config/config.php';

spl_autoload_register(function ($class) {

    $class = str_replace('\\', '/', $class);

    $arquivo = "../app/" . $class . ".php";

    if(file_exists($arquivo)){
        require_once $arquivo;
    }

});

use Core\Router;

$router = new Router();

$router->dispatch();