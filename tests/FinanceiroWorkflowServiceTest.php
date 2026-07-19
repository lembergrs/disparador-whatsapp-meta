<?php

require_once __DIR__ . '/../app/Services/FinanceiroWorkflowService.php';
require_once __DIR__ . '/../app/Models/Plano.php';

use Services\FinanceiroWorkflowService;

function fwAssert($condition, $message){ if(!$condition){ throw new RuntimeException($message); } }

class FwTransacao { public function executar(callable $c){ return $c(); } }
class FwClientes {
    public $rows=[1=>['CLI_ID'=>1,'CLI_Nome'=>'Cliente','CLI_Email'=>'cliente@teste.local','CLI_ProviderCustomerId'=>'cus_1','CLI_StatusPagamento'=>'pendente','CLI_StatusCadastro'=>'ativo']];
    public function buscar($id){return $this->rows[$id]??null;}
    public function atualizarEstadoFinanceiro($id,$d){$this->rows[$id]=array_merge($this->rows[$id]??[],$d);return true;}
    public function atualizarProviderPagamento($id,$p,$c){$this->rows[$id]['CLI_ProviderCustomerId']=$c;return true;}
}
class FwAssinaturas {
    public $rows=[]; public $next=1; public $falharAvanco=false;
    public function criarOuAtualizarPorCliente($c,$p,$s,$o=[]){
        $existente=$this->buscarParaPagamento($c,$p);
        $id=$existente['ASS_ID']??$this->next++;
        $this->rows[$id]=['ASS_ID'=>$id,'CLI_ID'=>$c,'PLA_ID'=>$p['PLA_ID'],'ASS_Status'=>$s,'ASS_Ciclo'=>$o['ciclo']??'mensal','ASS_Valor'=>$o['valor']??10,'ASS_DataProximaCobranca'=>$o['proxima_cobranca']??date('Y-m-d')];
        return true;
    }
    public function buscarParaPagamento($c,$p){foreach(array_reverse($this->rows,true) as $r){if($r['CLI_ID']==$c&&$r['PLA_ID']==$p&&in_array($r['ASS_Status'],['ativa','pendente','vencida']))return $r;}return null;}
    public function buscarPendenteMaisRecente($c,$p){return $this->buscarParaPagamento($c,$p);}
    public function ativar($id){$this->rows[$id]['ASS_Status']='ativa';return true;}
    public function cancelar($id){$this->rows[$id]['ASS_Status']='cancelada';return true;}
    public function cancelarVigentesPorCliente($c){foreach($this->rows as &$r){if($r['CLI_ID']==$c&&in_array($r['ASS_Status'],['ativa','pendente']))$r['ASS_Status']='cancelada';}return true;}
    public function buscarUltimaPorCliente($c){foreach(array_reverse($this->rows,true) as $r){if($r['CLI_ID']==$c)return $r;}return null;}
    public function buscarPorId($id){return $this->rows[$id]??null;}
    public function listarParaRecorrencia(){return array_values(array_filter($this->rows,fn($r)=>$r['ASS_Status']==='ativa'&&$r['ASS_DataProximaCobranca']<=date('Y-m-d')));}
    public function avancarProximaCobrancaSeCiclo($id,$ciclo,$proxima){
        if($this->falharAvanco){$this->falharAvanco=false;throw new RuntimeException('falha simulada');}
        if(($this->rows[$id]['ASS_DataProximaCobranca']??null)===$ciclo){$this->rows[$id]['ASS_DataProximaCobranca']=$proxima;return true;}return false;
    }
    public function buscarVigentePorCliente($c){foreach($this->rows as $r){if($r['CLI_ID']==$c&&in_array($r['ASS_Status'],['ativa','pendente']))return $r;}return null;}
    public function marcarVencida($id){$this->rows[$id]['ASS_Status']='vencida';return true;}
}
class FwCobrancas {
    public $rows=[]; public $events=[]; public $next=1; public $falharPersistencia=false;
    public function buscarPendentePorCliente($c){foreach($this->rows as $r){if($r['CLI_ID']==$c&&$r['COB_Status']==='pendente')return $r;}return null;}
    public function criar($d){$id=$this->next++;$this->rows[$id]=array_merge(['COB_ID'=>$id,'CLI_ID'=>$d['cliente'],'PLA_ID'=>$d['plano'],'ASS_ID'=>$d['assinatura']??null,'COB_Status'=>'pendente','COB_Valor'=>$d['valor'],'COB_DataVencimento'=>$d['vencimento']],$d);return $id;}
    public function criarRecorrenteIdempotente($d){$e=$this->buscarRecorrente($d['cliente'],$d['plano'],$d['vencimento'],$d['tipo'],$d['assinatura']);return $e?['id'=>$e['COB_ID'],'criada'=>false]:['id'=>$this->criar($d),'criada'=>true];}
    public function buscarRecorrente($c,$p,$v,$t='mensalidade',$a=null){foreach($this->rows as $r){if($r['CLI_ID']==$c&&$r['PLA_ID']==$p&&$r['COB_DataVencimento']===$v&&($a===null||$r['ASS_ID']==$a)&&$r['COB_Status']!=='cancelado')return $r;}return false;}
    public function buscarPorCompetencia($a,$v,$t='mensalidade'){foreach($this->rows as $r){if($r['ASS_ID']==$a&&$r['COB_DataVencimento']===$v&&($r['tipo']??'mensalidade')===$t)return $r;}return false;}
    public function buscar($id){return $this->rows[$id]??null;}
    public function buscarParaAtualizacao($id){return $this->buscar($id);}
    public function comLockIntegracao($id,callable $c){return $c();}
    public function prepararReprocessamento($id,$tentativa){$this->rows[$id]['COB_Status']='pendente';$this->rows[$id]['COB_ProviderPaymentId']=null;$this->rows[$id]['COB_ProviderStatus']=$tentativa<=1?'reprocessamento_base':'reprocessamento_tentativa_'.$tentativa;return true;}
    public function marcarPago($id){$this->rows[$id]['COB_Status']='pago';return true;}
    public function atualizarIntegracaoProvider($id,$d){if($this->falharPersistencia&&!empty($d['provider_payment_id'])){$this->falharPersistencia=false;throw new RuntimeException('falha simulada');}foreach(['status'=>'COB_Status','provider_payment_id'=>'COB_ProviderPaymentId','provider_status'=>'COB_ProviderStatus','provider_payload'=>'COB_ProviderPayload'] as $k=>$f){if(array_key_exists($k,$d))$this->rows[$id][$f]=$d[$k];}return true;}
    public function buscarPorProviderPaymentId($p,$id){foreach($this->rows as $r){if(($r['COB_ProviderPaymentId']??'')===$id)return $r;}return null;}
    public function registrarEventoProvider($id,$p,$eid,$e,$s,$payload){if(isset($this->events[$eid]))return 'duplicado';$this->events[$eid]=compact('id','e','s','payload');return true;}
    public function vincularAssinatura($id,$a){$this->rows[$id]['ASS_ID']=$a;return true;}
    public function cancelarPendentesPorCliente($c){foreach($this->rows as &$r){if($r['CLI_ID']==$c&&$r['COB_Status']==='pendente')$r['COB_Status']='cancelado';}return true;}
    public function listarPendentesVencidas(){return [];}
}
class FwPlanos { public $p=['PLA_ID'=>1,'PLA_Nome'=>'Plano','PLA_Valor'=>10,'PLA_ValorMensal'=>10,'PLA_Periodicidade'=>'mensal','PLA_LimiteNumeros'=>2];public function buscar($id){return $id===1?$this->p:null;} }
class FwAsaas {
    public $payments=[]; public $posts=0; public $falharCriacao=false;
    public function criarOuAtualizarCliente($c){return ['sucesso'=>true,'response'=>['id'=>'cus_1']];}
    public function buscarCobrancaPorReferenciaExterna($ref){return ['sucesso'=>true,'response'=>['data'=>isset($this->payments[$ref])?[$this->payments[$ref]]:[]]];}
    public function criarCobranca($c,$b,$ref=null){$this->posts++;if($this->falharCriacao)return ['sucesso'=>false,'response'=>[]];$ref=$ref?:'cobranca_'.$b['COB_ID'];$id='pay_'.$b['COB_ID'].($this->posts>1?'_'.$this->posts:'');return ['sucesso'=>true,'response'=>$this->payments[$ref]=['id'=>$id,'status'=>'PENDING','invoiceUrl'=>'https://teste.local/fatura','externalReference'=>$ref]];}
    public function consultarCobranca($id){foreach($this->payments as $p){if($p['id']===$id)return ['sucesso'=>true,'http_code'=>200,'response'=>$p];}return ['sucesso'=>false,'http_code'=>404,'response'=>[]];}
    public function buscarPixQrCode($id){return ['sucesso'=>true,'response'=>['payload'=>'pix','encodedImage'=>'qr']];}
}
class FwRecorrencia {public function diasTolerancia(){return 5;}public function calcularProximaData($c,$d){return date('Y-m-d',strtotime('+1 month',strtotime($d)));}}
class FwMetas {public function validarLimiteNumerosPlano(){return ['permitido'=>true];}}
class FwRollbackTransacao {
    private $cli;private $ass;private $cob;
    public function __construct($cli,$ass,$cob){$this->cli=$cli;$this->ass=$ass;$this->cob=$cob;}
    public function executar(callable $c){$cr=$this->cli->rows;$ar=$this->ass->rows;$br=$this->cob->rows;$ev=$this->cob->events;try{return $c();}catch(Throwable $e){$this->cli->rows=$cr;$this->ass->rows=$ar;$this->cob->rows=$br;$this->cob->events=$ev;throw $e;}}
}

