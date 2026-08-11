<?php

$root = dirname(__DIR__);
function ifcAssert($condicao, $mensagem){ if(!$condicao){ fwrite(STDERR, "FAIL: {$mensagem}\n"); exit(1); } }

$site = file_get_contents($root . '/app/Controllers/SiteController.php');
$financeiro = file_get_contents($root . '/app/Services/FinanceiroWorkflowService.php');
$marco = file_get_contents($root . '/app/Services/Indicacao/IndicacaoPrimeiroPagamentoService.php');
$codigo = file_get_contents($root . '/app/Services/Indicacao/IndicacaoCodigoService.php');

ifcAssert(strpos($site, "SESSAO_CODIGO_INDICACAO") !== false, 'cadastro preserva referência em sessão');
ifcAssert(strpos($site, 'validarCodigo($_GET') !== false && strpos($site, "registrarIndicacao(") !== false, 'cadastro valida e delega o vínculo ao domínio');
ifcAssert(strpos($site, "'link'") !== false && strpos($site, 'ICD_CodigoNormalizado') !== false, 'cadastro persiste somente código normalizado e origem link');
ifcAssert(strpos($financeiro, 'processarIndicacaoNoPrimeiroPagamento') !== false && substr_count($financeiro, 'processarIndicacaoNoPrimeiroPagamento(') >= 3, 'pagamento manual e webhook usam o mesmo marco de indicação');
ifcAssert(strpos($financeiro, 'contarPagasPorCliente') !== false, 'marco ocorre apenas no primeiro pagamento confirmado');
ifcAssert(strpos($marco, 'IndicacaoElegibilidadeService') !== false && strpos($marco, 'confirmarPrimeiroPagamento') !== false, 'marco delega a elegibilidade ao Scheduler existente');
ifcAssert(strpos($marco, "ICD_Status'] === 'nao_liberado'") !== false && strpos($marco, "'ativo'") !== false, 'código é ativado somente pelo marco financeiro');
ifcAssert(strpos($marco, 'buscarPublicaElegivel') !== false, 'ativação respeita a vigência da campanha');
ifcAssert(strpos($codigo, 'inTransaction') !== false, 'criação e ativação de código respeitam transação externa');

echo "IndicacaoFluxoClienteStaticTest OK\n";
