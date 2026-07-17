<?php
$root = dirname(__DIR__);
$login = file_get_contents($root . '/app/Controllers/LoginController.php');
$loginView = file_get_contents($root . '/app/Views/auth/login.php');
$recView = file_get_contents($root . '/app/Views/auth/recuperar_senha.php');
$redView = file_get_contents($root . '/app/Views/auth/redefinir_senha.php');
$service = file_get_contents($root . '/app/Services/RecuperacaoSenhaService.php');
$model = file_get_contents($root . '/app/Models/RecuperacaoSenha.php');
$email = file_get_contents($root . '/app/Services/Email/EmailRecuperacaoSenhaService.php');
$boas = file_get_contents($root . '/app/Services/Email/EmailBoasVindasService.php');
$trans = file_get_contents($root . '/app/Services/Email/EmailTransacionalService.php');
$migration = file_get_contents($root . '/database/migrations/20260717_create_recuperacoes_senha.sql');

function recStaticAssert($cond, $msg){ if(!$cond){ fwrite(STDERR, "FAIL: {$msg}\n"); exit(1); } }
function recStaticHas($h,$n,$m){ recStaticAssert(strpos($h,$n)!==false,$m."\nMissing: {$n}"); }
function recStaticNot($h,$n,$m){ recStaticAssert(strpos($h,$n)===false,$m."\nUnexpected: {$n}"); }

recStaticHas($trans, 'namespace Services\\Email;', 'EmailTransacionalService no namespace novo');
recStaticHas($boas, 'namespace Services\\Email;', 'EmailBoasVindasService no namespace novo');
recStaticHas($email, 'namespace Services\\Email;', 'EmailRecuperacaoSenhaService no namespace novo');
recStaticHas($login, 'function recuperarSenha()', 'controller possui recuperarSenha');
recStaticHas($login, 'function enviarRecuperacao()', 'controller possui enviarRecuperacao');
recStaticHas($login, 'function redefinirSenha()', 'controller possui redefinirSenha');
recStaticHas($login, 'function salvarNovaSenha()', 'controller possui salvarNovaSenha');
recStaticHas($login, 'Csrf::exigirPost();', 'POSTs exigem CSRF');
recStaticHas($loginView, 'login/recuperarSenha', 'login tem link de recuperação');
recStaticHas($loginView, 'Esqueceu sua senha?', 'texto do link correto');
recStaticHas($recView, 'Vamos localizar sua conta pelo e-mail informado', 'view solicitação tem texto neutro');
recStaticHas($recView, 'login/enviarRecuperacao', 'form posta para enviarRecuperacao');
recStaticHas($recView, 'Csrf::input()', 'form solicitação tem CSRF');
recStaticHas($redView, 'token_recuperacao', 'token de recuperação é separado do CSRF');
recStaticHas($redView, 'Csrf::input()', 'form redefinição tem CSRF');
recStaticHas($service, 'bin2hex(random_bytes(32))', 'gera token criptograficamente seguro');
recStaticHas($service, "hash('sha256', $" . "token)", 'salva hash do token');
recStaticHas($service, 'MINUTOS_VALIDADE = 30', 'token vale 30 minutos');
recStaticHas($service, 'contarRecentesPorUsuario', 'limita solicitações efetivas por e-mail');
recStaticHas($service, 'contarRecentesPorIp', 'limita solicitações por IP');
recStaticHas($service, 'SenhaForteValidator::forte', 'reutiliza regra forte de senha');
recStaticHas($service, 'password_hash', 'senha gravada com password_hash');
recStaticHas($model, 'RSE_TokenHash', 'model usa token hash');
recStaticNot($model, 'RSE_Token ', 'model não possui token puro');
recStaticHas($migration, 'CREATE TABLE IF NOT EXISTS recuperacoes_senha', 'migration cria recuperacoes_senha');
recStaticHas($migration, 'RSE_TokenHash CHAR(64)', 'migration armazena hash');
recStaticHas($migration, 'idx_recuperacoes_token_hash', 'migration indexa hash');
recStaticHas($email, 'email_recuperacao_senha', 'notificação usa tipo correto');
recStaticHas($email, 'Recuperação de senha - Disparador.net', 'assunto correto');
recStaticHas($email, 'Clique no botão abaixo e siga as orientações para redefinir sua senha.', 'concordância correta');
recStaticNot($email, 'sigas', 'não usa sigas');
recStaticNot($email, 'NOT_ChaveIdempotencia = $link', 'não persiste URL com token');
recStaticAssert(!file_exists($root . '/app/Services/EmailTransacionalService.php') && !file_exists($root . '/app/Services/EmailBoasVindasService.php'), 'arquivos antigos de e-mail removidos');

echo "Recuperação de senha static tests passed\n";
