<?php

$root = dirname(__DIR__);
$workflow = file_get_contents($root . '/app/Services/FinanceiroWorkflowService.php');
$recorrencia = file_get_contents($root . '/app/Services/FinanceiroRecorrenciaService.php');
$controllers = [
    'FinanceiroController.php',
    'FinanceiroAdminController.php',
    'AsaasController.php',
    'AssinaturaController.php',
    'ClienteController.php'
];

function arquiteturaAssert($condition, $message)
{
    if(!$condition){
        throw new RuntimeException($message);
    }
}

arquiteturaAssert(strpos($workflow, 'Database') === false, 'workflow não acessa Database');
arquiteturaAssert(strpos($workflow, 'PDO') === false, 'workflow não acessa PDO');
arquiteturaAssert(strpos($workflow, '->prepare(') === false, 'workflow não executa SQL');
arquiteturaAssert(strpos($recorrencia, 'Database') === false, 'recorrência não acessa Database');
arquiteturaAssert(strpos($recorrencia, '->prepare(') === false, 'recorrência não executa SQL');

$conteudoControllers = '';
foreach($controllers as $controller){
    $conteudoControllers .= file_get_contents($root . '/app/Controllers/' . $controller);
}

arquiteturaAssert(substr_count($conteudoControllers, 'FinanceiroWorkflowService') >= 5, 'fluxos financeiros delegam ao workflow');
arquiteturaAssert(!preg_match('/UPDATE\s+(clientes|assinaturas|cobrancas)/i', $conteudoControllers), 'controllers não alteram estados financeiros por SQL');
arquiteturaAssert(strpos(file_get_contents($root . '/app/Controllers/AsaasController.php'), 'processarPagamentoWebhook') !== false, 'webhook delega ao workflow');

echo "FinanceiroWorkflowArchitectureStaticTest OK\n";
