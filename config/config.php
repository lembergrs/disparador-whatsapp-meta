<?php

require_once __DIR__ . '/env.php';

defined('APP_ENV') || define('APP_ENV', app_env());

defined('APP_TIMEZONE') || define('APP_TIMEZONE', env_valor('APP_TIMEZONE', 'America/Sao_Paulo'));

// Identificadores públicos de Analytics. O GA4 é configurado somente dentro do GTM.
defined('GOOGLE_TAG_MANAGER_ID') || define('GOOGLE_TAG_MANAGER_ID', env_valor('GOOGLE_TAG_MANAGER_ID', 'GTM-5BV2SLDR'));
defined('GOOGLE_ANALYTICS_MEASUREMENT_ID') || define('GOOGLE_ANALYTICS_MEASUREMENT_ID', env_valor('GOOGLE_ANALYTICS_MEASUREMENT_ID', 'G-H6JP7C3CHG'));

date_default_timezone_set(APP_TIMEZONE);

$host = $_SERVER['HTTP_HOST'] ?? '';

if ($host === 'disparador.test') {

    define('BASE_URL', 'http://disparador.test');
    define('ASSET_URL', BASE_URL . '/assets');

} else {

    $protocolo =
        (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        )
            ? 'https'
            : 'http';

    define('BASE_URL', $protocolo . '://' . $host);
    define('ASSET_URL', BASE_URL . '/public/assets');

}

defined('RECAPTCHA_SITE_KEY') || define('RECAPTCHA_SITE_KEY', env_valor('RECAPTCHA_SITE_KEY', ''));
defined('RECAPTCHA_SECRET_KEY') || define('RECAPTCHA_SECRET_KEY', env_valor('RECAPTCHA_SECRET_KEY', ''));

// Configurações de e-mail transacional (SMTP). Credenciais reais devem ficar apenas no ambiente/.env.
defined('MAIL_HOST') || define('MAIL_HOST', env_valor('MAIL_HOST', ''));
defined('MAIL_PORT') || define('MAIL_PORT', (int) env_valor('MAIL_PORT', 587));
defined('MAIL_USERNAME') || define('MAIL_USERNAME', env_valor('MAIL_USERNAME', ''));
defined('MAIL_PASSWORD') || define('MAIL_PASSWORD', env_valor('MAIL_PASSWORD', ''));
defined('MAIL_ENCRYPTION') || define('MAIL_ENCRYPTION', env_valor('MAIL_ENCRYPTION', 'tls'));
defined('MAIL_FROM_ADDRESS') || define('MAIL_FROM_ADDRESS', env_valor('MAIL_FROM_ADDRESS', ''));
defined('MAIL_FROM_NAME') || define('MAIL_FROM_NAME', env_valor('MAIL_FROM_NAME', 'Disparador.net'));
defined('MAIL_REPLY_TO_ADDRESS') || define('MAIL_REPLY_TO_ADDRESS', env_valor('MAIL_REPLY_TO_ADDRESS', ''));
defined('MAIL_REPLY_TO_NAME') || define('MAIL_REPLY_TO_NAME', env_valor('MAIL_REPLY_TO_NAME', 'Suporte Disparador.net'));
defined('MAIL_TIMEOUT') || define('MAIL_TIMEOUT', (int) env_valor('MAIL_TIMEOUT', 10));

