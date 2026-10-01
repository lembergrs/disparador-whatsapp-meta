<?php
function rlAssert($c,$m){if(!$c){fwrite(STDERR,"FAIL: {$m}\n");exit(1);}}
$r=dirname(__DIR__);
$s=file_get_contents($r.'/app/Services/PartnerApiRateLimitService.php');
$c=file_get_contents($r.'/app/Controllers/ApiV1Controller.php');
$d=file_get_contents($r.'/docs/partner-api-v1.md');
rlAssert(strpos($s,"'messages'=>['limit'=>60,'window'=>60]")!==false,'mensagens 60/min');
rlAssert(strpos($s,"'media_upload'=>['limit'=>20,'window'=>60]")!==false,'upload 20/min');
rlAssert(strpos($s,"'read'=>['limit'=>120,'window'=>60]")!==false,'leituras 120/min');
rlAssert(strpos($s,'flock($fp,LOCK_EX)')!==false,'contador deve ter lock');
rlAssert(strpos($c,"aplicarRateLimit(\$parceiro,'messages')")!==false,'messages protegido');
rlAssert(strpos($c,"aplicarRateLimit(\$parceiro,'media_upload')")!==false,'upload protegido');
rlAssert(substr_count($c,"aplicarRateLimit(\$parceiro,'read')")>=3,'leituras protegidas');
rlAssert(strpos($c,"'rate_limit_exceeded'")!==false && strpos($c,'],429)')!==false,'deve responder 429');
rlAssert(strpos($c,"header('Retry-After: '")!==false,'deve informar Retry-After');
rlAssert(strpos($d,'X-RateLimit-Remaining')!==false,'docs devem explicar headers');
echo "Partner rate limit static tests passed\n";
