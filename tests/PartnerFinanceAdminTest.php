<?php
function pfaAssert($c,$m){if(!$c){fwrite(STDERR,"FAIL: {$m}\n");exit(1);}}
$r=dirname(__DIR__);
$m=file_get_contents($r.'/app/Models/ParceiroFinanceiro.php');
$s=file_get_contents($r.'/app/Services/ParceiroFinanceiroService.php');
$w=file_get_contents($r.'/app/Services/FinanceiroWorkflowService.php');
$c=file_get_contents($r.'/app/Controllers/ParceiroAdminController.php');
$v=file_get_contents($r.'/app/Views/parceiros/detalhe.php');
pfaAssert(strpos($m,'salvarPlanoAdmin')!==false && strpos($m,'sobrepõe outra faixa')!==false,'admin deve configurar faixas sem sobreposicao');
pfaAssert(strpos($m,'criarOuAtualizarAssinaturaAdmin')!==false,'admin deve configurar assinatura');
pfaAssert(strpos($m,'listarCobrancasAdmin')!==false,'admin deve acompanhar cobrancas');
pfaAssert(strpos($s,'buscarCobrancaImplantacaoAberta')!==false,'implantacao deve reutilizar cobranca existente');
pfaAssert(strpos($w,"($cobranca['COB_Origem'] ?? '') === 'partner_api'")!==false,'workflow deve distinguir Partner');
pfaAssert(strpos($w,"? (string) ($plano['PLA_Nome'] ?? 'Partner API')")!==false,'descricao Partner no Asaas nao deve receber prefixo Mensalidade');
pfaAssert(strpos($m,"PAR_StatusApi='suspensa'")===false,'primeiro vencimento nao deve suspender API imediatamente');
pfaAssert(substr_count($w,"($cobranca['COB_Origem'] ?? '') !== 'partner_api'")>=5,'estado financeiro comum deve ser isolado do Partner');
pfaAssert(strpos($c,'cobrarImplantacao')!==false && strpos($c,'cobrarMensalidade')!==false,'admin deve gerar cobrancas Partner');
pfaAssert(strpos($v,'Financeiro Partner')!==false && strpos($v,'Faixas mensais')!==false,'tela deve expor financeiro Partner');
echo "Partner finance admin static tests passed\n";
