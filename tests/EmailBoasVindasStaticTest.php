<?php
$root = dirname(__DIR__);
$site = file_get_contents($root . '/app/Controllers/SiteController.php');
$config = file_get_contents($root . '/config/config.php');
$env = file_get_contents($root . '/.env.example');
$migration = file_get_contents($root . '/database/migrations/20260717_create_notificacoes_transacionais.sql');
$boasVindas = file_get_contents($root . '/app/Services/Email/EmailBoasVindasService.php');
$transacional = file_get_contents($root . '/app/Services/Email/EmailTransacionalService.php');
$composer = file_get_contents($root . '/composer.json');

function emailStaticAssert($cond, $msg){ if(!$cond){ fwrite(STDERR, "FAIL: {$msg}\n"); exit(1); } }
function emailStaticHas($h, $n, $m){ emailStaticAssert(strpos($h, $n) !== false, $m . "\nMissing: {$n}"); }
function emailStaticNot($h, $n, $m){ emailStaticAssert(strpos($h, $n) === false, $m . "\nUnexpected: {$n}"); }

emailStaticHas($site, '$db->commit();', 'cadastro confirma transação antes do envio');
emailStaticHas($site, 'enviarEmailBoasVindasCadastro', 'controller chama serviço de boas-vindas após cadastro');
emailStaticHas($site, 'NotificacaoService', 'controller usa infraestrutura central de notificações');
emailStaticHas($site, 'EventoNotificacao::BOAS_VINDAS', 'controller dispara evento de boas-vindas');
emailStaticNot($site, 'isSMTP(', 'controller não configura PHPMailer');
emailStaticNot($site, 'MAIL_PASSWORD', 'controller não conhece senha SMTP');
emailStaticHas($site, 'Cadastro realizado com sucesso. Você já pode acessar sua conta.', 'falha de e-mail mantém mensagem neutra');
emailStaticHas($site, 'Enviamos para seu e-mail os próximos passos', 'sucesso pode informar envio confirmado');

foreach(['MAIL_HOST','MAIL_PORT','MAIL_USERNAME','MAIL_PASSWORD','MAIL_ENCRYPTION','MAIL_FROM_ADDRESS','MAIL_FROM_NAME','MAIL_REPLY_TO_ADDRESS','MAIL_REPLY_TO_NAME','MAIL_TIMEOUT'] as $key){
    emailStaticHas($config, $key, "config define {$key}");
    emailStaticHas($env, $key . '=', ".env.example documenta {$key}");
}

emailStaticHas($migration, 'CREATE TABLE IF NOT EXISTS notificacoes_transacionais', 'migration cria tabela transacional');
emailStaticHas($migration, 'UNIQUE KEY uk_notificacoes_chave_idempotencia (NOT_ChaveIdempotencia)', 'migration cria unique de idempotência');
emailStaticHas($migration, "NOT_Tipo", 'migration inclui tipo');
emailStaticHas($migration, "NOT_Canal", 'migration inclui canal');
emailStaticHas($migration, "NOT_Status ENUM('pendente','processando','enviado','erro_temporario','erro_definitivo')", 'migration inclui status esperados');

emailStaticHas($boasVindas, 'email:boas_vindas:cliente:', 'service usa chave idempotente por cliente');
emailStaticHas($boasVindas, 'Bem-vindo ao Disparador.net — veja os próximos passos', 'assunto correto');
emailStaticHas($boasVindas, 'período de avaliação começa somente após a conexão', 'não informa trial iniciado no cadastro');
emailStaticHas($boasVindas, 'htmlspecialchars($nome', 'HTML escapa variáveis');
emailStaticHas($boasVindas, 'storage/logs', 'log em storage/logs');
emailStaticNot($boasVindas, 'COB_Status', 'não implementa pagamento confirmado');
emailStaticNot($boasVindas, 'Evolution', 'não implementa Evolution API');
emailStaticNot($boasVindas, 'Cloud API', 'não implementa Cloud API');

emailStaticHas($transacional, 'filter_var($destinatario, FILTER_VALIDATE_EMAIL)', 'valida destinatário antes de SMTP');
emailStaticHas($transacional, 'PHPMailer\\\\PHPMailer\\\\PHPMailer', 'usa PHPMailer quando disponível');
emailStaticHas($transacional, 'Timeout', 'configura timeout');
emailStaticHas($transacional, 'password|senha|token|secret|authorization', 'sanitiza erros sensíveis');
emailStaticHas($composer, 'phpmailer/phpmailer', 'composer declara PHPMailer');

echo "Email boas-vindas static tests passed\n";
