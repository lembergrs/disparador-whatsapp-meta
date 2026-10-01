<?php
function ptlAssert($c,$m){if(!$c){fwrite(STDERR,"FAIL: {$m}\n");exit(1);}}
$r=dirname(__DIR__);
$c=file_get_contents($r.'/app/Controllers/ApiV1Controller.php');
$m=file_get_contents($r.'/app/Models/TemplateMeta.php');
$d=file_get_contents($r.'/docs/partner-api-v1.md');
ptlAssert(strpos($c,'public function templates()')!==false,'controller deve expor templates');
ptlAssert(strpos($c,"['PAR_ID'],\$clienteId,\$metaId")!==false,'endpoint deve validar canal Partner');
ptlAssert(strpos($c,'listarAprovadosParaPartner')!==false,'endpoint deve listar templates aprovados');
ptlAssert(strpos($c,"'variables'=>array_values(\$variaveis)")!==false,'resposta deve expor variaveis');
ptlAssert(strpos($c,"'components'=>\$componentes")!==false,'resposta deve expor componentes');
ptlAssert(strpos($m,"t.TMP_Status = 'APPROVED'")!==false,'model deve limitar templates aprovados');
ptlAssert(strpos($m,'m.CLI_ID = ?')!==false && strpos($m,'t.MTA_ID = ?')!==false,'model deve isolar cliente e canal');
ptlAssert(strpos($d,'GET /index.php?url=api/v1/templates&client_id=123&channel_id=456')!==false,'docs devem conter endpoint');
echo "Partner template list static tests passed\n";
