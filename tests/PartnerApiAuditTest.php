<?php
function paaAssert($c,$m){if(!$c){fwrite(STDERR,"FAIL: {$m}\n");exit(1);}}
$r=dirname(__DIR__);
$m=file_get_contents($r.'/app/Models/ParceiroApiAuditoria.php');
$s=file_get_contents($r.'/app/Services/PartnerApiAuditService.php');
$c=file_get_contents($r.'/app/Controllers/ApiV1Controller.php');
$a=file_get_contents($r.'/app/Controllers/ParceiroAdminController.php');
$v=file_get_contents($r.'/app/Views/parceiros/auditoria.php');
$g=file_get_contents($r.'/database/migrations/20261002_create_partner_api_auditoria.sql');
paaAssert(strpos($g,'CREATE TABLE IF NOT EXISTS parceiro_api_auditoria')!==false,'migration deve criar auditoria');
paaAssert(strpos($s,"'[REDACTED]'")!==false,'snapshot deve remover segredos');
paaAssert(strpos($s,'8192')!==false,'snapshot deve ser limitado');
paaAssert(strpos($c,"'rate_limit_exceeded',429")!==false,'429 deve ser auditado');
paaAssert(strpos($c,"'payload_too_large',413")!==false,'413 deve ser auditado');
paaAssert(strpos($c,"'api_validation_error'")!==false,'validacoes devem ser auditadas');
paaAssert(strpos($a,'public function auditoria()')!==false,'admin deve expor relatorio');
paaAssert(strpos($v,'Segurança / Auditoria da Partner API')!==false,'view deve existir');
echo "Partner API audit static tests passed\n";