// Taxa segura inicial de disparos para WhatsApp Cloud API.
// Ajuste conforme qualidade, limites e aprovação da conta na Meta.
defined('WHATSAPP_ENVIOS_POR_SEGUNDO') || define('WHATSAPP_ENVIOS_POR_SEGUNDO', 5);
defined('WHATSAPP_PAUSA_RATE_LIMIT_SEGUNDOS') || define('WHATSAPP_PAUSA_RATE_LIMIT_SEGUNDOS', 5);
defined('WORKER_MAX_ATTEMPTS') || define('WORKER_MAX_ATTEMPTS', 5);
defined('WORKER_RETRY_DELAY_SECONDS') || define('WORKER_RETRY_DELAY_SECONDS', 30);
defined('WORKER_RETRY_MAX_DELAY_SECONDS') || define('WORKER_RETRY_MAX_DELAY_SECONDS', 1800);
defined('WORKER_RETRY_JITTER_SECONDS') || define('WORKER_RETRY_JITTER_SECONDS', 15);
defined('WORKER_PROCESSING_TIMEOUT_MINUTES') || define('WORKER_PROCESSING_TIMEOUT_MINUTES', 15);
defined('WORKER_DAEMON_IDLE_SLEEP_SECONDS') || define('WORKER_DAEMON_IDLE_SLEEP_SECONDS', (float) env_valor('WORKER_DAEMON_IDLE_SLEEP_SECONDS', 5));
defined('WORKER_DAEMON_BUSY_SLEEP_SECONDS') || define('WORKER_DAEMON_BUSY_SLEEP_SECONDS', (float) env_valor('WORKER_DAEMON_BUSY_SLEEP_SECONDS', 1));
defined('WORKER_DAEMON_MAX_SLEEP_SECONDS') || define('WORKER_DAEMON_MAX_SLEEP_SECONDS', (float) env_valor('WORKER_DAEMON_MAX_SLEEP_SECONDS', 60));
defined('WORKER_DAEMON_SLEEP_GRANULARITY_SECONDS') || define('WORKER_DAEMON_SLEEP_GRANULARITY_SECONDS', (float) env_valor('WORKER_DAEMON_SLEEP_GRANULARITY_SECONDS', 1));
defined('WORKER_DAEMON_MAX_RUNTIME_SECONDS') || define('WORKER_DAEMON_MAX_RUNTIME_SECONDS', (float) env_valor('WORKER_DAEMON_MAX_RUNTIME_SECONDS', 0));
defined('WORKER_DAEMON_MAX_CYCLES') || define('WORKER_DAEMON_MAX_CYCLES', (int) env_valor('WORKER_DAEMON_MAX_CYCLES', 0));
defined('WORKER_DAEMON_MAX_MEMORY_MB') || define('WORKER_DAEMON_MAX_MEMORY_MB', (float) env_valor('WORKER_DAEMON_MAX_MEMORY_MB', 0));
defined('WORKER_DAEMON_HEARTBEAT_SECONDS') || define('WORKER_DAEMON_HEARTBEAT_SECONDS', (float) env_valor('WORKER_DAEMON_HEARTBEAT_SECONDS', 30));
defined('WORKER_DAEMON_HEARTBEAT_FILE') || define('WORKER_DAEMON_HEARTBEAT_FILE', env_valor('WORKER_DAEMON_HEARTBEAT_FILE', __DIR__ . '/../storage/logs/worker-daemon.jsonl'));
defined('WORKER_DAEMON_LOCK_FILE') || define('WORKER_DAEMON_LOCK_FILE', env_valor('WORKER_DAEMON_LOCK_FILE', __DIR__ . '/../storage/worker-daemon.lock'));
defined('WORKER_DAEMON_ID') || define('WORKER_DAEMON_ID', env_valor('WORKER_DAEMON_ID', ''));
defined('WORKER_DAEMON_LIMITE_CAMPANHAS') || define('WORKER_DAEMON_LIMITE_CAMPANHAS', (int) env_valor('WORKER_DAEMON_LIMITE_CAMPANHAS', 50));
defined('WORKER_DAEMON_LIMITE_DISPARO_MANUAL') || define('WORKER_DAEMON_LIMITE_DISPARO_MANUAL', (int) env_valor('WORKER_DAEMON_LIMITE_DISPARO_MANUAL', 20));

