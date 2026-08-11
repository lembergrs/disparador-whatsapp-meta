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
    public function listarPendentesPorCliente($c){return array_values(array_filter($this->rows,fn($r)=>$r['CLI_ID']==$c&&$r['COB_Status']==='pendente'));}
    public function contarAnterioresDoCliente($c,$id){return count(array_filter($this->rows,fn($r)=>$r['CLI_ID']==$c&&$r['COB_ID']<$id&&$r['COB_Status']!=='cancelado'));}
    public function registrarComposicaoDesconto($id,$d){foreach(['valor'=>'COB_Valor','valor_base_centavos'=>'COB_ValorBaseCentavos','desconto_inicial_centavos'=>'COB_DescontoInicialCentavos','desconto_indicacao_centavos'=>'COB_DescontoIndicacaoCentavos','adicionais_centavos'=>'COB_AdicionaisCentavos','ciclo'=>'COB_Ciclo'] as $k=>$f)$this->rows[$id][$f]=$d[$k];return true;}
    public function criar($d){$id=$this->next++;$this->rows[$id]=array_merge(['COB_ID'=>$id,'CLI_ID'=>$d['cliente'],'PLA_ID'=>$d['plano'],'ASS_ID'=>$d['assinatura']??null,'COB_Status'=>'pendente','COB_Valor'=>$d['valor'],'COB_DataVencimento'=>$d['vencimento']],$d);return $id;}
    public function criarRecorrenteIdempotente($d){$e=$this->buscarRecorrente($d['cliente'],$d['plano'],$d['vencimento'],$d['tipo'],$d['assinatura']);return $e?['id'=>$e['COB_ID'],'criada'=>false]:['id'=>$this->criar($d),'criada'=>true];}
    public function buscarRecorrente($c,$p,$v,$t='mensalidade',$a=null){foreach($this->rows as $r){if($r['CLI_ID']==$c&&$r['PLA_ID']==$p&&$r['COB_DataVencimento']===$v&&($a===null||$r['ASS_ID']==$a)&&$r['COB_Status']!=='cancelado')return $r;}return false;}
    public function buscarPorCompetencia($a,$v,$t='mensalidade'){foreach($this->rows as $r){if($r['ASS_ID']==$a&&$r['COB_DataVencimento']===$v&&($r['tipo']??'mensalidade')===$t)return $r;}return false;}
    public function buscar($id){return $this->rows[$id]??null;}
    public function buscarParaAtualizacao($id){return $this->buscar($id);}
    public function comLockIntegracao($id,callable $c){return $c();}
    public function prepararReprocessamento($id,$tentativa){$this->rows[$id]['COB_Status']='pendente';$this->rows[$id]['COB_ProviderPaymentId']=null;$this->rows[$id]['COB_ProviderStatus']=$tentativa<=1?'reprocessamento_base':'reprocessamento_tentativa_'.$tentativa;return true;}
    public function marcarPago($id){$this->rows[$id]['COB_Status']='pago';return true;}
    public function cancelar($id){$this->rows[$id]['COB_Status']='cancelado';return true;}
    public function atualizarIntegracaoProvider($id,$d){if($this->falharPersistencia&&!empty($d['provider_payment_id'])){$this->falharPersistencia=false;throw new RuntimeException('falha simulada');}foreach(['status'=>'COB_Status','provider_payment_id'=>'COB_ProviderPaymentId','provider_status'=>'COB_ProviderStatus','provider_payload'=>'COB_ProviderPayload'] as $k=>$f){if(array_key_exists($k,$d))$this->rows[$id][$f]=$d[$k];}return true;}
    public function buscarPorProviderPaymentId($p,$id){foreach($this->rows as $r){if(($r['COB_ProviderPaymentId']??'')===$id)return $r;}return null;}
    public function registrarEventoProvider($id,$p,$eid,$e,$s,$payload){if(isset($this->events[$eid]))return 'duplicado';$this->events[$eid]=compact('id','e','s','payload');return true;}
    public function vincularAssinatura($id,$a){$this->rows[$id]['ASS_ID']=$a;return true;}
    public function cancelarPendentesPorCliente($c){foreach($this->rows as &$r){if($r['CLI_ID']==$c&&$r['COB_Status']==='pendente')$r['COB_Status']='cancelado';}return true;}
    public function listarPendentesVencidas(){return [];}
}
class FwPlanos { public $p=['PLA_ID'=>1,'PLA_Nome'=>'Plano','PLA_Valor'=>10,'PLA_ValorMensal'=>10,'PLA_Periodicidade'=>'mensal','PLA_LimiteNumeros'=>2];public function buscar($id){return $id===1?$this->p:null;} }
class FwAsaas {
    public $payments=[]; public $posts=0; public $falharCriacao=false; public $values=[];
    public function criarOuAtualizarCliente($c){return ['sucesso'=>true,'response'=>['id'=>'cus_1']];}
    public function buscarCobrancaPorReferenciaExterna($ref){return ['sucesso'=>true,'response'=>['data'=>isset($this->payments[$ref])?[$this->payments[$ref]]:[]]];}
    public function criarCobranca($c,$b,$ref=null){$this->posts++;$this->values[]=(string)$b['COB_Valor'];if($this->falharCriacao)return ['sucesso'=>false,'response'=>[]];$ref=$ref?:'cobranca_'.$b['COB_ID'];$id='pay_'.$b['COB_ID'].($this->posts>1?'_'.$this->posts:'');return ['sucesso'=>true,'response'=>$this->payments[$ref]=['id'=>$id,'status'=>'PENDING','invoiceUrl'=>'https://teste.local/fatura','externalReference'=>$ref]];}
    public function consultarCobranca($id){foreach($this->payments as $p){if($p['id']===$id)return ['sucesso'=>true,'http_code'=>200,'response'=>$p];}return ['sucesso'=>false,'http_code'=>404,'response'=>[]];}
    public function buscarPixQrCode($id){return ['sucesso'=>true,'response'=>['payload'=>'pix','encodedImage'=>'qr']];}
}
class FwRecorrencia {public function diasTolerancia(){return 5;}public function calcularProximaData($c,$d){return date('Y-m-d',strtotime('+1 month',strtotime($d)));}}
class FwMetas {public function validarLimiteNumerosPlano(){return ['permitido'=>true];}}
class FwDescontos {
    public $total=0;public $preparadas=[];public $confirmadas=[];public $liberadas=[];public $garantidas=[];public $estados=[];
    public function prepararDesconto($cliente,$ciclo,$base,$tipo,$id){$this->preparadas[]=compact('cliente','ciclo','base','tipo','id');if($this->total>0)$this->estados[$tipo.':'.$id]='reservada';return ['desconto_total_centavos'=>$this->total];}
    public function garantirReservasDaReferencia($tipo,$id,$total){$chave=$tipo.':'.$id;$this->garantidas[]=['chave'=>$chave,'total'=>$total];if(($this->estados[$chave]??null)==='liberada')$this->estados[$chave]='reservada';if(($this->estados[$chave]??null)!=='reservada')throw new DomainException('reserva não utilizável');return [];}
    public function confirmarUtilizacao($tipo,$id){$chave=$tipo.':'.$id;if(($this->estados[$chave]??null)!=='reservada')throw new DomainException('reserva não confirmável');$this->estados[$chave]='utilizada';$this->confirmadas[$chave]=true;return [];}
    public function liberarReservas($tipo,$id,$motivo){$chave=$tipo.':'.$id;if(($this->estados[$chave]??null)==='reservada')$this->estados[$chave]='liberada';$this->liberadas[$chave]=$motivo;return [];}
}
class FwRollbackTransacao {
    private $cli;private $ass;private $cob;
    public function __construct($cli,$ass,$cob){$this->cli=$cli;$this->ass=$ass;$this->cob=$cob;}
    public function executar(callable $c){$cr=$this->cli->rows;$ar=$this->ass->rows;$br=$this->cob->rows;$ev=$this->cob->events;try{return $c();}catch(Throwable $e){$this->cli->rows=$cr;$this->ass->rows=$ar;$this->cob->rows=$br;$this->cob->events=$ev;throw $e;}}
}

