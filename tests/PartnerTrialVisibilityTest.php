<?php
function phAssert($c,$m){if(!$c){fwrite(STDERR,"FAIL: {$m}\n");exit(1);}}
$r=dirname(__DIR__);
$a=file_get_contents($r.'/app/Core/Auth.php');
$m=file_get_contents($r.'/app/Views/layouts/master.php');
$d=file_get_contents($r.'/app/Controllers/DashboardController.php');
phAssert(strpos($a,'clienteEhPartnerAprovado() || self::clienteEhPartnerVinculado()')!==false,'Partner deve ser liberado fora do trial comum');
phAssert(strpos($a,"if(self::clienteEhPartnerAprovado()){\n            self::validarRotaPartner();")!==false,'Partner deve ter allowlist própria');
phAssert(strpos($a,"['dashboard','configuracao','parceiroClientes','parceiroTemplates','conta','login','onboardingSuporte']")!==false,'allowlist Partner deve excluir financeiro e módulos comuns e permitir templates delegados');
phAssert(substr_count($a,'clienteEhPartnerAprovado() || self::clienteEhPartnerVinculado()')>=3,'trial/pretrial devem excluir Partner e vinculado');
phAssert(strpos($m,'!$clientePartnerVinculado && !$clientePartnerAprovado')!==false,'menu deve ocultar financeiro e módulos comuns do Partner');
phAssert(strpos($d,"if(Auth::clienteEhPartnerAprovado()){\n            \$this->redirect('parceiroClientes');")!==false,'dashboard Partner deve ir para Clientes Partner');
echo "Partner trial visibility static tests passed\n";
