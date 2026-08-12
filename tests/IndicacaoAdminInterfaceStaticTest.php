<?php
$r=dirname(__DIR__);function iaa($v,$m){if(!$v){fwrite(STDERR,"FAIL: $m\n");exit(1);}}
$ctl=file_get_contents($r.'/app/Controllers/IndicacaoAdminController.php');$read=file_get_contents($r.'/app/Services/Indicacao/IndicacaoAdminReadService.php');$view=file_get_contents($r.'/app/Views/indicacao_admin/index.php');$menu=file_get_contents($r.'/app/Views/layouts/master.php');
iaa(substr_count($ctl,'Auth::admin()')>=3,'admin obrigatório em leitura e mutações');iaa(substr_count($ctl,'validarCsrfPost()')===2,'mutações usam CSRF');iaa(strpos($ctl,'IndicacaoCampanhaService')!==false&&strpos($ctl,'INSERT INTO indicacao_campanhas')===false,'campanha delegada ao domínio');
foreach(["SUM(IND_Status='aguardando_pagamento')","SUM(ICR_Status='liberado')",'indicacao_credito_reservas','indicacao_auditoria'] as $x)iaa(strpos($read,$x)!==false,'leitura operacional: '.$x);
foreach(['marcar crédito','liberar crédito','editar percentual','FIFO','prepararDesconto'] as $x)iaa(stripos($view,$x)===false,'UI não cria ação proibida: '.$x);
iaa(strpos($view,'IAU_Dados')===false&&strpos($view,'ProviderPayload')===false,'auditoria não despeja payload sensível');iaa(strpos($menu,'Programa de Indicação')!==false,'menu admin existe');
echo "IndicacaoAdminInterfaceStaticTest OK\n";
