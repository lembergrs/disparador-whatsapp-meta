<?php
require_once __DIR__ . '/../app/Services/MensagemStatusService.php';
require_once __DIR__ . '/../app/Services/MetaStatusWebhookService.php';
use Services\MensagemStatusService; use Services\MetaStatusWebhookService;
function webhookStatusAssert($c,$m){ if(!$c){ fwrite(STDERR,"FAIL: {$m}\n"); exit(1); } }
class MensagensWebhookFake {
 public $rows=['wamid.1'=>['status'=>'pending'],'wamid.2'=>['status'=>'pending']];
 public function atualizarStatusPorMetaMessageId($id,$novo,$data,$erro){ if(!isset($this->rows[$id])||!MensagemStatusService::podeAvancar($this->rows[$id]['status'],$novo)) return false; $this->rows[$id]=['status'=>$novo,'data'=>$data,'erro'=>$erro]; return true; }
}
$repo=new MensagensWebhookFake(); $secundarios=[];
$service=new MetaStatusWebhookService($repo,function($id,$status,$erro)use(&$secundarios){$secundarios[]=compact('id','status','erro');});
$r=$service->processarLote([
 ['id'=>'wamid.1','status'=>'sent','timestamp'=>'1785405600'],
 ['id'=>'','status'=>'read'],
 ['id'=>'wamid.desconhecida','status'=>'sent'],
 ['id'=>'wamid.2','status'=>'delivered'],
 ['id'=>'wamid.2','status'=>'sent'],
 ['id'=>'wamid.1','status'=>'delivered'],
 ['id'=>'wamid.1','status'=>'read'],
 ['id'=>'wamid.1','status'=>'delivered'],
]);
webhookStatusAssert($repo->rows['wamid.1']['status']==='read','sent/delivered/read devem atualizar mensagem correta');
webhookStatusAssert($repo->rows['wamid.2']['status']==='delivered','delivered seguido de sent não pode regredir');
webhookStatusAssert($r['processados']===4 && $r['ignorados']===4,'inválido, desconhecido, repetido e regressivo devem ser ignorados sem parar lote');
$rFalha=$service->processarLote([['id'=>'wamid.2','status'=>'failed','errors'=>[['code'=>131026,'error_data'=>['details'=>'token=secreto Não entregue']]]],['id'=>'wamid.3','status'=>'bogus']]);
webhookStatusAssert($repo->rows['wamid.2']['status']==='delivered','falha não deve substituir delivered');
$repo->rows['wamid.2']=['status'=>'processing']; $service->processarLote([['id'=>'wamid.2','status'=>'failed','errors'=>[['code'=>131026,'message'=>'Falha segura']]]]);
webhookStatusAssert($repo->rows['wamid.2']['status']==='failed' && $repo->rows['wamid.2']['erro']['codigo']==='131026','failed deve registrar código e erro');
webhookStatusAssert(strpos($repo->rows['wamid.2']['erro']['mensagem'],'secreto')===false,'erro deve ser sanitizado');
echo "MetaStatusWebhookServiceTest OK\n";