function novoWorkflow(&$cli,&$ass,&$cob,&$asaas,$descontos=null){$cli=new FwClientes();$ass=new FwAssinaturas();$cob=new FwCobrancas();$asaas=new FwAsaas();return new FinanceiroWorkflowService($cli,$ass,$cob,new FwPlanos(),$asaas,new FwRecorrencia(),new FwTransacao(),new FwMetas(),$descontos);}

$w=novoWorkflow($cli,$ass,$cob,$asaas);
$contrato=$w->contratarPlano(1,1,'mensal');
fwAssert($contrato['sucesso']&&$asaas->posts===1,'contratação integra uma cobrança');
fwAssert($cob->rows[1]['COB_Valor']==='5.00'&&$cob->rows[1]['COB_DescontoInicialCentavos']===500&&$cob->rows[1]['COB_DescontoIndicacaoCentavos']===0,'primeira mensalidade recebe somente 50%');
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

$descontos=new FwDescontos();$descontos->total=150;$w=novoWorkflow($cli,$ass,$cob,$asaas,$descontos);
$anterior=$cob->criar(['cliente'=>1,'plano'=>1,'assinatura'=>1,'valor'=>'10.00','vencimento'=>'2026-01-01','tipo'=>'mensalidade']);$cob->rows[$anterior]['COB_Status']='pago';
$ass->criarOuAtualizarPorCliente(1,(new FwPlanos())->p,'ativa',['ciclo'=>'mensal','valor'=>'10.00','proxima_cobranca'=>date('Y-m-d')]);
$w->gerarCobrancasRecorrentes();$recorrente=$cob->rows[2];
fwAssert($recorrente['COB_Valor']==='8.50'&&$recorrente['COB_ValorBaseCentavos']===1000&&$recorrente['COB_DescontoIndicacaoCentavos']===150&&count($descontos->preparadas)===1,'segunda cobrança delega desconto e envia valor final');
fwAssert(empty($descontos->confirmadas),'criação no Asaas mantém crédito apenas reservado');
$w->processarPagamentoWebhook(['id'=>'evt_indicacao_vencida','event'=>'PAYMENT_OVERDUE','payment'=>['id'=>$recorrente['COB_ProviderPaymentId'],'status'=>'OVERDUE']]);
fwAssert(($descontos->estados['cobranca:2']??null)==='reservada'&&empty($descontos->liberadas),'vencimento por webhook mantém reserva ativa');
$w->processarPagamentoWebhook(['id'=>'evt_indicacao','event'=>'PAYMENT_CONFIRMED','payment'=>['id'=>$recorrente['COB_ProviderPaymentId'],'status'=>'CONFIRMED']]);
$w->processarPagamentoWebhook(['id'=>'evt_indicacao','event'=>'PAYMENT_CONFIRMED','payment'=>['id'=>$recorrente['COB_ProviderPaymentId'],'status'=>'CONFIRMED']]);
fwAssert(count($descontos->confirmadas)===1,'webhook duplicado não confirma utilização novamente');

