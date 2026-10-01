<?php
function pomAssert($c,$m){if(!$c){fwrite(STDERR,"FAIL: {$m}\n");exit(1);}}
$r=dirname(__DIR__);
$c=file_get_contents($r.'/app/Controllers/ApiV1Controller.php');
$s=file_get_contents($r.'/app/Services/PartnerMessageService.php');
$d=file_get_contents($r.'/docs/partner-api-v1.md');
$meta=file_get_contents($r.'/app/Services/MetaService.php');
pomAssert(strpos($c,"['GET','POST']")!==false,'media deve aceitar upload POST');
pomAssert(strpos($c,'uploadMensagemMedia')!==false,'upload deve reutilizar MetaMediaService');
pomAssert(strpos($c,"'image'=>'IMAGE','document'=>'DOCUMENT','audio'=>'AUDIO'")!==false,'upload deve limitar tipos');
pomAssert(strpos($c,'buscarCanalAutorizado')!==false,'upload deve validar canal Partner');
pomAssert(strpos($s,"['text','template','image','document','audio']")!==false,'messages deve aceitar mídia');
pomAssert(strpos($s,"in_array(\$type,['image','document','audio'],true)")!==false,'service deve tratar mídia');
pomAssert(strpos($s,'$meta->enviarMidia')!==false,'service deve enviar mídia pela Meta');
pomAssert(strpos($meta,"['image', 'video', 'document', 'audio']")!==false,'MetaService deve permitir envio de audio');
pomAssert(strpos($s,"'media_id'=>\$extra['media_id']??null")!==false,'mensagem local deve guardar media id');
pomAssert(strpos($d,'multipart/form-data')!==false && strpos($d,'Idempotency-Key')!==false,'docs devem explicar fluxo');
$mm=file_get_contents($r.'/app/Services/MetaMediaService.php');
pomAssert(strpos($mm,"const TIPO_AUDIO = 'AUDIO'")!==false,'MetaMediaService deve suportar audio');
pomAssert(strpos($mm,"['aac', 'amr', 'mp3', 'm4a', 'ogg']")!==false,'audio deve limitar extensoes');
pomAssert(strpos($mm,"'audio/ogg'")!==false && strpos($mm,"'audio/mpeg'")!==false,'audio deve validar MIME');
pomAssert(strpos($d,'"type":"audio"')!==false,'docs devem conter exemplo de audio');
echo "Partner outbound media static tests passed\n";
