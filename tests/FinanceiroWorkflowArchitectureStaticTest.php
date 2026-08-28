<?php

$root = dirname(__DIR__);
$workflow = file_get_contents($root . '/app/Services/FinanceiroWorkflowService.php');
$recorrencia = file_get_contents($root . '/app/Services/FinanceiroRecorrenciaService.php');
$cobranca = file_get_contents($root . '/app/Models/Cobranca.php');
$asaas = file_get_contents($root . '/app/Services/AsaasService.php');
$migration = file_get_contents($root . '/database/migrations/20260719_add_financeiro_idempotency_indexes.sql');
$migrationVencimento = file_get_contents($root . '/database/migrations/20260828_add_cobranca_vencimento_efetivo.sql');
$accessPolicy = file_get_contents($root . '/app/Services/FinanceiroAccessPolicyService.php');
$auth = file_get_contents($root . '/app/Core/Auth.php');
$workerValidator = file_get_contents($root . '/app/Services/WorkerOperationalValidatorService.php');
$config = file_get_contents($root . '/config/config.php');
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
arquiteturaAssert(strpos($migration, 'uk_cobrancas_assinatura_competencia_tipo') !== false, 'recorrência possui constraint única');
arquiteturaAssert(strpos($migration, 'uk_cobranca_eventos_provider_evento') !== false, 'webhook possui constraint única');
arquiteturaAssert(strpos($cobranca, "getCode() === '23000'") !== false, 'model trata disputa de chave única');
arquiteturaAssert(preg_match("/catch\(PDOException.*?buscarPorCompetencia\(/s", $cobranca), 'violação 23000 busca pela chave completa inclusive canceladas');
arquiteturaAssert(strpos($asaas, 'buscarCobrancaPorReferenciaExterna') !== false, 'Asaas permite reconciliação por referência');
arquiteturaAssert(strpos($workflow, '_tentativa_') !== false, 'reprocessamento externo cancelado usa referência versionada determinística');
arquiteturaAssert(strpos($migrationVencimento, 'COB_DataVencimentoEfetivo DATE NULL') !== false, 'migration separa vencimento efetivo sem alterar competência histórica');
arquiteturaAssert(strpos($cobranca, 'COALESCE(COB_DataVencimentoEfetivo, COB_DataVencimento)') !== false, 'vencimentos usam data efetiva com fallback retrocompatível');
arquiteturaAssert(strpos($workflow, 'definirVencimentoEfetivo') !== false && strpos($asaas, "'COB_DataVencimentoEfetivo'") !== false, 'vencimento efetivo é persistido antes de ser enviado ao gateway');
arquiteturaAssert(strpos($accessPolicy, 'buscarObrigacaoVencidaPorAssinatura') !== false && strpos($accessPolicy, "'suspenso'") !== false, 'política central decide inadimplência pelo vínculo atual');
arquiteturaAssert(strpos($auth, 'FinanceiroAccessPolicyService') !== false && strpos($workerValidator, 'FinanceiroAccessPolicyService') !== false, 'Auth e worker compartilham a política financeira');
arquiteturaAssert(strpos($auth, 'clienteEmToleranciaFinanceira') === false && strpos($workerValidator, 'clienteEmToleranciaFinanceira') === false, 'não restam tolerâncias financeiras duplicadas');
arquiteturaAssert(strpos(substr($workflow, strpos($workflow, 'public function processarVencimentos'), 1300), 'marcarVencida') === false, 'vencimento da cobrança não vence assinatura comercial');
arquiteturaAssert(strpos($config, "env_valor('FINANCEIRO_DIAS_TOLERANCIA_VENCIMENTO', 7)") !== false, 'limite central possui padrão D+7 configurável');
arquiteturaAssert(strpos($workflow, 'cancelarAssinatura') !== false && strpos($workflow, 'cancelarContratoPorAssinatura') === false, 'cancelamento pontual é separado do contrato');

echo "FinanceiroWorkflowArchitectureStaticTest OK\n";