function novoWorkflow(&$cli,&$ass,&$cob,&$asaas){$cli=new FwClientes();$ass=new FwAssinaturas();$cob=new FwCobrancas();$asaas=new FwAsaas();return new FinanceiroWorkflowService($cli,$ass,$cob,new FwPlanos(),$asaas,new FwRecorrencia(),new FwTransacao(),new FwMetas());}

$w=novoWorkflow($cli,$ass,$cob,$asaas);
$contrato=$w->contratarPlano(1,1,'mensal');
fwAssert($contrato['sucesso']&&$asaas->posts===1,'contratação integra uma cobrança');
$w->confirmarPagamentoManual(1);fwAssert($cob->rows[1]['COB_Status']==='pago','pagamento manual confirma cobrança');

$cob->rows[1]['COB_ProviderPaymentId']='pay_1';
$w->processarPagamentoWebhook(['id'=>'evt_confirmado','event'=>'PAYMENT_CONFIRMED','payment'=>['id'=>'pay_1','status'=>'CONFIRMED']]);
$w->processarPagamentoWebhook(['id'=>'evt_atrasado','event'=>'PAYMENT_OVERDUE','payment'=>['id'=>'pay_1','status'=>'OVERDUE']]);
fwAssert($cob->rows[1]['COB_Status']==='pago'&&$cli->rows[1]['status_pagamento']==='pago','evento atrasado não regride pagamento');
$duplicado=$w->processarPagamentoWebhook(['id'=>'evt_atrasado','event'=>'PAYMENT_OVERDUE','payment'=>['id'=>'pay_1','status'=>'OVERDUE']]);
fwAssert(!empty($duplicado['duplicado'])&&count($cob->events)===2,'webhook duplicado não é processado novamente');

