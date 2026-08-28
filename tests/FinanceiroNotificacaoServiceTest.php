<?php

define('BASE_URL', 'https://teste.local');
require_once __DIR__ . '/../app/Services/EventoNotificacao.php';
require_once __DIR__ . '/../app/Services/CanalNotificacao.php';
require_once __DIR__ . '/../app/Services/Tasks/TaskRetryException.php';
require_once __DIR__ . '/../app/Services/FinanceiroNotificacaoService.php';

use Services\CanalNotificacao;
use Services\EventoNotificacao;
use Services\FinanceiroNotificacaoService;

function fnAssert($ok,$mensagem){if(!$ok){throw new RuntimeException($mensagem);}}

class FnCobrancas {
    public $rows=[];
    public function listarAbertasParaComunicacao(){return array_values(array_filter($this->rows,fn($r)=>in_array($r['COB_Status'],['pendente','vencido'],true)&&!empty($r['ASS_ID'])));}
    public function buscar($id){return $this->rows[$id]??null;}
    public function buscarObrigacaoAbertaPorAssinatura($cli,$ass){foreach($this->rows as $r){if($r['CLI_ID']===$cli&&$r['ASS_ID']===$ass&&in_array($r['COB_Status'],['pendente','vencido'],true)){return $r;}}return null;}
}
class FnAssinaturas {
    public $ativa=['ASS_ID'=>10,'CLI_ID'=>1,'ASS_Status'=>'ativa']; public $pendente=null;
    public function buscarParaRegularizacaoFinanceira($cli){return $this->ativa ?: $this->pendente;}
    public function buscarAtivaPorCliente($cli){return $this->ativa;}
}
class FnClientes {public function buscar($id){return ['CLI_ID'=>$id,'CLI_Nome'=>'Cliente','CLI_Email'=>'cliente@teste.local','CLI_Telefone'=>'11999999999'];}}
class FnPlanos {public function buscar($id){return ['PLA_ID'=>$id,'PLA_Nome'=>'Profissional'];}}
class FnPolicy {public $resultado=['situacao'=>'regular','cobranca_id'=>null];public function avaliar($id){return $this->resultado;}}
class FnEntregas {
    public $canais=[CanalNotificacao::EMAIL]; public $resultado=['sucesso'=>true,'status'=>'enviada']; public $envios=0;
    public function canaisAtivos($evento){return $this->canais;}
    public function entregarCanalReservado($evento,$canal,$cliente,$dados){$this->envios++;return $this->resultado[$canal]??$this->resultado;}
}
class FnNotificacoes {
    public $rows=[]; private $chaves=[]; private $next=1;
    public function reservarIdempotente($d){if(isset($this->chaves[$d['chave']])){return $this->rows[$this->chaves[$d['chave']]];}$id=$this->next++;$this->chaves[$d['chave']]=$id;return $this->rows[$id]=['NOT_ID'=>$id,'NOT_Status'=>'pendente','COB_ID'=>$d['cobranca_id'],'NOT_Tipo'=>$d['tipo'],'NOT_Canal'=>$d['canal'],'NOT_Dados'=>json_encode($d['dados'])];}
    public function buscar($id){return $this->rows[$id]??null;}
    public function marcarProcessando($id){if(!in_array($this->rows[$id]['NOT_Status'],['pendente','erro_temporario']))return false;$this->rows[$id]['NOT_Status']='processando';return true;}
    public function marcarResultadoFinanceiro($id,$r){$this->rows[$id]['NOT_Status']=!empty($r['sucesso'])?'enviada':$r['status'];return true;}
    public function marcarIgnorada($id,$m){$this->rows[$id]['NOT_Status']='ignorada';return true;}
}
class FnScheduler {public $tarefas=[];public function agendarAgora($tipo,$payload,$chave,$prioridade,$max){$this->tarefas[$chave]=compact('tipo','payload');return ['id'=>count($this->tarefas)];}}

function fnCobranca($id,$vencimento,$status='pendente',$ass=10,$efetivo=null){return ['COB_ID'=>$id,'CLI_ID'=>1,'ASS_ID'=>$ass,'PLA_ID'=>1,'COB_Status'=>$status,'COB_Valor'=>'49.90','COB_DataVencimento'=>$vencimento,'COB_DataVencimentoEfetivo'=>$efetivo,'COB_VencimentoFinanceiro'=>$efetivo?:$vencimento,'COB_LinkPagamento'=>'https://teste.local/pagar'];}
function fnServico($cob,$ass,$not,$ent,$sch,$pol,$hoje='2026-08-28',$permitidas=''){return new FinanceiroNotificacaoService($cob,$ass,new FnClientes(),new FnPlanos(),$not,$ent,$sch,$pol,fn()=>new DateTimeImmutable($hoje),$permitidas);}

