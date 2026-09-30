<?php
function psAssert($c,$m){if(!$c){fwrite(STDERR,"FAIL: {$m}\n");exit(1);}}
$r=dirname(__DIR__);
$s=file_get_contents($r.'/app/Services/ParceiroCadastroService.php');
$c=file_get_contents($r.'/app/Controllers/ParceiroCadastroController.php');
$m=file_get_contents($r.'/app/Models/ParceiroApi.php');
$v=file_get_contents($r.'/app/Views/parceiros/cadastro_publico.php');
$a=file_get_contents($r.'/app/Views/parceiros/detalhe.php');
psAssert(strpos($s,"'cliente_partner'")!==false,'cadastro deve classificar parceiro');
psAssert(strpos($s,"'pendente','aguardando_validacao','bloqueada'")!==false,'partner deve nascer pendente e API bloqueada');
psAssert(strpos($s,'CLI_NFSe_CodigoIBGE')!==false && strpos($s,'CLI_NFSe_CNPJ')!==false,'cadastro deve persistir dados fiscais NFS-e');
psAssert(strpos($s,"'cliente_admin'")!==false,'cadastro deve criar administrador do parceiro');
psAssert(strpos($c,'Csrf::exigirPost()')!==false,'cadastro publico deve exigir CSRF');
psAssert(strpos($c,'aceite_termos')!==false,'cadastro deve exigir aceite');
psAssert(strpos($v,'Código IBGE')!==false && strpos($v,'Endereço fiscal')!==false,'formulario deve coletar aptidao fiscal');
psAssert(strpos($m,"PAR_StatusCadastro='aprovado'")!==false,'auth/key deve exigir aprovacao');
psAssert(strpos($m,'aprovarAdmin')!==false,'admin deve conseguir aprovar');
psAssert(strpos($a,'Aprovar cadastro Partner')!==false,'tela deve expor aprovacao');
echo "Partner self-service registration static tests passed\n";
