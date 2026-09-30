<?php
function pciAssert($c,$m){if(!$c){fwrite(STDERR,"FAIL: {$m}\n");exit(1);}}
$r=dirname(__DIR__);
$m=file_get_contents($r.'/database/migrations/20260930_create_partner_client_invites.sql');
$model=file_get_contents($r.'/app/Models/ParceiroConvite.php');
$service=file_get_contents($r.'/app/Services/ParceiroClienteCadastroService.php');
$auth=file_get_contents($r.'/app/Core/Auth.php');
$config=file_get_contents($r.'/app/Controllers/ConfiguracaoController.php');
$menu=file_get_contents($r.'/app/Views/layouts/master.php');
$public=file_get_contents($r.'/app/Views/parceiros/cadastro_cliente_convite.php');
pciAssert(strpos($m,'CREATE TABLE parceiro_convites')!==false,'migration deve criar convites');
pciAssert(strpos($m,'PCI_TokenHash CHAR(64)')!==false,'convite deve persistir somente hash do token');
pciAssert(strpos($model,"hash('sha256'")!==false,'token recebido deve ser validado por hash');
pciAssert(strpos($model,"PCI_Status='aceito'")!==false,'vínculo deve exigir convite aceito');
pciAssert(strpos($service,"'cliente_partner_vinculado'")!==false,'cadastro deve criar perfil vinculado');
pciAssert(strpos($service,"'cliente_admin'")!==false,'cliente final deve receber login próprio');
pciAssert(strpos($auth,'clienteEhPartnerVinculado')!==false,'Auth deve reconhecer cliente vinculado');
pciAssert(strpos($auth,"['dashboard','template','configuracao','conta','login','onboardingSuporte']")!==false,'perfil vinculado deve ter allowlist restrita');
pciAssert(strpos($config,'vincularContaDoCliente')!==false,'Embedded Signup deve criar vínculo Partner');
pciAssert(strpos($model,"PAC_FaturavelDesde")!==false,'ativação deve registrar início faturável');
pciAssert(strpos($menu,'Clientes Partner')!==false,'Partner deve ter acesso à gestão de clientes');
pciAssert(strpos($public,'gerenciar os templates')!==false,'cadastro deve explicar acesso a templates');
echo "Partner client invite static tests passed\n";
