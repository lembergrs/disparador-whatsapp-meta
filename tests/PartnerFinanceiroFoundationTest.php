<?php
function pfAssert($c,$m){if(!$c){fwrite(STDERR,"FAIL: {$m}\n");exit(1);}}
$r=dirname(__DIR__);
$m=file_get_contents($r.'/database/migrations/20260930_create_partner_financeiro.sql');
$c=file_get_contents($r.'/app/Models/Cobranca.php');
$s=file_get_contents($r.'/app/Services/ParceiroFinanceiroService.php');
$d=file_get_contents($r.'/docs/partner-api-v1.md');
pfAssert(strpos($m,'parceiro_assinaturas')!==false,'assinatura partner ausente');
pfAssert(strpos($m,'parceiro_faturamento_competencias')!==false,'snapshot mensal ausente');
pfAssert(strpos($m,"PAC_Status ENUM('convidado','cadastrado','onboarding','ativo','suspenso','cancelado')")!==false,'ciclo cliente partner ausente');
pfAssert(strpos($m,'PAR_ID INT UNSIGNED NULL')!==false && strpos($m,'PAS_ID BIGINT UNSIGNED NULL')!==false,'cobranca deve referenciar partner');
pfAssert(strpos($c,"'COB_Origem' => 'origem'")!==false,'model cobranca deve aceitar origem partner');
pfAssert(strpos($s,"'tipo'=>'implantacao_partner'")!==false,'implantacao deve ser cobranca normal');
pfAssert(strpos($s,"'tipo'=>'mensalidade_partner'")!==false,'mensalidade partner deve ser cobranca normal');
pfAssert(strpos($d,'NFS-e por COB_ID')!==false,'documentacao deve preservar vinculo fiscal por cobranca');
pfAssert(strpos($d,'CLI_NFSe_*')!==false,'documentacao deve exigir cadastro fiscal do partner');
echo "Partner financial foundation static tests passed\n";
