<?php

$root=dirname(__DIR__);
$controller=file_get_contents($root.'/app/Controllers/FinanceiroController.php');
$view=file_get_contents($root.'/app/Views/financeiro/index.php');
$cobranca=file_get_contents($root.'/app/Models/Cobranca.php');
$workflow=file_get_contents($root.'/app/Services/FinanceiroWorkflowService.php');
function regularizacaoAssert($condicao,$mensagem){if(!$condicao){throw new RuntimeException($mensagem);}}

regularizacaoAssert(strpos($controller,'buscarObrigacaoAbertaPorAssinatura')!==false,'controller usa obrigação aberta centralizada');
regularizacaoAssert(substr_count($controller,'buscarParaRegularizacaoFinanceira')===2,'tela e histórico usam o contexto explícito de regularização');
regularizacaoAssert(strpos($cobranca,"COB_Status IN ('pendente','vencido')")!==false&&strpos($cobranca,'c.ASS_ID = ?')!==false,'obrigação aceita pendente/vencida e exige assinatura vigente');
regularizacaoAssert(strpos($cobranca,'COALESCE(c.COB_DataVencimentoEfetivo, c.COB_DataVencimento)')!==false,'seleção usa vencimento efetivo com fallback');
regularizacaoAssert(strpos($view,'id="obrigacaoFinanceiraAtual"')!==false&&strpos($view,'Pagar agora')!==false,'interface apresenta obrigação e pagamento');
regularizacaoAssert(strpos($view,'Vencida / em tolerância')!==false&&strpos($view,'Suspensa por inadimplência')!==false,'interface distingue tolerância e suspensão');
regularizacaoAssert(strpos($view,'Gerar link de pagamento')!==false&&strpos($view,'financeiro/recuperarCobranca')!==false,'interface oferece recuperação sem link');
regularizacaoAssert(strpos($controller,"(int) \$obrigacaoAtualId")!==false&&strpos($controller,"in_array(\$statusFatura, ['pendente','vencido'], true)")!==false,'histórico permite pagar pendente/vencida somente quando é a obrigação atual');
regularizacaoAssert(strpos($controller,"COB_DataVencimentoEfetivo")!==false,'histórico exibe vencimento efetivo');
$inicioRecuperacao=strpos($controller,'public function recuperarCobranca');
$trechoRecuperacao=substr($controller,$inicioRecuperacao,1500);
regularizacaoAssert(strpos($trechoRecuperacao,'validarCsrfPost')!==false&&strpos($trechoRecuperacao,'Auth::usuario()')!==false&&strpos($trechoRecuperacao,"\$_POST['cobranca_id']")!==false,'recuperação exige CSRF e combina identidade autenticada com o ID solicitado');
regularizacaoAssert(strpos($workflow,'comLockIntegracao')!==false&&strpos($workflow,'buscarCobrancaPorReferenciaExterna')!==false,'recuperação preserva lock e reconciliação externa');
regularizacaoAssert(strpos($workflow,'recuperarIntegracaoCobranca')!==false&&strpos($workflow,"Cobrança não pertence ao contexto financeiro atual")!==false,'domínio valida propriedade e contexto explícito de regularização');
$assinatura=file_get_contents($root.'/app/Models/Assinatura.php');
regularizacaoAssert(strpos($assinatura,'public function buscarParaRegularizacaoFinanceira')!==false&&strpos($assinatura,"WHEN 'ativa' THEN 1")!==false,'assinatura ativa tem prioridade explícita na regularização');
regularizacaoAssert(strpos($assinatura,"a.ASS_Status = 'pendente' AND EXISTS")!==false&&strpos($assinatura,"c.COB_Status IN ('pendente','vencido')")!==false,'pendente só participa como candidata com obrigação aberta vinculada');

echo "FinanceiroRegularizacaoStaticTest OK\n";
