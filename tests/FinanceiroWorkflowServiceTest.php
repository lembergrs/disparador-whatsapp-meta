<?php

require_once __DIR__ . '/../app/Services/FinanceiroWorkflowService.php';
require_once __DIR__ . '/../app/Models/Plano.php';

use Services\FinanceiroWorkflowService;

function fwAssert($condition, $message){ if(!$condition){ throw new RuntimeException($message); } }

class FwTransacao { public function executar(callable $c){ return $c(); } }
class FwClientes {
    public $rows = [1=>['CLI_ID'=>1,'CLI_Nome'=>'Cliente','CLI_Email'=>'cliente@teste.local','CLI_ProviderCustomerId'=>'cus_1']]; public $updates=[];
    public function buscar($id){ return $this->rows[$id] ?? null; }
    public function atualizarEstadoFinanceiro($id,$d){ $this->updates[]=$d; $this->rows[$id]=array_merge($this->rows[$id]??[],$d); return true; }
    public function atualizarProviderPagamento($id,$p,$c){ $this->rows[$id]['CLI_ProviderCustomerId']=$c; return true; }
    public function atualizarAtivacaoComUsuarios($id,$a,$s){ return true; }
    public function salvar($d){ return 1; } public function atualizar($id,$d){ return true; }
}
class FwAssinaturas {
    public $rows=[]; public $next=1;
    public function criarOuAtualizarPorCliente($c,$p,$s,$o=[]){ $this->rows[$this->next]=['ASS_ID'=>$this->next,'CLI_ID'=>$c,'PLA_ID'=>$p['PLA_ID'],'ASS_Status'=>$s,'ASS_Ciclo'=>$o['ciclo']??'mensal','ASS_Valor'=>$o['valor']??10,'ASS_DataProximaCobranca'=>$o['proxima_cobranca']??date('Y-m-d')]; $this->next++; return true; }
    public function buscarParaPagamento($c,$p){ foreach(array_reverse($this->rows,true) as $r){if($r['CLI_ID']==$c&&$r['PLA_ID']==$p)return $r;} return null; }
    public function buscarPendenteMaisRecente($c,$p){ return $this->buscarParaPagamento($c,$p); }
    public function ativar($id){ $this->rows[$id]['ASS_Status']='ativa'; return true; }
    public function cancelarVigentesPorCliente($c){ foreach($this->rows as &$r){if($r['CLI_ID']==$c)$r['ASS_Status']='cancelada';} return true; }
    public function buscarUltimaPorCliente($c){ foreach(array_reverse($this->rows,true) as $r){if($r['CLI_ID']==$c)return $r;} return null; }
    public function buscarPorId($id){ return $this->rows[$id]??null; }
    public function listarParaRecorrencia(){ return array_values(array_filter($this->rows,fn($r)=>$r['ASS_Status']==='ativa')); }
    public function atualizarProximaCobranca($id,$d){$this->rows[$id]['ASS_DataProximaCobranca']=$d;return true;}
    public function buscarVigentePorCliente($c){ foreach($this->rows as $r){if($r['CLI_ID']==$c&&in_array($r['ASS_Status'],['ativa','pendente']))return $r;} return null; }
    public function marcarVencida($id){$this->rows[$id]['ASS_Status']='vencida';return true;}
    public function atualizar($id,$d){return true;} public function criar($d){return true;} public function cancelar($id){$this->rows[$id]['ASS_Status']='cancelada';return true;}
}
class FwCobrancas {
    public $rows=[]; public $events=[]; public $next=1;
    public function buscarPendentePorCliente($c){foreach($this->rows as $r){if($r['CLI_ID']==$c&&$r['COB_Status']==='pendente')return $r;}return null;}
    public function criar($d){$id=$this->next++;$this->rows[$id]=array_merge(['COB_ID'=>$id,'CLI_ID'=>$d['cliente'],'PLA_ID'=>$d['plano'],'ASS_ID'=>$d['assinatura']??null,'COB_Status'=>'pendente','COB_Valor'=>$d['valor'],'COB_DataVencimento'=>$d['vencimento']],$d);return $id;}
    public function buscar($id){return $this->rows[$id]??null;}
    public function marcarPago($id){$this->rows[$id]['COB_Status']='pago';return true;}
    public function atualizarIntegracaoProvider($id,$d){foreach(['status'=>'COB_Status','provider_payment_id'=>'COB_ProviderPaymentId'] as $k=>$f){if(isset($d[$k]))$this->rows[$id][$f]=$d[$k];}return true;}
    public function buscarPorProviderPaymentId($p,$id){foreach($this->rows as $r){if(($r['COB_ProviderPaymentId']??'')===$id)return $r;}return null;}
    public function registrarEventoProvider($id,$p,$eid){if(isset($this->events[$eid]))return 'duplicado';$this->events[$eid]=true;return true;}
    public function vincularAssinatura($id,$a){$this->rows[$id]['ASS_ID']=$a;return true;}
    public function cancelarPendentesPorCliente($c){foreach($this->rows as &$r){if($r['CLI_ID']==$c&&$r['COB_Status']==='pendente')$r['COB_Status']='cancelado';}return true;}
    public function cancelar($id){$this->rows[$id]['COB_Status']='cancelado';return true;}
    public function buscarRecorrente(){return false;} public function listarPendentesVencidas(){return array_values(array_filter($this->rows,fn($r)=>$r['COB_Status']==='pendente'));}
}
class FwPlanos { public $p=['PLA_ID'=>1,'PLA_Nome'=>'Plano','PLA_Valor'=>10,'PLA_ValorMensal'=>10,'PLA_Periodicidade'=>'mensal','PLA_LimiteNumeros'=>2]; public function buscar($id){return $id===1?$this->p:null;} }
class FwAsaas { public function criarOuAtualizarCliente($c){return ['sucesso'=>true,'response'=>['id'=>'cus_1']];} public function criarCobranca($c,$b){return ['sucesso'=>true,'response'=>['id'=>'pay_'.$b['COB_ID'],'status'=>'PENDING','invoiceUrl'=>'https://teste.local/fatura']];} public function buscarPixQrCode($id){return ['sucesso'=>true,'response'=>['payload'=>'pix','encodedImage'=>'qr']];} }
class FwRecorrencia { public function diasTolerancia(){return 5;} public function calcularProximaData($c,$d){return date('Y-m-d',strtotime('+1 month',strtotime($d)));} }
class FwMetas { public function validarLimiteNumerosPlano(){return ['permitido'=>true];} }

