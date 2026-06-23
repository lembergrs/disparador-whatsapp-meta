<?php

<<<<<<< HEAD
=======
require_once __DIR__ . '/config/env.php';

configurarErrosAplicacao();

>>>>>>> 891617e7af133d0d629f77617230112bf4fa196b
$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $https,
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();


/*
|--------------------------------------------------------------------------
| Config
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/config/config.php';

/*
|--------------------------------------------------------------------------
| Composer - se existir
|--------------------------------------------------------------------------
*/

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

/*
|--------------------------------------------------------------------------
| Autoload manual do MVC
|--------------------------------------------------------------------------
*/

spl_autoload_register(function ($class) {

    $baseDir = __DIR__ . '/app/';

    $class = str_replace('\\', '/', $class);

    $file = $baseDir . $class . '.php';

    if (file_exists($file)) {
        require_once $file;
    }

});

/*
|--------------------------------------------------------------------------
| Rota padrão
|--------------------------------------------------------------------------
*/

if (empty($_GET['url'])) {
    $_GET['url'] = 'site';
}

/*
|--------------------------------------------------------------------------
| Router
|--------------------------------------------------------------------------
*/

$router = new Core\Router();
$router->dispatch();