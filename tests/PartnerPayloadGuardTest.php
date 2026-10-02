<?php
function pgAssert($c,$m){if(!$c){fwrite(STDERR,"FAIL: {$m}\n");exit(1);}}
$r=dirname(__DIR__);
$c=file_get_contents($r.'/app/Controllers/ApiV1Controller.php');
$s=file_get_contents($r.'/app/Services/PartnerMessageService.php');
$d=file_get_contents($r.'/docs/partner-api-v1.md');
pgAssert(strpos($c,'$contentLength>65536')!==false,'JSON deve limitar 64KB');
pgAssert(strpos($c,"'payload_too_large'")!==false && strpos($c,'],413)')!==false,'payload grande deve retornar 413');
pgAssert(strpos($c,'$contentLength>18874368')!==false,'multipart deve limitar 18MB');
pgAssert(strpos($s,'>4096')!==false,'texto deve limitar 4096');
pgAssert(strpos($s,'>1024')!==false,'caption/variavel deve limitar 1024');
pgAssert(strpos($s,'count($variables)>100')!==false,'templates devem limitar variaveis');
pgAssert(strpos($s,'>255')!==false,'filename deve limitar 255');
pgAssert(strpos($d,'Limites de payload e proteção operacional')!==false,'docs devem conter limites');
echo "Partner payload guard static tests passed\n";
