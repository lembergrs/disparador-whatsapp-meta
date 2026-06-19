<?php

$host = $_SERVER['HTTP_HOST'] ?? '';

if ($host === 'disparador.test') {

    define('BASE_URL', 'http://disparador.test');
    define('ASSET_URL', BASE_URL . '/assets');

} else {

    $protocolo =
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            ? 'https'
            : 'http';

    define('BASE_URL', $protocolo . '://' . $host);
    define('ASSET_URL', BASE_URL . '/public/assets');

}

define('RECAPTCHA_SITE_KEY', '6LdDLBQtAAAAAEp5UhSPe_cikIC5u3VDrtq1-rse');
define('RECAPTCHA_SECRET_KEY', '6LdDLBQtAAAAAPB-YaekMjxjXwJY9V05mMJnUoZG');

// Taxa segura inicial de disparos para WhatsApp Cloud API.
// Ajuste conforme qualidade, limites e aprovação da conta na Meta.
defined('WHATSAPP_ENVIOS_POR_SEGUNDO') || define('WHATSAPP_ENVIOS_POR_SEGUNDO', 5);
defined('WHATSAPP_PAUSA_RATE_LIMIT_SEGUNDOS') || define('WHATSAPP_PAUSA_RATE_LIMIT_SEGUNDOS', 5);

// Dias de tolerância financeira após vencimento antes do bloqueio operacional.
defined('FINANCEIRO_DIAS_TOLERANCIA_VENCIMENTO') || define('FINANCEIRO_DIAS_TOLERANCIA_VENCIMENTO', 5);

// App Secret usado para validar X-Hub-Signature-256 do webhook da Meta.
defined('META_APP_SECRET') || define('META_APP_SECRET', getenv('META_APP_SECRET') ?: '');


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
