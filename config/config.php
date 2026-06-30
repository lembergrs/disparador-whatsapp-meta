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

// Dias de tolerância financeira após vencimento antes do bloqueio operacional.
defined('FINANCEIRO_DIAS_TOLERANCIA_VENCIMENTO') || define('FINANCEIRO_DIAS_TOLERANCIA_VENCIMENTO', 5);

// App Secret usado para validar X-Hub-Signature-256 do webhook da Meta.
defined('META_APP_ID') || define('META_APP_ID', env_valor('META_APP_ID', ''));
defined('META_APP_SECRET') || define('META_APP_SECRET', env_valor('META_APP_SECRET', ''));
defined('META_CONFIGURATION_ID') || define('META_CONFIGURATION_ID', env_valor('META_CONFIGURATION_ID', ''));
defined('META_VERIFY_TOKEN') || define('META_VERIFY_TOKEN', env_valor('META_VERIFY_TOKEN', ''));
defined('META_EMBEDDED_SIGNUP_REDIRECT_URI') || define('META_EMBEDDED_SIGNUP_REDIRECT_URI', env_valor('META_EMBEDDED_SIGNUP_REDIRECT_URI', ''));
defined('META_GRAPH_VERSION') || define('META_GRAPH_VERSION', env_valor('META_GRAPH_VERSION', ''));

define('UPLOADS_PUBLIC_BASE_URL', rtrim(env_valor('UPLOADS_PUBLIC_BASE_URL', BASE_URL . '/uploads'), '/'));

$asaasEnv = strtolower(trim((string) env_valor('ASAAS_ENV', 'sandbox')));
$asaasBaseUrlSandbox = env_valor('ASAAS_API_BASE_URL_SANDBOX', 'https://sandbox.asaas.com/api/v3');
$asaasBaseUrlProduction = env_valor('ASAAS_API_BASE_URL_PRODUCTION', 'https://api.asaas.com/v3');

// Base segura da integração Asaas. Esta etapa não cria clientes nem cobranças.
defined('ASAAS_ENV') || define('ASAAS_ENV', $asaasEnv);
defined('ASAAS_API_KEY') || define('ASAAS_API_KEY', env_valor('ASAAS_API_KEY', ''));
defined('ASAAS_WEBHOOK_TOKEN') || define('ASAAS_WEBHOOK_TOKEN', env_valor('ASAAS_WEBHOOK_TOKEN', ''));
defined('ASAAS_API_BASE_URL') || define(
    'ASAAS_API_BASE_URL',
    $asaasEnv === 'production' ? $asaasBaseUrlProduction : $asaasBaseUrlSandbox
);

// TODO: criar cliente no Asaas.
// TODO: criar cobrança no Asaas.
// TODO: salvar asaas_customer_id.
// TODO: salvar asaas_payment_id.

defined('DB_HOST') || define('DB_HOST', env_valor('DB_HOST'));
defined('DB_NAME') || define('DB_NAME', env_valor('DB_NAME'));
defined('DB_USER') || define('DB_USER', env_valor('DB_USER'));
defined('DB_PASS') || define('DB_PASS', env_valor('DB_PASS', ''));
defined('DB_PORT') || define('DB_PORT', env_valor('DB_PORT', '3306'));