$w=novoWorkflow($cli,$ass,$cob,$asaas);$w->contratarPlano(1,1,'mensal');
$w->processarPagamentoWebhook(['id'=>'evt_overdue','event'=>'PAYMENT_OVERDUE','payment'=>['id'=>'pay_1','status'=>'OVERDUE']]);
$w->processarPagamentoWebhook(['id'=>'evt_paid','event'=>'PAYMENT_CONFIRMED','payment'=>['id'=>'pay_1','status'=>'CONFIRMED']]);
fwAssert($cob->rows[1]['COB_Status']==='pago'&&$cli->rows[1]['status_pagamento']==='pago'&&$ass->rows[1]['ASS_Status']==='ativa','vencida pode transicionar para paga');

$w=novoWorkflow($cli,$ass,$cob,$asaas);$ass->criarOuAtualizarPorCliente(1,(new FwPlanos())->p,'ativa',['proxima_cobranca'=>date('Y-m-d')]);
$ass->falharAvanco=true;$w->gerarCobrancasRecorrentes();$posts=$asaas->posts;$w->gerarCobrancasRecorrentes();
fwAssert(count($cob->rows)===1&&$asaas->posts===$posts&&$ass->rows[1]['ASS_DataProximaCobranca']>date('Y-m-d'),'retry reconcilia ciclo sem duplicar cobrança');