$cli=new FwClientes();$ass=new FwAssinaturas();$cob=new FwCobrancas();$pla=new FwPlanos();
$w=new FinanceiroWorkflowService($cli,$ass,$cob,$pla,new FwAsaas(),new FwRecorrencia(),new FwTransacao(),new FwMetas());

$contrato=$w->contratarPlano(1,1,'mensal');
fwAssert($contrato['sucesso']&&$cob->rows[1]['COB_ProviderPaymentId']==='pay_1','contratação integra cobrança');
$w->confirmarPagamentoManual(1); fwAssert($cob->rows[1]['COB_Status']==='pago','pagamento manual confirma cobrança');
$cob->rows[1]['COB_ProviderPaymentId']='pay_1';
$w->processarPagamentoWebhook(['id'=>'evt_1','event'=>'PAYMENT_CONFIRMED','payment'=>['id'=>'pay_1','status'=>'CONFIRMED']]);
fwAssert($ass->rows[1]['ASS_Status']==='ativa','webhook ativa assinatura');
$ass->rows[1]['ASS_DataProximaCobranca']=date('Y-m-d');
$renovacao=$w->gerarCobrancasRecorrentes(); fwAssert($renovacao['cobrancas_geradas']===1&&$cob->rows[2]['COB_ProviderPaymentId']==='pay_2','recorrência integra Asaas');
$w->cancelarContrato(1,'teste'); fwAssert($ass->rows[1]['ASS_Status']==='cancelada'&&$cob->rows[2]['COB_Status']==='cancelado','cancelamento é composto');
$reativacao=$w->reativarContrato(1); fwAssert($reativacao['sucesso']&&count($cob->rows)===3,'reativação cria cobrança integrada');
$vencimentos=$w->processarVencimentos(); fwAssert($vencimentos['cobrancas_vencidas']>=1,'vencimento processado pelo workflow');

echo "FinanceiroWorkflowServiceTest OK\n";