$descontos2=new FwDescontos();$descontos2->total=150;$w=novoWorkflow($cli,$ass,$cob,$asaas,$descontos2);
$anterior=$cob->criar(['cliente'=>1,'plano'=>1,'assinatura'=>1,'valor'=>'10.00','vencimento'=>'2026-01-01','tipo'=>'mensalidade']);$cob->rows[$anterior]['COB_Status']='pago';
$ass->criarOuAtualizarPorCliente(1,(new FwPlanos())->p,'ativa',['valor'=>'10.00','proxima_cobranca'=>date('Y-m-d')]);$asaas->falharCriacao=true;$w->gerarCobrancasRecorrentes();
fwAssert(($descontos2->liberadas['cobranca:2']??null)==='falha_criacao_cobranca_asaas','falha externa libera reservas');
$valorCongelado=$cob->rows[2]['COB_Valor'];$asaas->falharCriacao=false;$w->gerarCobrancasRecorrentes();$w->gerarCobrancasRecorrentes();
fwAssert(($descontos2->estados['cobranca:2']??null)==='reservada'&&count($descontos2->garantidas)===1,'retry restabelece uma única reserva ativa antes do Asaas');
fwAssert($cob->rows[2]['COB_Valor']===$valorCongelado&&$asaas->values===[$valorCongelado,$valorCongelado],'retry preserva exatamente o valor congelado');
$w->processarPagamentoWebhook(['id'=>'evt_retry_pago','event'=>'PAYMENT_CONFIRMED','payment'=>['id'=>$cob->rows[2]['COB_ProviderPaymentId'],'status'=>'CONFIRMED']]);fwAssert(($descontos2->estados['cobranca:2']??null)==='utilizada','pagamento após retry utiliza reserva restabelecida');

$descontos3=new FwDescontos();$descontos3->total=150;$w=novoWorkflow($cli,$ass,$cob,$asaas,$descontos3);$anterior=$cob->criar(['cliente'=>1,'plano'=>1,'assinatura'=>1,'valor'=>'10.00','vencimento'=>'2026-01-01','tipo'=>'mensalidade']);$cob->rows[$anterior]['COB_Status']='pago';$ass->criarOuAtualizarPorCliente(1,(new FwPlanos())->p,'ativa',['valor'=>'10.00','proxima_cobranca'=>date('Y-m-d')]);$w->gerarCobrancasRecorrentes();$cob->rows[2]['COB_Status']='vencido';$w->confirmarPagamentoManual(2);fwAssert(($descontos3->estados['cobranca:2']??null)==='utilizada','confirmação manual tardia utiliza reserva ativa');

$descontos4=new FwDescontos();$descontos4->total=150;$w=novoWorkflow($cli,$ass,$cob,$asaas,$descontos4);$anterior=$cob->criar(['cliente'=>1,'plano'=>1,'assinatura'=>1,'valor'=>'10.00','vencimento'=>'2026-01-01','tipo'=>'mensalidade']);$cob->rows[$anterior]['COB_Status']='pago';$ass->criarOuAtualizarPorCliente(1,(new FwPlanos())->p,'ativa',['valor'=>'10.00','proxima_cobranca'=>date('Y-m-d')]);$w->gerarCobrancasRecorrentes();$w->cancelarCobranca(2);$w->cancelarCobranca(2);fwAssert(($descontos4->estados['cobranca:2']??null)==='liberada'&&count($descontos4->liberadas)===1,'cancelamento terminal repetido permanece idempotente');

$workflowFonte=file_get_contents(__DIR__.'/../app/Services/FinanceiroWorkflowService.php');
fwAssert(strpos($workflowFonte,'selecionarDisponiveisFifo')===false&&strpos($workflowFonte,'ICR_Percentual')===false,'Financeiro não duplica FIFO nem cálculo percentual do domínio');

echo "FinanceiroWorkflowServiceTest OK\n";
