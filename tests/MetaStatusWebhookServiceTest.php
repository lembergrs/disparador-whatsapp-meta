<?php
require_once __DIR__ . '/../app/Services/MensagemStatusService.php';
require_once __DIR__ . '/../app/Services/MetaStatusWebhookService.php';
use Services\MensagemStatusService; use Services\MetaStatusWebhookService;
function webhookStatusAssert($c,$m){ if(!$c){ fwrite(STDERR,"FAIL: {$m}\n"); exit(1); } }
class MensagensWebhookFake {
 public $rows=['1:wamid.1'=>['status'=>'pending'],'1:wamid.2'=>['status'=>'pending']];
 public function atualizarStatusPorMetaMessageId($id,$novo,$data,$erro,$metaId=null){
  $key=(int)$metaId.':'.$id;
  if(!isset($this->rows[$key])) return false;
  if(!MensagemStatusService::podeAvancar($this->rows[$key]['status'],$novo)) return false;
  $this->rows[$key]=array_merge($this->rows[$key],['status'=>$novo,'data'=>$data,'erro'=>$erro]); return true;
 }
 public function atualizarPricingPorMetaMessageId($id,$pricing,$metaId=null){
  $key=(int)$metaId.':'.$id;
  if(!isset($this->rows[$key])||!$pricing) return false;
  if(array_key_exists('billable',$pricing)) $pricing['billable']=$pricing['billable']?1:0;
  $this->rows[$key]['pricing']=array_merge($this->rows[$key]['pricing']??[],$pricing); return true;
 }
 public function row($metaId,$id){ return $this->rows[(int)$metaId.':'.$id]??null; }
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
],1);
webhookStatusAssert($repo->row(1,'wamid.1')['status']==='read','sent/delivered/read devem atualizar mensagem correta');
webhookStatusAssert($repo->row(1,'wamid.2')['status']==='delivered','delivered seguido de sent não pode regredir');
webhookStatusAssert($r['processados']===4 && $r['ignorados']===4,'inválido, desconhecido, repetido e regressivo devem ser ignorados sem parar lote');
$rFalha=$service->processarLote([['id'=>'wamid.2','status'=>'failed','errors'=>[['code'=>131026,'error_data'=>['details'=>'token=secreto Não entregue']]]],['id'=>'wamid.3','status'=>'bogus']],1);
webhookStatusAssert($repo->row(1,'wamid.2')['status']==='delivered','falha não deve substituir delivered');
$repo->rows['1:wamid.2']=['status'=>'processing']; $service->processarLote([['id'=>'wamid.2','status'=>'failed','errors'=>[['code'=>131026,'message'=>'Falha segura']]]],1);
webhookStatusAssert($repo->row(1,'wamid.2')['status']==='failed' && $repo->row(1,'wamid.2')['erro']['codigo']==='131026','failed deve registrar código e erro');
webhookStatusAssert(strpos($repo->row(1,'wamid.2')['erro']['mensagem'],'secreto')===false,'erro deve ser sanitizado');
$conversasComErro = new class {
 public function atualizarStatusPorMetaMessageId($id,$novo,$data,$erro){ throw new RuntimeException('falha isolada'); }
};
$notificacoesProcessadas=[];
$isolado = new MetaStatusWebhookService($conversasComErro, function(){ throw new RuntimeException('falha secundária'); }, function($id,$status,$erro,$data) use (&$notificacoesProcessadas){ $notificacoesProcessadas[]=$id; return true; });
$resultadoIsolado = $isolado->processarLote([['id'=>'wamid.institucional','status'=>'delivered'],['id'=>'','status'=>'read']],1);
webhookStatusAssert($notificacoesProcessadas===['wamid.institucional'] && $resultadoIsolado['processados']===1 && $resultadoIsolado['ignorados']===1,'notificações devem atualizar mesmo se conversas ou secundários falharem');

$pricingRepo = new MensagensWebhookFake();
$pricingService = new MetaStatusWebhookService($pricingRepo);
$pricingService->processarLote([['id'=>'wamid.1','status'=>'delivered','pricing'=>['billable'=>true,'pricing_model'=>'CBP','category'=>'service','type'=>'regular','market'=>'BR','currency'=>'BRL']]],1);
webhookStatusAssert($pricingRepo->row(1,'wamid.1')['status']==='delivered','status com pricing deve ser atualizado');
webhookStatusAssert($pricingRepo->row(1,'wamid.1')['pricing']['category']==='service' && $pricingRepo->row(1,'wamid.1')['pricing']['billable']===1 && $pricingRepo->row(1,'wamid.1')['pricing']['model']==='CBP','pricing disponível deve ser preservado');
$pricingService->processarLote([['id'=>'wamid.1','status'=>'read']],1);
webhookStatusAssert($pricingRepo->row(1,'wamid.1')['status']==='read' && $pricingRepo->row(1,'wamid.1')['pricing']['category']==='service','read sem pricing não deve apagar pricing anterior');
$pricingService->processarLote([['id'=>'wamid.desconhecida','status'=>'delivered','pricing'=>['category'=>'utility']]],1);
webhookStatusAssert($pricingRepo->row(1,'wamid.desconhecida')===null,'wamid desconhecido com pricing deve ser ignorado');
$pricingServiceComLoggerFalho = new MetaStatusWebhookService($pricingRepo, null, null, function(){ throw new RuntimeException('log indisponível'); });
$pricingServiceComLoggerFalho->processarLote([['id'=>'wamid.2','status'=>'sent','pricing'=>'invalido']],1);
webhookStatusAssert($pricingRepo->row(1,'wamid.2')['status']==='sent','pricing inválido e falha de log não podem interromper atualização do status');