$casos=[
    ['2026-09-04',EventoNotificacao::COBRANCA_DISPONIVEL,'regular'],
    ['2026-08-31',EventoNotificacao::LEMBRETE_VENCIMENTO_D3,'regular'],
    ['2026-08-27',EventoNotificacao::COBRANCA_VENCIDA_D1,'tolerancia'],
    ['2026-08-25',EventoNotificacao::LEMBRETE_VENCIDA_D3,'tolerancia'],
    ['2026-08-23',EventoNotificacao::AVISO_SUSPENSAO_D5,'tolerancia'],
    ['2026-08-21',EventoNotificacao::SUSPENSAO_INADIMPLENCIA_D7,'suspenso'],
];
foreach($casos as $i=>[$data,$evento,$situacao]){$c=new FnCobrancas();$c->rows[1]=fnCobranca(1,$data);$a=new FnAssinaturas();$n=new FnNotificacoes();$e=new FnEntregas();$s=new FnScheduler();$p=new FnPolicy();$p->resultado=['situacao'=>$situacao,'cobranca_id'=>1];fnServico($c,$a,$n,$e,$s,$p)->planejar();fnAssert(count($n->rows)===1&&reset($n->rows)['NOT_Tipo']===$evento,'marco financeiro '.$evento.' deve ser reservado');}

$c=new FnCobrancas();$c->rows[1]=fnCobranca(1,'2026-08-28');$n=new FnNotificacoes();fnServico($c,new FnAssinaturas(),$n,new FnEntregas(),new FnScheduler(),new FnPolicy())->planejar();fnAssert(count($n->rows)===0,'D0 não deve gerar comunicação');
$c->rows[1]=fnCobranca(1,'2026-08-01','pendente',10,'2026-09-04');fnServico($c,new FnAssinaturas(),$n=new FnNotificacoes(),new FnEntregas(),new FnScheduler(),new FnPolicy())->planejar();fnAssert(reset($n->rows)['NOT_Tipo']===EventoNotificacao::COBRANCA_DISPONIVEL,'vencimento efetivo recuperado deve prevalecer sobre competência antiga');

$c=new FnCobrancas();$c->rows[1]=fnCobranca(1,'2026-09-04');$n=new FnNotificacoes();$s=new FnScheduler();$svc=fnServico($c,new FnAssinaturas(),$n,new FnEntregas(),$s,new FnPolicy());$svc->planejar();$svc->planejar();fnAssert(count($n->rows)===1&&count($s->tarefas)===1,'planejamento repetido deve permanecer idempotente');
$dadosDisponivel=json_decode($n->rows[1]['NOT_Dados'],true);fnAssert($dadosDisponivel['link']==='https://teste.local/pagar','cobrança disponível deve preservar o link de pagamento válido');
$c->rows[1]['COB_Status']='pago';$svc->enviar(1);fnAssert($n->rows[1]['NOT_Status']==='ignorada','pagamento entre planejamento e envio deve ignorar comunicação sem retry');

foreach([['pago',10],['cancelado',10],['pendente',null],['pendente',9]] as [$status,$assId]){$c=new FnCobrancas();$c->rows[1]=fnCobranca(1,'2026-09-04',$status,$assId);$n=new FnNotificacoes();fnServico($c,new FnAssinaturas(),$n,new FnEntregas(),new FnScheduler(),new FnPolicy())->planejar();fnAssert(count($n->rows)===0,'cobrança paga, cancelada, legada ou de assinatura anterior não deve ser planejada');}
$a=new FnAssinaturas();$a->pendente=['ASS_ID'=>20,'CLI_ID'=>1,'ASS_Status'=>'pendente'];$c=new FnCobrancas();$c->rows[1]=fnCobranca(1,'2026-09-04','pendente',20);$n=new FnNotificacoes();fnServico($c,$a,$n,new FnEntregas(),new FnScheduler(),new FnPolicy())->planejar();fnAssert(count($n->rows)===0,'assinatura ativa deve prevalecer sobre pendente residual');

