<?php
function ptAssert($c,$m){if(!$c){fwrite(STDERR,"FAIL: {$m}\n");exit(1);}}
$r=dirname(__DIR__);
$c=file_get_contents($r.'/app/Controllers/ParceiroTemplatesController.php');
$p=file_get_contents($r.'/app/Models/ParceiroConvite.php');
$t=file_get_contents($r.'/app/Models/TemplateMeta.php');
$v=file_get_contents($r.'/app/Views/templates/index.php');
$l=file_get_contents($r.'/app/Views/parceiros/clientes_partner.php');
$a=file_get_contents($r.'/app/Core/Auth.php');
ptAssert(strpos($c,'buscarClienteGerenciavel')!==false,'controller deve validar cliente do Partner');
ptAssert(strpos($c,'buscarCanalGerenciavel')!==false,'controller deve validar canal do Partner');
ptAssert(strpos($p,"i.PAR_ID=? AND i.CLI_ID=?")!==false,'cliente deve ser validado por PAR_ID + CLI_ID');
ptAssert(strpos($p,"pc.PAR_ID=? AND pc.CLI_ID=? AND pc.MTA_ID=?")!==false,'canal deve ser validado por PAR_ID + CLI_ID + MTA_ID');
ptAssert(strpos($t,'listarPorClienteConta')!==false,'templates devem ser filtrados por cliente e canal');
ptAssert(strpos($c,"new MetaService(\$metaId,(int)\$cliente['CLI_ID'])")!==false,'MetaService deve usar CLI_ID do cliente');
ptAssert(strpos($c,'$this->templates->buscarPorCliente')!==false,'inativação deve validar propriedade do template');
ptAssert(strpos($v,'$modoPartner')!==false && strpos($v,'$partnerClienteId')!==false,'view completa deve suportar contexto Partner');
ptAssert(strpos($l,'parceiroTemplates&cliente_id=')!==false,'lista de clientes deve abrir templates no cliente escolhido');
ptAssert(strpos($a,"'parceiroTemplates'")!==false,'rota Partner deve liberar controller dedicado');
echo "Partner template isolation static tests passed\n";