$parcialRepo = new MensagensWebhookFake(); $parcialService = new MetaStatusWebhookService($parcialRepo);
$parcialService->processarLote([['id'=>'wamid.1','status'=>'sent','pricing'=>['billable'=>false]]],1);
webhookStatusAssert($parcialRepo->row(1,'wamid.1')['pricing']['billable']===0,'billable false deve chegar à persistência como zero');
$parcialService->processarLote([['id'=>'wamid.2','status'=>'sent','pricing'=>['billable'=>true]]],1);
webhookStatusAssert($parcialRepo->row(1,'wamid.2')['pricing']===['billable'=>1],'pricing somente billable deve ser aceito');
$parcialService->processarLote([['id'=>'wamid.2','status'=>'delivered','pricing'=>['category'=>'service']]],1);
webhookStatusAssert($parcialRepo->row(1,'wamid.2')['pricing']['category']==='service','pricing somente categoria deve ser aceito');
$antesVazio=$parcialRepo->row(1,'wamid.2');
$parcialService->processarLote([['id'=>'wamid.2','status'=>'read','pricing'=>[]],['id'=>'wamid.2','status'=>'read','pricing'=>null]],1);
webhookStatusAssert($parcialRepo->row(1,'wamid.2')['pricing']===$antesVazio['pricing'],'pricing vazio, null e evento repetido não devem apagar valores');

$duplicadoRepo = new MensagensWebhookFake();
$duplicadoRepo->rows['1:wamid.duplicado']=['status'=>'pending']; $duplicadoRepo->rows['2:wamid.duplicado']=['status'=>'pending'];
$duplicadoService = new MetaStatusWebhookService($duplicadoRepo);
$duplicadoService->processarLote([['id'=>'wamid.duplicado','status'=>'delivered','pricing'=>['category'=>'future_meta_category']]],1);
webhookStatusAssert($duplicadoRepo->row(1,'wamid.duplicado')['status']==='delivered' && $duplicadoRepo->row(1,'wamid.duplicado')['pricing']['category']==='future_meta_category','categoria desconhecida deve ser persistida na conta correta');
webhookStatusAssert($duplicadoRepo->row(2,'wamid.duplicado')===['status'=>'pending'],'wamid duplicado não pode atualizar outra conta Meta');

$fonteModel=file_get_contents(__DIR__.'/../app/Models/Conversa.php');
webhookStatusAssert(strpos($fonteModel,'INNER JOIN conversas c ON c.CVS_ID=m.CVS_ID')!==false && substr_count($fonteModel,'c.MTA_ID=?')>=2,'SQL de status e pricing deve restringir wamid por MTA_ID');
webhookStatusAssert(strpos($fonteModel,'array_key_exists($chave, $pricing)')!==false,'persistência deve distinguir billable false de campo ausente');
webhookStatusAssert(strpos($fonteModel,'public function atualizarPricingPorMetaMessageId')!==false,'status e pricing devem possuir operações independentes');

$logsPersistencia=[];
$falhaPersistencia = new class {
 public $status=null;
 public function atualizarStatusPorMetaMessageId($id,$novo){ $this->status=$novo; return true; }
 public function atualizarPricingPorMetaMessageId(){ throw new RuntimeException('token=segredo falha SQL'); }
};
$serviceFalha = new MetaStatusWebhookService($falhaPersistencia, null, null, function($acao,$dados) use (&$logsPersistencia){ $logsPersistencia[]=compact('acao','dados'); });
$resultadoFalha=$serviceFalha->processarLote([['id'=>'wamid.log','status'=>'delivered','pricing'=>['category'=>'service']]],9);
webhookStatusAssert($falhaPersistencia->status==='delivered' && $resultadoFalha['processados']===1,'falha de pricing não deve desfazer status persistido nem interromper processamento');
webhookStatusAssert($logsPersistencia[0]['acao']==='pricing_meta_persistencia_falhou' && $logsPersistencia[0]['dados']['meta_id']===9,'falha exclusiva de pricing deve ser identificada no log');
webhookStatusAssert(strpos($logsPersistencia[0]['dados']['erro'],'segredo')===false,'log de falha de pricing deve sanitizar segredos');
echo "MetaStatusWebhookServiceTest OK\n";
