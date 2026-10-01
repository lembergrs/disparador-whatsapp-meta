<?php
function pimAssert($c,$m){if(!$c){fwrite(STDERR,"FAIL: {$m}\n");exit(1);}}
$r=dirname(__DIR__);
$c=file_get_contents($r.'/app/Controllers/ApiV1Controller.php');
$e=file_get_contents($r.'/app/Services/PartnerWebhookEventService.php');
$m=file_get_contents($r.'/app/Models/Conversa.php');
$d=file_get_contents($r.'/docs/partner-api-v1.md');
pimAssert(strpos($c,'public function media()')!==false,'controller deve expor media');
pimAssert(strpos($c,'$this->authService->autenticar()')!==false,'download deve autenticar Partner');
pimAssert(strpos($c,'listarCanaisAutorizados')!==false && strpos($c,'buscarMensagemPartner')!==false,'download deve validar escopo Partner');
pimAssert(strpos($c,"['audio','image','document']")!==false,'download deve limitar tipos da primeira fase');
pimAssert(strpos($c,'MetaMediaService')!==false && strpos($c,'obterMidiaMensagem')!==false,'download deve reutilizar serviço seguro de mídia');
pimAssert(strpos($m,'function buscarMensagemPartner')!==false && strpos($m,'c.CLI_ID=? AND c.MTA_ID=?')!==false,'mensagem deve ser localizada por cliente e canal');
pimAssert(strpos($e,"'download_url'=>\$this->mediaUrl")!==false,'webhook deve publicar URL de download');
pimAssert(strpos($e,"'id'=>\$dados['media_id']")===false,'webhook não deve expor media id da Meta');
pimAssert(strpos($d,'Authorization: Bearer <API_KEY>')!==false,'documentação deve explicar autenticação da mídia');
echo "Partner inbound media static tests passed\n";