// Task Scheduler genérico. Nenhum fluxo legado é ativado por estas opções.
defined('TASK_SCHEDULER_BATCH_SIZE') || define('TASK_SCHEDULER_BATCH_SIZE', max(1, (int) env_valor('TASK_SCHEDULER_BATCH_SIZE', 50)));
defined('TASK_SCHEDULER_LEASE_MINUTES') || define('TASK_SCHEDULER_LEASE_MINUTES', max(1, (int) env_valor('TASK_SCHEDULER_LEASE_MINUTES', 15)));
defined('TASK_SCHEDULER_LOCK_FILE') || define('TASK_SCHEDULER_LOCK_FILE', env_valor('TASK_SCHEDULER_LOCK_FILE', __DIR__ . '/../storage/task-scheduler.lock'));
defined('TASK_SCHEDULER_LOG_FILE') || define('TASK_SCHEDULER_LOG_FILE', env_valor('TASK_SCHEDULER_LOG_FILE', __DIR__ . '/../storage/logs/task-scheduler.log'));

// Dias de tolerância financeira após vencimento antes do bloqueio operacional.
defined('FINANCEIRO_DIAS_TOLERANCIA_VENCIMENTO') || define('FINANCEIRO_DIAS_TOLERANCIA_VENCIMENTO', 5);

