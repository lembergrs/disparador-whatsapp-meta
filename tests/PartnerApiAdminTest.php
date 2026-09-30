<?php
function partnerAdminAssert($condition,$message){ if(!$condition){ fwrite(STDERR,"FAIL: {$message}\n"); exit(1); } }
$root=dirname(__DIR__);
$model=file_get_contents($root.'/app/Models/ParceiroApi.php');
$controller=file_get_contents($root.'/app/Controllers/ParceiroAdminController.php');
$index=file_get_contents($root.'/app/Views/parceiros/index.php');
$detail=file_get_contents($root.'/app/Views/parceiros/detalhe.php');
$docs=file_get_contents($root.'/docs/partner-api-v1.md');

partnerAdminAssert(strpos($controller,'Auth::admin()')!==false,'gestão deve ser exclusiva do admin');
partnerAdminAssert(strpos($model,"'dsp_live_' . bin2hex(random_bytes(32))")!==false,'API key deve usar segredo criptograficamente aleatório');
partnerAdminAssert(strpos($model,'hash(\'sha256\',$segredo)')!==false,'somente hash deve ser persistido');
partnerAdminAssert(strpos($model,"c.CLI_TipoConta IN ('cliente','cliente_partner_vinculado')")!==false,'admin deve poder autorizar cliente comum para homologação e cliente Partner vinculado');
partnerAdminAssert(strpos($model,"PAC_FaturavelDesde=NULL")!==false,'canal comum de homologação não deve entrar no faturamento Partner');
partnerAdminAssert(strpos($model,"c.CLI_TipoConta = 'cliente'")!==false,'API deve reconhecer canal comum explicitamente autorizado para homologação');
partnerAdminAssert(strpos($model,"UPDATE clientes SET CLI_TipoConta")===false,'autorização não deve alterar o tipo da conta original');
partnerAdminAssert(strpos($controller,'listarClientesAutorizaveisAdmin()')!==false,'tela admin deve carregar clientes autorizáveis para homologação');
partnerAdminAssert(strpos($detail,'Homologação')!==false,'UI deve identificar canal comum como homologação');
partnerAdminAssert(strpos($model,'m.MTA_ID=?')!==false && strpos($model,'m.MTA_Ativo=\'S\'')!==false,'MTA deve pertencer ao cliente e estar ativo');
partnerAdminAssert(strpos($detail,'Ela não será exibida novamente')!==false,'UI deve alertar sobre exibição única');
partnerAdminAssert(strpos($detail,'revogarChave')!==false,'UI deve permitir revogação');
partnerAdminAssert(strpos($index,'cliente_partner')!==false,'cadastro deve selecionar conta partner');
partnerAdminAssert(strpos($docs,'Provisionamento de acesso')!==false,'documentação deve explicar provisionamento');
partnerAdminAssert(strpos($docs,'Postman')!==false,'documentação deve orientar smoke test no Postman');
echo "Partner admin static tests passed\n";
