<?php
function ppAssert($c,$m){if(!$c){fwrite(STDERR,"FAIL: {$m}\n");exit(1);}}
$r=dirname(__DIR__);
$workflow=file_get_contents($r.'/app/Services/FinanceiroWorkflowService.php');
$partner=file_get_contents($r.'/app/Services/ParceiroFinanceiroService.php');
$model=file_get_contents($r.'/app/Models/ParceiroFinanceiro.php');
ppAssert(strpos($workflow,"COB_Origem'] ?? '') === 'partner_api'")!==false,'workflow deve distinguir cobranca Partner');
ppAssert(strpos($workflow,'marcarPagamentoPartner')!==false,'pagamento Partner deve usar estado Partner');
ppAssert(strpos($workflow,'integrarCobrancaPartner')!==false,'workflow deve expor integracao Asaas para Partner');
ppAssert(strpos($partner,"'tipo'=>'implantacao_partner'")!==false && strpos($partner,"'tipo'=>'mensalidade_partner'")!==false,'tipos Partner devem ser preservados');
ppAssert(substr_count($partner,'integrarCobrancaPartner')>=2,'implantacao e mensalidade devem sincronizar com Asaas');
ppAssert(strpos($partner,"PAR_StatusImplantacao='aguardando_pagamento'")!==false,'implantacao deve aguardar pagamento');
ppAssert(strpos($model,"PAR_StatusImplantacao='paga'")!==false,'pagamento da implantacao deve atualizar estado');
ppAssert(strpos($model,"PAR_StatusApi=IF(PAR_StatusApi='bloqueada','homologacao'")!==false,'pagamento deve liberar homologacao');
ppAssert(strpos($model,"PFC_Status='paga'")!==false,'mensalidade paga deve fechar competencia');
ppAssert(strpos($workflow,"!== 'partner_api'){ \$this->processarIndicacaoNoPrimeiroPagamento")!==false,'Partner nao deve consumir beneficio de indicacao');
echo "Partner payment workflow static tests passed\n";