$w=novoWorkflow($cli,$ass,$cob,$asaas);$ass->criarOuAtualizarPorCliente(1,(new FwPlanos())->p,'ativa',['proxima_cobranca'=>date('Y-m-d')]);$cob->falharPersistencia=true;
$w->gerarCobrancasRecorrentes();$posts=$asaas->posts;$w->gerarCobrancasRecorrentes();
fwAssert($asaas->posts===$posts,'retry encontra cobrança externa pela referência');

$w=novoWorkflow($cli,$ass,$cob,$asaas);$cob->falharPersistencia=true;
try{$w->contratarPlano(1,1,'mensal');}catch(RuntimeException $e){}
$posts=$asaas->posts;$retry=$w->contratarPlano(1,1,'mensal');
fwAssert($retry['sucesso']&&$asaas->posts===$posts&&count($cob->rows)===1,'contratação retoma cobrança local sem novo POST');

$w=novoWorkflow($cli,$ass,$cob,$asaas);$ass->criarOuAtualizarPorCliente(1,(new FwPlanos())->p,'ativa');$ass->rows[2]=array_merge($ass->rows[1],['ASS_ID'=>2]);
$w->cancelarAssinatura(1,'teste');fwAssert($ass->rows[1]['ASS_Status']==='cancelada'&&$ass->rows[2]['ASS_Status']==='ativa'&&$cli->rows[1]['CLI_StatusCadastro']==='ativo','cancelamento pontual não afeta contrato inteiro');
$w->cancelarContrato(1,'teste completo');fwAssert($ass->rows[2]['ASS_Status']==='cancelada'&&$cli->rows[1]['status_cadastro']==='suspenso','cancelamento contratual mantém alcance completo');

$w=novoWorkflow($cli,$ass,$cob,$asaas);$ass->criarOuAtualizarPorCliente(1,(new FwPlanos())->p,'cancelada');
$asaas->falharCriacao=true;$reativacao=$w->reativarContrato(1);fwAssert(!$reativacao['sucesso']&&$ass->buscarParaPagamento(1,1)['ASS_Status']==='pendente'&&$cli->rows[1]['status_pagamento']==='pendente'&&$cli->rows[1]['status_cadastro']==='suspenso','falha na reativação não libera acesso');
$asaas->falharCriacao=false;$quantidade=count($cob->rows);$w->reativarContrato(1);fwAssert(count($cob->rows)===$quantidade,'retry da reativação reutiliza cobrança recuperável');

$w=novoWorkflow($cli,$ass,$cob,$asaas);$competencia=date('Y-m-d');$ass->criarOuAtualizarPorCliente(1,(new FwPlanos())->p,'ativa',['proxima_cobranca'=>$competencia]);
$id=$cob->criar(['cliente'=>1,'plano'=>1,'assinatura'=>1,'valor'=>10,'vencimento'=>$competencia,'tipo'=>'mensalidade']);$cob->rows[$id]['COB_Status']='cancelado';
$w->gerarCobrancasRecorrentes();fwAssert(count($cob->rows)===1&&$cob->rows[$id]['COB_Status']==='pendente'&&!empty($cob->rows[$id]['COB_ProviderPaymentId'])&&$ass->rows[1]['ASS_DataProximaCobranca']>$competencia,'cobrança cancelada sem Payment ID reutiliza a mesma competência');

