<?php

require_once __DIR__ . '/env.php';

defined('APP_ENV') || define('APP_ENV', app_env());

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

defined('RECAPTCHA_SITE_KEY') || define('RECAPTCHA_SITE_KEY', env_valor('RECAPTCHA_SITE_KEY', ''));
defined('RECAPTCHA_SECRET_KEY') || define('RECAPTCHA_SECRET_KEY', env_valor('RECAPTCHA_SECRET_KEY', ''));

// Taxa segura inicial de disparos para WhatsApp Cloud API.
// Ajuste conforme qualidade, limites e aprovação da conta na Meta.
defined('WHATSAPP_ENVIOS_POR_SEGUNDO') || define('WHATSAPP_ENVIOS_POR_SEGUNDO', 5);
defined('WHATSAPP_PAUSA_RATE_LIMIT_SEGUNDOS') || define('WHATSAPP_PAUSA_RATE_LIMIT_SEGUNDOS', 5);
<<<<<<< HEAD

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
=======
>>>>>>> 891617e7af133d0d629f77617230112bf4fa196b

// Dias de tolerância financeira após vencimento antes do bloqueio operacional.
defined('FINANCEIRO_DIAS_TOLERANCIA_VENCIMENTO') || define('FINANCEIRO_DIAS_TOLERANCIA_VENCIMENTO', 5);

// App Secret usado para validar X-Hub-Signature-256 do webhook da Meta.
defined('META_APP_SECRET') || define('META_APP_SECRET', env_valor('META_APP_SECRET', ''));


defined('DB_HOST') || define('DB_HOST', env_valor('DB_HOST', 'localhost'));
defined('DB_NAME') || define('DB_NAME', env_valor('DB_NAME', 'whatsapp_disparador'));
defined('DB_USER') || define('DB_USER', env_valor('DB_USER', 'root'));
defined('DB_PASS') || define('DB_PASS', env_valor('DB_PASS', ''));
defined('DB_PORT') || define('DB_PORT', env_valor('DB_PORT', '3306'));