// App Secret usado para validar X-Hub-Signature-256 do webhook da Meta.
defined('META_APP_ID') || define('META_APP_ID', env_valor('META_APP_ID', ''));
defined('META_APP_SECRET') || define('META_APP_SECRET', env_valor('META_APP_SECRET', ''));
defined('META_CONFIGURATION_ID') || define('META_CONFIGURATION_ID', env_valor('META_CONFIGURATION_ID', ''));
defined('META_VERIFY_TOKEN') || define('META_VERIFY_TOKEN', env_valor('META_VERIFY_TOKEN', ''));
defined('META_EMBEDDED_SIGNUP_REDIRECT_URI') || define('META_EMBEDDED_SIGNUP_REDIRECT_URI', env_valor('META_EMBEDDED_SIGNUP_REDIRECT_URI', ''));
defined('META_GRAPH_VERSION') || define('META_GRAPH_VERSION', env_valor('META_GRAPH_VERSION', ''));
defined('META_COEXISTENCE_ENABLED') || define(
    'META_COEXISTENCE_ENABLED',
    filter_var(env_valor('META_COEXISTENCE_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN)
);
defined('WHATSAPP_INSTITUCIONAL_PHONE_NUMBER_ID') || define('WHATSAPP_INSTITUCIONAL_PHONE_NUMBER_ID', env_valor('WHATSAPP_INSTITUCIONAL_PHONE_NUMBER_ID', ''));
defined('WHATSAPP_INSTITUCIONAL_WABA_ID') || define('WHATSAPP_INSTITUCIONAL_WABA_ID', env_valor('WHATSAPP_INSTITUCIONAL_WABA_ID', ''));
defined('WHATSAPP_INSTITUCIONAL_ACCESS_TOKEN') || define('WHATSAPP_INSTITUCIONAL_ACCESS_TOKEN', env_valor('WHATSAPP_INSTITUCIONAL_ACCESS_TOKEN', ''));
defined('WHATSAPP_INSTITUCIONAL_API_VERSION') || define('WHATSAPP_INSTITUCIONAL_API_VERSION', env_valor('WHATSAPP_INSTITUCIONAL_API_VERSION', META_GRAPH_VERSION ?: 'v23.0'));
defined('WHATSAPP_INSTITUCIONAL_IDIOMA') || define('WHATSAPP_INSTITUCIONAL_IDIOMA', env_valor('WHATSAPP_INSTITUCIONAL_IDIOMA', 'pt_BR'));
defined('WHATSAPP_INSTITUCIONAL_TIMEOUT') || define('WHATSAPP_INSTITUCIONAL_TIMEOUT', max(1, (int) env_valor('WHATSAPP_INSTITUCIONAL_TIMEOUT', 15)));

$uploadsPublicPath = rtrim(env_valor('UPLOADS_PUBLIC_PATH', '/uploads'), '/');
if($uploadsPublicPath === ''){
    $uploadsPublicPath = '/uploads';
}
if($uploadsPublicPath[0] !== '/'){
    $uploadsPublicPath = '/' . $uploadsPublicPath;
}
defined('UPLOADS_PUBLIC_PATH') || define('UPLOADS_PUBLIC_PATH', $uploadsPublicPath);

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


// Configurações locais para futura integração NFS-e RL2.
// Esta etapa não chama a API e não carrega certificado; apenas referencia variáveis de ambiente.
defined('NFSE_API_BASE_URL') || define('NFSE_API_BASE_URL', rtrim(env_valor('NFSE_API_BASE_URL', 'https://api.disparador.net'), '/'));
defined('NFSE_API_AUTH_TOKEN') || define('NFSE_API_AUTH_TOKEN', env_valor('NFSE_API_AUTH_TOKEN', ''));
defined('NFSE_PRESTADOR_CNPJ') || define('NFSE_PRESTADOR_CNPJ', env_valor('NFSE_PRESTADOR_CNPJ', ''));
defined('NFSE_PRESTADOR_IM') || define('NFSE_PRESTADOR_IM', env_valor('NFSE_PRESTADOR_IM', ''));
defined('NFSE_PRESTADOR_OP_SIMPLES') || define('NFSE_PRESTADOR_OP_SIMPLES', env_valor('NFSE_PRESTADOR_OP_SIMPLES', ''));
defined('NFSE_LOCAL_EMISSAO_IBGE') || define('NFSE_LOCAL_EMISSAO_IBGE', env_valor('NFSE_LOCAL_EMISSAO_IBGE', ''));
defined('NFSE_DPS_SERIE') || define('NFSE_DPS_SERIE', env_valor('NFSE_DPS_SERIE', '900'));
defined('NFSE_CODIGO_TRIBUTACAO_NACIONAL') || define('NFSE_CODIGO_TRIBUTACAO_NACIONAL', env_valor('NFSE_CODIGO_TRIBUTACAO_NACIONAL', ''));
defined('NFSE_DESCRICAO_SERVICO') || define('NFSE_DESCRICAO_SERVICO', env_valor('NFSE_DESCRICAO_SERVICO', ''));
defined('NFSE_AMBIENTE') || define('NFSE_AMBIENTE', env_valor('NFSE_AMBIENTE', app_env() === 'production' ? 'production' : 'sandbox'));
defined('NFSE_CERT_PATH') || define('NFSE_CERT_PATH', env_valor('NFSE_CERT_PATH', ''));
defined('NFSE_CERT_PASSWORD') || define('NFSE_CERT_PASSWORD', env_valor('NFSE_CERT_PASSWORD', ''));
defined('NFSE_CONNECT_TIMEOUT') || define('NFSE_CONNECT_TIMEOUT', (int) env_valor('NFSE_CONNECT_TIMEOUT', 10));
defined('NFSE_REQUEST_TIMEOUT') || define('NFSE_REQUEST_TIMEOUT', (int) env_valor('NFSE_REQUEST_TIMEOUT', 30));

// TODO: criar cliente no Asaas.
// TODO: criar cobrança no Asaas.
// TODO: salvar asaas_customer_id.
// TODO: salvar asaas_payment_id.

defined('DB_HOST') || define('DB_HOST', env_valor('DB_HOST'));
defined('DB_NAME') || define('DB_NAME', env_valor('DB_NAME'));
defined('DB_USER') || define('DB_USER', env_valor('DB_USER'));
defined('DB_PASS') || define('DB_PASS', env_valor('DB_PASS', ''));
defined('DB_PORT') || define('DB_PORT', env_valor('DB_PORT', '3306'));
