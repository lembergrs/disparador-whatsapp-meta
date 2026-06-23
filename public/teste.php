<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/config.php';

configurarErrosAplicacao();

require_once __DIR__ . '/../vendor/autoload.php';

echo 'AUTOLOAD OK<br>';

$router = new Core\Router();

echo 'ROUTER OK<br>';