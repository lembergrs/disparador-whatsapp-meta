<?php
function pwwAssert($c,$m){if(!$c){fwrite(STDERR,"FAIL: {$m}\n");exit(1);}}
$r=dirname(__DIR__);
$general=file_get_contents($r.'/app/Services/WorkerService.php');
$worker=file_get_contents($r.'/partner-webhook-worker.php');
$unit=file_get_contents($r.'/deploy/systemd/disparador-partner-webhook.service');
pwwAssert(strpos($general,'partnerWebhookDelivery')===false && strpos($general,"'partner_webhooks'")===false,'worker geral nao deve consumir fila Partner');
pwwAssert(strpos($worker,'PartnerWebhookDeliveryService')!==false && strpos($worker,'processarPendentes(50)')!==false,'worker Partner deve consumir fila em lote');
pwwAssert(strpos($worker,'usleep(500000)')!==false,'fila vazia deve ter espera curta');
pwwAssert(strpos($worker,'LOCK_EX|LOCK_NB')!==false,'worker Partner deve impedir instancia duplicada');
pwwAssert(strpos($worker,'SIGTERM')!==false && strpos($worker,'SIGINT')!==false,'worker deve prever encerramento limpo');
pwwAssert(strpos($unit,'Restart=always')!==false,'systemd deve reiniciar worker');
echo "Partner webhook worker static tests passed\n";