$w=novoWorkflow($cli,$ass,$cob,$asaas);$competencia=date('Y-m-d');$ass->criarOuAtualizarPorCliente(1,(new FwPlanos())->p,'ativa',['proxima_cobranca'=>$competencia]);
$id=$cob->criar(['cliente'=>1,'plano'=>1,'assinatura'=>1,'valor'=>10,'vencimento'=>$competencia,'tipo'=>'mensalidade']);$cob->rows[$id]['COB_Status']='cancelado';$cob->rows[$id]['COB_ProviderPaymentId']='pay_cancelado';$asaas->payments['cobranca_'.$id]=['id'=>'pay_cancelado','status'=>'REFUNDED','externalReference'=>'cobranca_'.$id];
$w->gerarCobrancasRecorrentes();fwAssert(count($cob->rows)===1&&$cob->rows[$id]['COB_ProviderPaymentId']!=='pay_cancelado'&&isset($asaas->payments['cobranca_'.$id.'_tentativa_2'])&&$ass->rows[1]['ASS_DataProximaCobranca']>$competencia,'pagamento externo cancelado gera tentativa determinística na mesma cobrança local');

$w=novoWorkflow($cli,$ass,$cob,$asaas);$ass->criarOuAtualizarPorCliente(1,(new FwPlanos())->p,'cancelada');$id=$cob->criar(['cliente'=>1,'plano'=>1,'assinatura'=>1,'valor'=>10,'vencimento'=>date('Y-m-d'),'tipo'=>'mensalidade']);
$w->reativarContrato(1);$vinculada=$cob->rows[$id]['ASS_ID'];fwAssert($vinculada!==1&&$ass->rows[$vinculada]['ASS_Status']==='pendente','retry substitui vínculo com assinatura cancelada por pendente');
$w->processarPagamentoWebhook(['id'=>'evt_reativacao_a','event'=>'PAYMENT_CONFIRMED','payment'=>['id'=>$cob->rows[$id]['COB_ProviderPaymentId'],'status'=>'CONFIRMED']]);fwAssert($ass->rows[$vinculada]['ASS_Status']==='ativa','pagamento ativa a assinatura compatível vinculada');

$w=novoWorkflow($cli,$ass,$cob,$asaas);$ass->criarOuAtualizarPorCliente(1,(new FwPlanos())->p,'cancelada');$id=$cob->criar(['cliente'=>1,'plano'=>1,'assinatura'=>null,'valor'=>10,'vencimento'=>date('Y-m-d'),'tipo'=>'mensalidade']);
$w->reativarContrato(1);$vinculada=$cob->rows[$id]['ASS_ID'];$quantidadeAssinaturas=count($ass->rows);$w->reativarContrato(1);fwAssert($vinculada>0&&$ass->rows[$vinculada]['ASS_Status']==='pendente'&&count($ass->rows)===$quantidadeAssinaturas&&count($cob->rows)===1,'retry sem ASS_ID cria e reutiliza uma única assinatura pendente');

$cli=new FwClientes();$ass=new FwAssinaturas();$cob=new FwCobrancas();$asaas=new FwAsaas();$id=$cob->criar(['cliente'=>1,'plano'=>1,'assinatura'=>null,'valor'=>10,'vencimento'=>date('Y-m-d'),'tipo'=>'mensalidade']);$cob->rows[$id]['COB_ProviderPaymentId']='pay_sem_assinatura';
$w=new FinanceiroWorkflowService($cli,$ass,$cob,new FwPlanos(),$asaas,new FwRecorrencia(),new FwRollbackTransacao($cli,$ass,$cob),new FwMetas());$falhou=false;try{$w->processarPagamentoWebhook(['id'=>'evt_sem_assinatura','event'=>'PAYMENT_CONFIRMED','payment'=>['id'=>'pay_sem_assinatura','status'=>'CONFIRMED']]);}catch(LogicException $e){$falhou=true;}
fwAssert($falhou&&$cob->rows[$id]['COB_Status']==='pendente'&&$cli->rows[1]['CLI_StatusPagamento']==='pendente','pagamento sem assinatura válida falha e sofre rollback');

echo "FinanceiroWorkflowServiceTest OK\n";
