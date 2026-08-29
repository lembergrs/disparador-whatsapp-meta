<?php

require_once __DIR__ . '/../app/Services/MetaHealthService.php';
require_once __DIR__ . '/../app/Services/WorkerOperationalValidatorService.php';

use Services\MetaHealthService;
use Services\WorkerOperationalValidatorService;

function eligibilityAssert($condition, $message){
    if(!$condition){
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$conta = ['MTA_PagamentoMetaStatus' => 'confirmado_cliente'];
$saudavel = ['disponivel' => true, 'can_send_message' => 'AVAILABLE', 'erros' => []];
$warning = ['disponivel' => true, 'can_send_message' => 'AVAILABLE', 'erros' => [['codigo'=>'141010','nivel'=>'warning']]];
$limited = ['disponivel' => true, 'can_send_message' => 'LIMITED', 'erros' => []];
$indisponivel = ['disponivel' => false, 'can_send_message' => null, 'erros' => []];
$blocked = ['disponivel' => true, 'can_send_message' => 'BLOCKED', 'erros' => []];
$erroPagamento = ['disponivel'=>true,'can_send_message'=>'AVAILABLE','erros'=>[['codigo'=>'141006','nivel'=>'danger','titulo'=>'Problema na forma de pagamento','solucao'=>'Corrija na Meta.']]];

eligibilityAssert(MetaHealthService::avaliarAptidaoEnvio($conta, $saudavel)['permitido'], 'pagamento confirmado e health saudável permitem envio');
eligibilityAssert(!MetaHealthService::avaliarAptidaoEnvio([], $saudavel)['permitido'], 'pagamento não confirmado bloqueia');
eligibilityAssert(!MetaHealthService::avaliarAptidaoEnvio($conta, $blocked)['permitido'], 'health BLOCKED bloqueia');
$r141006 = MetaHealthService::avaliarAptidaoEnvio($conta, $erroPagamento);
eligibilityAssert(!$r141006['permitido'] && $r141006['codigo'] === '141006' && strpos($r141006['mensagem'], 'forma de pagamento') !== false, 'erro 141006 bloqueia com mensagem adequada');
eligibilityAssert(MetaHealthService::avaliarAptidaoEnvio($conta, $warning)['permitido'], 'warning não crítico não bloqueia');
eligibilityAssert(MetaHealthService::avaliarAptidaoEnvio($conta, $indisponivel)['permitido'], 'diagnóstico indisponível não bloqueia sozinho');
eligibilityAssert(MetaHealthService::avaliarAptidaoEnvio($conta, $limited)['permitido'], 'LIMITED não bloqueia automaticamente');

class EligibilityClienteFake { public function buscarComPlano($id){ return ['CLI_ID'=>$id,'CLI_Ativo'=>'S','CLI_StatusCadastro'=>'ativo','CLI_StatusPagamento'=>'pago','PLA_LimiteMensagens'=>0]; } }
class EligibilityMetaFake {
    public $pagamento = 'confirmado_cliente';
    public function buscarPorCliente($metaId, $clienteId){
        if($clienteId !== 1){ return false; }
        return ['MTA_ID'=>$metaId,'CLI_ID'=>1,'MTA_Token'=>'token','MTA_PhoneNumberId'=>'phone','MTA_WabaId'=>'waba','MTA_UrlBase'=>'https://meta.test','MTA_Status'=>'conectado','MTA_PagamentoMetaStatus'=>$this->pagamento];
    }
}
class EligibilityConsumoFake { public function buscarMesAtual(){ return ['CMS_Mensagens'=>0]; } }
class EligibilityPolicyFake { public function avaliar(){ return ['vinculo_ativo'=>false]; } }

$metaFake = new EligibilityMetaFake();
$consultas = 0;
$validator = new WorkerOperationalValidatorService(new EligibilityClienteFake(), $metaFake, new EligibilityConsumoFake(), new EligibilityPolicyFake(), function() use (&$consultas, $saudavel){ $consultas++; return $saudavel; });
eligibilityAssert($validator->validarEnvio(1, 10, '5511999999999')['permitido'], 'worker permite conta apta');
eligibilityAssert($validator->validarEnvio(1, 10, '5511888888888')['permitido'], 'worker reutiliza aptidão no mesmo lote');
eligibilityAssert($consultas === 1, 'worker consulta health apenas uma vez por conta e ciclo');
eligibilityAssert(!$validator->validarEnvio(2, 10, '5511999999999')['permitido'], 'conta de outro CLI_ID é rejeitada');

$metaPendente = new EligibilityMetaFake();
$metaPendente->pagamento = 'pendente_confirmacao';
$consultasPendente = 0;
$validatorPendente = new WorkerOperationalValidatorService(new EligibilityClienteFake(), $metaPendente, new EligibilityConsumoFake(), new EligibilityPolicyFake(), function() use (&$consultasPendente){ $consultasPendente++; return []; });
$bloqueioPendente = $validatorPendente->validarEnvio(1, 10, '5511999999999');
eligibilityAssert(!$bloqueioPendente['permitido'] && $bloqueioPendente['codigo'] === 'pagamento_meta_pendente', 'worker bloqueia estado local de pagamento');
eligibilityAssert($consultasPendente === 0, 'worker não consulta health externo quando pagamento local já bloqueia');

$root = dirname(__DIR__);
$disparo = file_get_contents($root . '/app/Controllers/DisparoController.php');
$campanha = file_get_contents($root . '/app/Controllers/CampanhaController.php');
$workerValidator = file_get_contents($root . '/app/Services/WorkerOperationalValidatorService.php');
$manualQueue = file_get_contents($root . '/app/Services/DisparoManualQueueService.php');
$campanhaQueue = file_get_contents($root . '/app/Services/CampanhaQueueService.php');
$configuracao = file_get_contents($root . '/app/Views/configuracao/meta.php');
$disparoView = file_get_contents($root . '/app/Views/disparos/index.php');
$campanhaView = file_get_contents($root . '/app/Views/campanhas/index.php');
$custos = file_get_contents($root . '/app/Views/components/meta_costs_notice.php');

eligibilityAssert(substr_count($disparo, 'validarContaMetaParaEnvio(') >= 5, 'fluxos manuais validam conta antes de enviar ou enfileirar');
eligibilityAssert(strpos($campanha, '$this->validarContaMetaParaEnvio((int) $template[\'MTA_ID\']') !== false, 'criação de campanha valida a conta do template');
eligibilityAssert(substr_count($campanha, 'validarContaMetaParaEnvio(') >= 4, 'criação, reagendamento e teste de campanha são protegidos');
eligibilityAssert(strpos($disparo, 'buscarPorCliente($metaId, $clienteId)') !== false && strpos($campanha, 'buscarPorCliente($metaId, $clienteId)') !== false, 'controllers garantem isolamento por CLI_ID');
eligibilityAssert(strpos($workerValidator, 'MetaHealthService::avaliarAptidaoEnvio') !== false, 'worker reutiliza a regra central de aptidão');
eligibilityAssert(strpos($workerValidator, '$this->aptidaoMetaCache[$cacheKey]') !== false, 'worker reutiliza diagnóstico por conta durante o processamento');
eligibilityAssert(!preg_match('/if\s*\(\$origem\s*!==\s*[\'\"]ajax[\'\"]\)\s*\{\s*\$validacao/', $manualQueue), 'fila manual também valida no processamento AJAX');
eligibilityAssert(strpos($campanhaQueue, 'validarEnvio(') !== false, 'fila de campanhas revalida antes do envio externo');
eligibilityAssert(strpos($configuracao, 'Meta cobra diretamente as tarifas de uso do WhatsApp Business') !== false, 'aviso de cobrança direta aparece antes da conexão');
eligibilityAssert(strpos($configuracao, "MTA_Status'] ?? '') === 'conectado' && \$pagamentoStatus === 'confirmado_cliente'") !== false, 'pronto para uso exige confirmação de pagamento Meta');
eligibilityAssert(strpos($configuracao, 'Falta configurar ou confirmar a forma de pagamento na Meta') !== false, 'conta conectada pendente recebe orientação adequada');
eligibilityAssert(strpos($disparoView, 'meta_costs_notice.php') !== false && strpos($campanhaView, 'meta_costs_notice.php') !== false, 'telas de envio reutilizam aviso de custos');
eligibilityAssert(strpos($custos, 'cobradas diretamente pela Meta') !== false && strpos($custos, 'não estão incluídas no plano do Disparador') !== false, 'responsabilidades financeiras ficam separadas');

echo "MetaSendEligibilityTest OK\n";
