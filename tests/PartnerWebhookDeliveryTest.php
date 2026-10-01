<?php
function pwdAssert($c,$m){if(!$c){fwrite(STDERR,"FAIL: {$m}\n");exit(1);}}
$r=dirname(__DIR__);
$m=file_get_contents($r.'/app/Models/ParceiroWebhookEvento.php');
$e=file_get_contents($r.'/app/Services/PartnerWebhookEventService.php');
$d=file_get_contents($r.'/app/Services/PartnerWebhookDeliveryService.php');
$i=file_get_contents($r.'/app/Services/MetaWebhookMessageIngestionService.php');
$s=file_get_contents($r.'/app/Services/MetaStatusWebhookService.php');
$w=file_get_contents($r.'/app/Services/WorkerService.php');
$h=file_get_contents($r.'/public/webhook/meta.php');
$g=file_get_contents($r.'/database/migrations/20261001_create_partner_webhook_queue.sql');
pwdAssert(strpos($g,'parceiro_webhook_eventos')!==false && strpos($g,'PWE_EventId')!==false,'migration deve criar fila/historico');
pwdAssert(strpos($e,"'message.received'")!==false && strpos($e,"'message.reaction'")!==false,'inbound deve ser normalizado');
pwdAssert(strpos($e,"$tipo=$dados['tipo']==='reaction' ? 'message.reaction' : 'message.received';")!==false,'inbound deve continuar mapeando mensagem comum para message.received');
pwdAssert(strpos($e,"MSG_Origem")!==false && strpos($e,"partner_api")!==false,'status deve restringir eventos a mensagens originadas pela Partner API');
pwdAssert(strpos($m,"MSG_Origem")!==false,'modelo deve retornar a origem persistida da mensagem');
pwdAssert(strpos($d,'hash_hmac')!==false && strpos($d,'X-Disparador-Signature: sha256=')!==false,'entrega deve assinar HMAC');
pwdAssert(strpos($d,'CURLOPT_FOLLOWLOCATION=>false')!==false && strpos($d,'FILTER_FLAG_NO_PRIV_RANGE')!==false && strpos($d,'CURLOPT_RESOLVE')!==false,'entrega deve mitigar SSRF e DNS rebinding');
pwdAssert(strpos($d,'2**')!==false && strpos($d,'PWE_MaxTentativas')!==false,'entrega deve ter retry exponencial');
pwdAssert(strpos($i,'partnerWebhook')!==false,'ingestao deve publicar inbound');
pwdAssert(strpos($s,'partnerWebhook')!==false,'status deve publicar eventos');
pwdAssert(strpos($w,'partner_webhooks')!==false,'worker existente deve processar fila Partner');
pwdAssert(strpos($h,'PartnerWebhookEventService')!==false,'webhook Meta deve conectar publicadores');
echo "Partner webhook delivery static tests passed\n";
