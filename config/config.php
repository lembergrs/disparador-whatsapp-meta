<?php

$host = $_SERVER['HTTP_HOST'] ?? '';

if ($host === 'disparador.test') {
    define('BASE_URL', 'http://disparador.test');
    define('ASSET_URL', BASE_URL . '/assets');
} else {
    define('BASE_URL', 'https://disparador.rosemegamania.com');
    define('ASSET_URL', BASE_URL . '/public/assets');
}

/*
define('DB_HOST', 'localhost');
define('DB_NAME', 'whatsapp_disparador');
define('DB_USER', 'root'); 
define('DB_PASS', ''); // teste1
define('DB_PORT', '3306');
*/

define('DB_HOST', 'rosemegamania.com');
define('DB_NAME', 'u795697383_disparador');
define('DB_USER', 'u795697383_wpdisp'); 
define('DB_PASS', '4|D|+wRKp@A');
define('DB_PORT', '3306');
