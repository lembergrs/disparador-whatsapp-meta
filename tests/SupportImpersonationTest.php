<?php

define('BASE_URL', 'https://disparador.net');
require_once __DIR__ . '/../app/Core/Session.php';
require_once __DIR__ . '/../app/Core/Csrf.php';
require_once __DIR__ . '/../app/Core/Auth.php';

use Core\Auth;

if(session_status() !== PHP_SESSION_ACTIVE){
    session_start();
}

$assert = function($condition, $message){
    if(!$condition){
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$admin = ['id'=>7, 'nome'=>'Suporte', 'nivel'=>'admin', 'CLI_ID'=>null, 'cliente_id'=>null];
$_SESSION['usuario'] = $admin;

$identidade = [
    'USU_ID'=>31,
    'USU_Nome'=>'Cliente Principal',
    'USU_Nivel'=>'cliente_admin',
    'CLI_ID'=>12,
    'CLI_Nome'=>'Empresa Teste',
    'CLI_StatusPagamento'=>'pendente',
    'CLI_StatusCadastro'=>'ativo',
    'CLI_DataLiberacao'=>null,
    'CLI_DataCadastro'=>'2026-07-01',
    'CLI_Plano_DR'=>null,
    'CMS_MensagensMesAtual'=>0,
    'USU_Senha'=>'nao_deve_ir_para_sessao'
];

Auth::startImpersonation($identidade, 99);
$assert(Auth::isImpersonating(), 'modo suporte deve ficar ativo');
$assert(Auth::usuario()['id'] === 31 && Auth::usuario()['nivel'] === 'cliente_admin', 'identidade ativa deve ser a principal do cliente');
$assert(Auth::usuario()['CLI_ID'] === 12, 'escopo de dados deve usar o cliente impersonado');
$assert(Auth::getOriginalAdmin() === $admin, 'administrador original deve ser preservado');
$assert(!isset($_SESSION['impersonacao']['admin']['senha']) && !isset(Auth::usuario()['USU_Senha']), 'sessão não deve armazenar senha ou hash');

try{
    Auth::startImpersonation($identidade, 100);
    $assert(false, 'impersonação em cascata deveria ser recusada');
}catch(RuntimeException $e){
    $assert(true, 'impersonação em cascata recusada');
}

ob_start();
require __DIR__ . '/../app/Views/components/support_mode_banner.php';
$banner = ob_get_clean();
$assert(strpos($banner, 'MODO SUPORTE') !== false && strpos($banner, 'Empresa Teste') !== false, 'faixa deve identificar o cliente');
$assert(strpos($banner, 'suporte/encerrar') !== false && strpos($banner, 'csrf_token') !== false, 'retorno deve usar POST protegido por CSRF');

Auth::stopImpersonation();
$assert(!Auth::isImpersonating(), 'modo suporte deve ser removido no retorno');
$assert(Auth::usuario() === $admin, 'administrador deve ser integralmente restaurado');

$auth = file_get_contents(__DIR__ . '/../app/Core/Auth.php');
$suporte = file_get_contents(__DIR__ . '/../app/Controllers/SuporteController.php');
$auditoria = file_get_contents(__DIR__ . '/../app/Models/SuporteAcesso.php');
$usuarioController = file_get_contents(__DIR__ . '/../app/Controllers/UsuarioController.php');
$contaController = file_get_contents(__DIR__ . '/../app/Controllers/ContaController.php');
$loginController = file_get_contents(__DIR__ . '/../app/Controllers/LoginController.php');
$master = file_get_contents(__DIR__ . '/../app/Views/layouts/master.php');
$migration = file_get_contents(__DIR__ . '/../database/migrations/20260727_create_suporte_acessos.sql');

$assert(substr_count($suporte, 'Csrf::exigirPost()') >= 2, 'início e retorno devem exigir CSRF');
$assert(strpos($suporte, 'Auth::admin()') !== false, 'somente administrador pode iniciar');
$assert(strpos($auditoria, "USU_Nivel IN ('cliente_admin', 'cliente')") !== false, 'somente usuário principal pode representar cliente');
$assert(strpos($auditoria, "c.CLI_Ativo = 'S'") !== false && strpos($auditoria, "c.CLI_StatusCadastro = 'ativo'") !== false, 'cliente inativo deve ser recusado');
$assert(substr_count($usuarioController, 'bloquearAcaoSensivelEmImpersonacao') >= 4, 'mutações de usuário devem ser bloqueadas no backend');
$assert(strpos($contaController, 'bloquearAcaoSensivelEmImpersonacao') !== false, 'alteração de senha da conta deve ser bloqueada');
$assert(strpos($loginController, 'Auth::logout()') !== false, 'logout deve encerrar auditoria e toda a sessão');
$assert(strpos($loginController, 'salvarNovaSenha') !== false && strpos($loginController, 'bloquearAcaoSensivelEmImpersonacao') !== false, 'redefinição de senha deve ser bloqueada');
$assert(strpos($master, 'support_mode_banner.php') !== false, 'layout interno deve carregar faixa compartilhada');
$assert(strpos($migration, 'CREATE TABLE IF NOT EXISTS suporte_acessos') !== false && strpos($migration, 'SUA_DataFim DATETIME NULL') !== false, 'script de auditoria deve registrar acessos abertos e encerrados');

echo "Support impersonation checks passed\n";
