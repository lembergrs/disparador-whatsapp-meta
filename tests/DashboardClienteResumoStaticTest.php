<?php
$root=dirname(__DIR__);
$view=file_get_contents($root.'/app/Views/dashboard/index.php');
$cliente=file_get_contents($root.'/app/Views/dashboard/_cliente_resumo.php');
$login=file_get_contents($root.'/app/Controllers/LoginController.php');
$auth=file_get_contents($root.'/app/Core/Auth.php');
$config=file_get_contents($root.'/app/Controllers/ConfiguracaoController.php');
$assert=function($ok,$msg){if(!$ok){fwrite(STDERR,"FAIL: {$msg}\n");exit(1);}};
$assert(strpos($view,'$mostrarOnboardingDashboard')!==false,'Onboarding concluído deve deixar de ocupar o dashboard.');
$assert(strpos($cliente,'Mensagens extras')!==false && strpos($cliente,'EXC_Mensagens')!==false,'Excedente deve aparecer junto do plano.');
$assert(strpos($cliente,'Ações rápidas')!==false,'Dashboard deve ter ações rápidas.');
$assert(strpos($cliente,'Atualizar dados da Meta')!==false,'Cliente deve poder atualizar a conta Meta.');
$assert(strpos($config,'buscarPorUsuario($contaId, $usuario)')!==false,'Atualização Meta deve respeitar escopo do cliente.');
$assert(strpos($login,"UPDATE usuarios SET USU_UltimoAcesso = NOW()")!==false,'Login real deve registrar último acesso.');
$assert(strpos($auth,'startImpersonation')!==false && strpos($auth,'USU_UltimoAcesso')===false,'Impersonação não deve atualizar último acesso.');
echo "Dashboard cliente checks passed\n";
