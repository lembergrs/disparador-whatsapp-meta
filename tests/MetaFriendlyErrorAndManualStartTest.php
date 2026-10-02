<?php
function meAssert($c,$m){if(!$c){fwrite(STDERR,"FAIL: {$m}\n");exit(1);}}
$r=dirname(__DIR__);
$s=file_get_contents($r.'/app/Services/MetaErrorMessageService.php');
$w=file_get_contents($r.'/public/webhook/meta.php');
$d=file_get_contents($r.'/app/Controllers/DisparoController.php');
$c=file_get_contents($r.'/app/Controllers/CampanhaController.php');
$j=file_get_contents($r.'/public/assets/js/app.js');

meAssert(strpos($s,"$codigo==='131026'")!==false,'deve mapear 131026');
meAssert(strpos($s,'pode não possuir WhatsApp ativo')!==false,'mensagem 131026 deve ser cautelosa');
meAssert(strpos($w,'MetaErrorMessageService::amigavel')!==false,'webhook deve persistir erro amigavel');
meAssert(strpos($d,'MetaErrorMessageService::codigoDoRetorno')!==false,'historico manual deve interpretar codigo salvo');
meAssert(strpos($c,'MetaErrorMessageService::codigoDoRetorno')!==false,'campanha deve interpretar codigo salvo');
meAssert(strpos($j,'processamentoImediatoAtivo = true;')!==false,'disparo manual deve iniciar processamento imediato');
meAssert(strpos($j,'processarProximoBloco();')!==false,'primeiro bloco deve iniciar sem aguardar worker');
meAssert(strpos($j,'agendarProximoBloco(2000);')!==false,'blocos seguintes devem usar pausa de 2s');
echo "Meta friendly errors and manual immediate start static tests passed\n";
