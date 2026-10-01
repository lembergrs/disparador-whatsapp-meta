<?php
function pwhpAssert($c,$m){if(!$c){fwrite(STDERR,"FAIL: {$m}\n");exit(1);}}
$r=dirname(__DIR__);
$m=file_get_contents($r.'/app/Models/ParceiroWebhookEvento.php');
$c=file_get_contents($r.'/app/Controllers/ParceiroClientesController.php');
$v=file_get_contents($r.'/app/Views/parceiros/clientes_partner.php');
pwhpAssert(strpos($m,'OFFSET {$offset}')!==false,'modelo deve paginar com offset');
pwhpAssert(strpos($m,'function contarPartner')!==false && strpos($m,'COUNT(*)')!==false,'modelo deve contar entregas');
pwhpAssert(strpos($c,"webhook_page")!==false && strpos($c,'$webhookPorPagina=50')!==false,'controller deve aceitar pagina e manter 50 itens');
pwhpAssert(strpos($v,'Anterior')!==false && strpos($v,'Próxima')!==false,'view deve exibir navegacao');
pwhpAssert(strpos($v,'50 por página')!==false,'view deve informar tamanho da pagina');
echo "Partner webhook history pagination static tests passed\n";