$c=new FnCobrancas();$c->rows[1]=fnCobranca(1,'2026-08-21','pago');$n=new FnNotificacoes();$e=new FnEntregas();$svc=fnServico($c,new FnAssinaturas(),$n,$e,new FnScheduler(),new FnPolicy());$svc->agendarPagamentoConfirmado(1,'suspenso');$svc->agendarPagamentoConfirmado(1,'suspenso');fnAssert(count($n->rows)===1,'pagamento confirmado deve possuir reserva única');$dadosPagamento=json_decode($n->rows[1]['NOT_Dados'],true);fnAssert($dadosPagamento['link']==='https://teste.local/index.php?url=financeiro'&&strpos($dadosPagamento['link'],'/pagar')===false,'pagamento confirmado deve abrir o Financeiro, não a cobrança já paga no gateway');$svc->enviar(1);$svc->enviar(1);fnAssert($e->envios===1&&$n->rows[1]['NOT_Status']==='enviada','entrega repetida não pode reenviar canal já concluído');

$c=new FnCobrancas();$c->rows[1]=fnCobranca(1,'2026-09-04');$n=new FnNotificacoes();$e=new FnEntregas();$e->resultado=['sucesso'=>false,'status'=>'erro_temporario'];$svc=fnServico($c,new FnAssinaturas(),$n,$e,new FnScheduler(),new FnPolicy());$svc->planejar();$retry=false;try{$svc->enviar(1);}catch(Services\Tasks\TaskRetryException $ex){$retry=true;}fnAssert($retry&&$n->rows[1]['NOT_Status']==='erro_temporario','falha temporária deve delegar retry ao scheduler');$e->resultado=['sucesso'=>true,'status'=>'enviada'];$svc->enviar(1);fnAssert($n->rows[1]['NOT_Status']==='enviada','retry posterior deve concluir o mesmo canal reservado');
$c=new FnCobrancas();$c->rows[1]=fnCobranca(1,'2026-09-04');$n=new FnNotificacoes();$e=new FnEntregas();$e->resultado=['sucesso'=>false,'status'=>'erro_definitivo'];$svc=fnServico($c,new FnAssinaturas(),$n,$e,new FnScheduler(),new FnPolicy());$svc->planejar();$svc->enviar(1);fnAssert($n->rows[1]['NOT_Status']==='erro_definitivo','falha permanente deve encerrar sem retry');

$c=new FnCobrancas();$c->rows[38]=fnCobranca(38,'2026-09-04');$n=new FnNotificacoes();$s=new FnScheduler();fnServico($c,new FnAssinaturas(),$n,new FnEntregas(),$s,new FnPolicy(),'2026-08-28','')->planejar();fnAssert(count($n->rows)===1&&count($s->tarefas)===1,'whitelist vazia deve manter o comportamento normal');
$c=new FnCobrancas();$c->rows[38]=fnCobranca(38,'2026-09-04');$n=new FnNotificacoes();$s=new FnScheduler();fnServico($c,new FnAssinaturas(),$n,new FnEntregas(),$s,new FnPolicy(),'2026-08-28','38')->planejar();fnAssert(count($n->rows)===1&&count($s->tarefas)===1&&(int)reset($n->rows)['COB_ID']===38,'whitelist deve permitir a COB 38');
$c=new FnCobrancas();$c->rows[40]=fnCobranca(40,'2026-09-04');$n=new FnNotificacoes();$s=new FnScheduler();fnServico($c,new FnAssinaturas(),$n,new FnEntregas(),$s,new FnPolicy(),'2026-08-28','38')->planejar();fnAssert(count($n->rows)===0&&count($s->tarefas)===0,'outra cobrança elegível fora da whitelist não deve criar notificação ou tarefa');
$c=new FnCobrancas();$c->rows[40]=fnCobranca(40,'2026-08-21','pago');$n=new FnNotificacoes();$s=new FnScheduler();$reservadas=fnServico($c,new FnAssinaturas(),$n,new FnEntregas(),$s,new FnPolicy(),'2026-08-28','38')->agendarPagamentoConfirmado(40,'suspenso');fnAssert($reservadas===0&&count($n->rows)===0&&count($s->tarefas)===0,'pagamento confirmado fora da whitelist não deve criar notificação ou tarefa');

$texto=file_get_contents(__DIR__.'/../database/migrations/20260828_add_financeiro_notificacoes.sql');$modelo=file_get_contents(__DIR__.'/../app/Models/Notificacao.php');fnAssert(strpos($texto,'NOT_ReservadaEm')!==false&&strpos($texto,"'ignorada'")!==false&&strpos($modelo,'DATE_SUB(NOW()')!==false,'migration e modelo devem suportar lease expirado recuperável e descarte explícito');
echo "FinanceiroNotificacaoServiceTest OK\n";
