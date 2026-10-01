<?php
function pwcAssert($c,$m){if(!$c){fwrite(STDERR,"FAIL: {$m}\n");exit(1);}}
$r=dirname(__DIR__);
$m=file_get_contents($r.'/app/Models/ParceiroApi.php');
$c=file_get_contents($r.'/app/Controllers/ParceiroClientesController.php');
$v=file_get_contents($r.'/app/Views/parceiros/clientes_partner.php');
$g=file_get_contents($r.'/database/migrations/20261001_add_partner_webhook_config.sql');
$cfg=file_get_contents($r.'/config/config.php');
pwcAssert(strpos($g,'PAR_WebhookAtivo')!==false && strpos($g,'PAR_WebhookEventos')!==false && strpos($g,'PAR_WebhookSecretSalt')!==false,'migration deve criar configuração operacional do webhook');
pwcAssert(strpos($cfg,'PARTNER_WEBHOOK_SIGNING_KEY')!==false,'config deve expor chave mestra do webhook');
pwcAssert(strpos($m,'salvarWebhookParceiro')!==false,'model deve salvar webhook por Partner');
pwcAssert(strpos($m,"'message.received'")!==false && strpos($m,"'message.failed'")!==false,'eventos devem usar contrato normalizado');
pwcAssert(strpos($m,"'dsp_whsec_'")!==false && strpos($m,'hash_hmac')!==false,'segredo deve ser derivado para HMAC sem texto puro no banco');
pwcAssert(strpos($c,'public function salvarWebhook()')!==false,'Partner deve configurar o próprio webhook');
pwcAssert(strpos($c,'validarCsrfPost')!==false,'configuração deve exigir CSRF');
pwcAssert(strpos($v,'webhook_url')!==false && strpos($v,'eventos[]')!==false,'painel deve permitir URL e eventos');
pwcAssert(strpos($v,'regenerar_segredo')!==false,'painel deve permitir regenerar segredo');
echo "Partner webhook config static tests passed\n";
