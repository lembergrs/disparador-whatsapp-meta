<?php
function pomAssert($c,$m){if(!$c){fwrite(STDERR,"FAIL: {$m}\n");exit(1);}}
$r=dirname(__DIR__);
$c=file_get_contents($r.'/app/Controllers/ApiV1Controller.php');
$s=file_get_contents($r.'/app/Services/PartnerMessageService.php');
$d=file_get_contents($r.'/docs/partner-api-v1.md');
pomAssert(strpos($c,"['GET','POST']")!==false,'media deve aceitar upload POST');
pomAssert(strpos($c,'uploadMensagemMedia')!==false,'upload deve reutilizar MetaMediaService');
pomAssert(strpos($c,"'image'=>'IMAGE','document'=>'DOCUMENT'")!==false,'upload deve limitar tipos');
pomAssert(strpos($c,'buscarCanalAutorizado')!==false,'upload deve validar canal Partner');
pomAssert(strpos($s,"['text','template','image','document']")!==false,'messages deve aceitar mídia');
pomAssert(strpos($s,"in_array(\$type,['image','document'],true)")!==false,'service deve tratar mídia');
pomAssert(strpos($s,'$meta->enviarMidia')!==false,'service deve enviar mídia pela Meta');
pomAssert(strpos($s,"'media_id'=>\$extra['media_id']??null")!==false,'mensagem local deve guardar media id');
pomAssert(strpos($d,'multipart/form-data')!==false && strpos($d,'Idempotency-Key')!==false,'docs devem explicar fluxo');
echo "Partner outbound media static tests passed\n";
