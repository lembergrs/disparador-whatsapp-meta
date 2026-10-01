<?php
function pbeAssert($c,$m){if(!$c){fwrite(STDERR,"FAIL: {$m}\n");exit(1);}}
$r=dirname(__DIR__);
$i=file_get_contents($r.'/app/Services/MetaWebhookMessageIngestionService.php');
$e=file_get_contents($r.'/app/Services/PartnerWebhookEventService.php');
$h=file_get_contents($r.'/public/webhook/meta.php');
pbeAssert(strpos($i,"'business_app'")!==false && strpos($i,'partnerWebhook')!==false,'echo criado deve acionar callback Partner');
pbeAssert(strpos($e,'businessAppEcho')!==false,'service Partner deve tratar echo');
pbeAssert(strpos($e,"'source'=>'business_app'")!==false && strpos($e,"'human'=>true")!==false,'payload deve identificar intervencao humana');
pbeAssert(strpos($e,"'message.reaction'")!==false && strpos($e,"'message.sent'")!==false,'echo deve publicar reacao ou mensagem enviada');
pbeAssert(strpos($e,"'echo:'")!==false,'echo deve ter chave idempotente propria');
pbeAssert(strpos($h,"$origem === 'business_app'")!==false && strpos($h,'businessAppEcho')!==false,'webhook Meta deve rotear business_app');
echo "Partner business app echo static tests passed\n";
