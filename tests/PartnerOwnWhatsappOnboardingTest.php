<?php
function poAssert($c,$m){if(!$c){fwrite(STDERR,"FAIL: {$m}\n");exit(1);}}
$r=dirname(__DIR__);
$a=file_get_contents($r.'/app/Core/Auth.php');
$c=file_get_contents($r.'/app/Controllers/ConfiguracaoController.php');
$m=file_get_contents($r.'/app/Models/MetaConta.php');
poAssert(strpos($a,'clienteEhPartnerAprovado')!==false,'Auth deve reconhecer Partner aprovado');
poAssert(strpos($a,"c.CLI_TipoConta = 'cliente_partner'")!==false,'regra deve ser exclusiva do cliente Partner');
poAssert(strpos($a,"p.PAR_StatusCadastro = 'aprovado'")!==false,'Partner deve estar aprovado');
poAssert(strpos($a,'self::clienteEmPreTrial() || self::clienteEhPartnerAprovado()')!==false,'Partner aprovado deve acessar configuracao Meta');
poAssert(strpos($a,'self::clienteEmPreTrial() || self::clienteEhPartnerAprovado($clienteId)')!==false,'Partner aprovado deve conectar o primeiro numero');
poAssert(strpos($c,'Auth::podeConectarPrimeiroNumero')!==false,'Embedded Signup deve usar a regra central de elegibilidade');
poAssert(strpos($c,'salvarOuAtualizarEmbeddedSignupComBloqueio')!==false,'onboarding deve manter gravacao Meta vinculada ao cliente');
poAssert(strpos($m,"'pre_trial_primeiro_numero' => true")!==false,'limite deve permitir um primeiro numero sem plano quando elegivel');
echo "Partner own WhatsApp onboarding static tests passed\n";
