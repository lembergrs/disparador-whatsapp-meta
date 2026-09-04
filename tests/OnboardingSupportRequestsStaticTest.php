<?php

$assert = function($condition, $message){
    if(!$condition){
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$root = dirname(__DIR__);
$model = file_get_contents($root . '/app/Models/OnboardingSuporteSolicitacao.php');
$cliente = file_get_contents($root . '/app/Controllers/OnboardingSuporteController.php');
$admin = file_get_contents($root . '/app/Controllers/OnboardingSuporteAdminController.php');
$view = file_get_contents($root . '/app/Views/dashboard/_onboarding.php');
$adminView = file_get_contents($root . '/app/Views/onboarding_suporte_admin/index.php');
$auth = file_get_contents($root . '/app/Core/Auth.php');
$master = file_get_contents($root . '/app/Views/layouts/master.php');
$migration = file_get_contents($root . '/database/migrations/20260904_create_onboarding_support_requests.sql');

$assert(strpos($migration, 'CREATE TABLE IF NOT EXISTS onboarding_suporte_solicitacoes') !== false, 'migration da solicitação deve existir');
$assert(strpos($migration, 'CLI_ID INT NOT NULL') !== false && strpos($migration, 'MTA_ID INT NULL') !== false, 'escopo deve preservar CLI_ID e permitir pré-conexão sem MTA_ID');
$assert(strpos($model, "AND MTA_ID = ?") !== false && strpos($model, "AND MTA_Ativo = 'S'") !== false, 'conta deve ser validada no mesmo cliente');
$assert(strpos($model, "OSS_Status IN ('aberta','em_atendimento')") !== false, 'duplicidade de solicitação ativa deve ser bloqueada');
$assert(strpos($cliente, 'Auth::cliente()') !== false && strpos($cliente, 'validarCsrfPost()') !== false, 'cliente deve estar autenticado e POST protegido');
$assert(strpos($cliente, 'Auth::isImpersonating()') !== false, 'modo suporte administrativo não deve criar solicitação em nome do cliente');
$assert(strpos($admin, 'Auth::admin()') !== false && strpos($admin, 'validarCsrfPost()') !== false, 'mudança administrativa deve exigir admin e CSRF');
$assert(strpos($view, 'onboardingSuporte/solicitar') !== false, 'onboarding deve abrir solicitação estruturada');
$assert(strpos($view, 'name="periodo"') !== false && strpos($view, 'name="horario"') !== false, 'cliente deve informar preferência de atendimento');
$assert(strpos($adminView, 'onboardingSuporteAdmin/alterarStatus') !== false && strpos($adminView, 'https://wa.me/') !== false, 'admin deve acompanhar status e contatar pelo WhatsApp');
$assert(strpos($auth, "'onboardingSuporte'") !== false, 'rota de ajuda deve permanecer acessível durante pré-trial/bloqueio');
$assert(strpos($master, 'onboardingSuporteAdmin') !== false, 'menu administrativo deve expor solicitações');

echo "Onboarding support request static checks passed\n";
