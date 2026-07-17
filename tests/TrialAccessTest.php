<?php

function assertContainsTrial($needle, $haystack, $message)
{
    if(strpos($haystack, $needle) === false){
        throw new RuntimeException($message);
    }
}

function assertNotContainsTrial($needle, $haystack, $message)
{
    if(strpos($haystack, $needle) !== false){
        throw new RuntimeException($message);
    }
}

$auth = file_get_contents(__DIR__ . '/../app/Core/Auth.php');
$cliente = file_get_contents(__DIR__ . '/../app/Models/Cliente.php');
$configuracao = file_get_contents(__DIR__ . '/../app/Controllers/ConfiguracaoController.php');
$financeiro = file_get_contents(__DIR__ . '/../app/Controllers/FinanceiroController.php');
$dashboard = file_get_contents(__DIR__ . '/../app/Views/dashboard/index.php');
$menu = file_get_contents(__DIR__ . '/../app/Views/layouts/master.php');

assertContainsTrial('const DIAS_AVALIACAO = 7', $auth, 'Trial deve manter 7 dias.');
assertContainsTrial('const LIMITE_MENSAGENS_AVALIACAO = 200', $auth, 'Trial deve manter limite de 200 mensagens.');
assertContainsTrial('public static function clienteEmPreTrial()', $auth, 'Auth deve explicitar estado pre-trial.');
assertContainsTrial('public static function trialEncerradoCliente()', $auth, 'Auth deve explicitar trial encerrado.');
assertContainsTrial('return self::clienteEmPreTrial();', $auth, 'A conexão Meta deve ser liberada pelo estado pre-trial.');
assertContainsTrial("\$controller == 'configuracao'", $auth, 'Rotas de configuração/Meta devem ser avaliadas no bloqueio financeiro.');
assertContainsTrial('Seu período de avaliação terminou. Escolha ou regularize um plano para continuar utilizando a plataforma.', $auth, 'Mensagem de trial encerrado deve ser clara.');
assertContainsTrial('Conecte seu número do WhatsApp para iniciar seu período de avaliação', $auth, 'Mensagem de pre-trial deve orientar conexão Meta.');
assertNotContainsTrial('clienteEmToleranciaFinanceira($usuario', substr($auth, strpos($auth, 'public static function clienteLiberado')), 'Liberação operacional não deve depender apenas de tolerância financeira.');

assertContainsTrial("CLI_StatusCadastro = 'ativo'", $cliente, 'Início do trial deve exigir cliente ativo.');
assertContainsTrial("CLI_StatusPagamento = 'pendente'", $cliente, 'Início do trial deve exigir pagamento pendente.');
assertContainsTrial("CLI_DataLiberacao IS NULL OR CLI_DataLiberacao = ''", $cliente, 'Início do trial deve ser idempotente.');
assertContainsTrial('trial_iniciado', $cliente, 'Início do trial deve ter registro seguro.');

assertContainsTrial("\$statusConexao === 'conectado'", $configuracao, 'Trial só deve iniciar após conexão Meta confirmada.');
assertContainsTrial('iniciarTrialSePendente($clienteId)', $configuracao, 'Fluxo de Embedded Signup deve iniciar trial após sucesso.');

$trechoPlano = substr($financeiro, strpos($financeiro, 'public function escolherPlano'));
assertContainsTrial("CLI_StatusPagamento = 'pendente'", $trechoPlano, 'Seleção de plano deve manter pagamento pendente.');
assertNotContainsTrial('CLI_DataLiberacao', $trechoPlano, 'Seleção de plano não deve iniciar trial.');
assertNotContainsTrial("CLI_StatusPagamento = 'pago'", $trechoPlano, 'Seleção de plano não deve marcar cliente como pago.');

assertContainsTrial('Conecte seu número do WhatsApp para iniciar seu período de avaliação de 7 dias ou até 200 mensagens.', $dashboard, 'Dashboard deve orientar pre-trial.');
assertContainsTrial('Período de avaliação ativo.', $dashboard, 'Dashboard deve indicar trial ativo.');
assertContainsTrial('mensagens_restantes', $dashboard, 'Dashboard deve mostrar mensagens restantes no trial.');
assertContainsTrial('dias_restantes', $dashboard, 'Dashboard deve mostrar dias restantes no trial.');
assertContainsTrial('configuracao/meta', $dashboard, 'Dashboard deve levar ao fluxo real de Contas Meta.');

assertContainsTrial('Números WhatsApp', $menu, 'Menu deve manter Contas Meta/Números WhatsApp para clientes.');
assertContainsTrial('$clientePodeConectarMeta', $menu, 'Menu deve liberar Contas Meta no pre-trial.');
assertContainsTrial('configuracao/meta', $menu, 'Menu deve apontar para a rota real de Contas Meta.');

print "Trial access tests passed\n";
