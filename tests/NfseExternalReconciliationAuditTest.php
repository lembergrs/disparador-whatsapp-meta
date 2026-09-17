<?php

function nfseExternalAssert($condition, $message)
{
    if(!$condition){ fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
    echo "OK: {$message}\n";
}

$root = dirname(__DIR__);
$service = file_get_contents($root . '/app/Services/NfseExternalReconciliationService.php');
$model = file_get_contents($root . '/app/Models/NfseReconciliacaoExterna.php');
$controller = file_get_contents($root . '/app/Controllers/NfseController.php');
$migration = file_get_contents($root . '/database/migrations/20260917_nfse_external_reconciliation.sql');

nfseExternalAssert(strpos($service, 'strlen($chave) !== 50') !== false, 'exige chave de acesso com 50 dígitos');
nfseExternalAssert(strpos($service, 'consultarXml') !== false && strpos($service, 'mapearXml') !== false, 'consulta XML oficial antes da reconciliação');
nfseExternalAssert(strpos($service, "documentosDoGrupo(\$xp, 'prest')") !== false, 'valida documento dentro do grupo do prestador');
nfseExternalAssert(strpos($service, "documentosDoGrupo(\$xp, 'toma')") !== false, 'valida documento dentro do grupo do tomador');
nfseExternalAssert(strpos($service, "(int) (\$cobranca['CLI_ID'] ?? 0) !== (int) (\$tentativa['CLI_ID'] ?? 0)") !== false, 'confere vínculo entre cobrança e cliente');
nfseExternalAssert(strpos($service, "abs(\$valor - (float) (\$cobranca['COB_Valor'] ?? 0)) > 0.01") !== false, 'confere valor oficial com a cobrança');
nfseExternalAssert(strpos($model, 'STATUS_ERRO_TEMPORARIO, NfseEmissao::STATUS_ERRO_DEFINITIVO') !== false, 'modelo aceita reconciliação apenas para tentativas com erro');
nfseExternalAssert(strpos($model, "NFE_UltimoErroCodigo = 'substituida_por_emissao_externa'") !== false, 'preserva tentativa anterior como histórico encerrado');
nfseExternalAssert(strpos($model, 'NFE_EmissaoAtiva = NULL') !== false, 'inativa tentativa substituída na mesma transação');
nfseExternalAssert(strpos($model, 'NFE_XmlStoragePath') !== false && strpos($model, 'NFE_XmlSha256') !== false, 'grava referência do XML na mesma transação da reconciliação');
nfseExternalAssert(strpos($model, 'STATUS_EMITIDA') !== false, 'nova nota externa é registrada como emitida');
nfseExternalAssert(strpos($controller, 'buscarVigentesPorCobrancas') !== false, 'cobranças com registro fiscal vigente saem do seletor de nova emissão');
nfseExternalAssert(strpos($controller, "(\$_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST'") !== false, 'rota administrativa permite abrir a tela por GET e reconciliar por POST');
nfseExternalAssert(strpos($controller, 'STATUS_ERRO_TEMPORARIO, NfseEmissao::STATUS_ERRO_DEFINITIVO') !== false, 'tela de reconciliação só abre para tentativa ativa com erro');
nfseExternalAssert(strpos($migration, 'UNIQUE KEY uk_nfse_chave_acesso_unica') !== false, 'banco impede duplicidade de chave de acesso');

$onDemand = file_get_contents($root . '/app/Services/NfsePdfOnDemandService.php');
$emission = file_get_contents($root . '/app/Services/NfseEmissionService.php');
$emissionModel = file_get_contents($root . '/app/Models/NfseEmissao.php');
nfseExternalAssert(strpos($service, 'gerarPdfXml') === false && strpos($service, 'consultarPdf') === false, 'reconciliação não gera PDF');
nfseExternalAssert(strpos($model, 'NFE_PdfStoragePath') === false, 'reconciliação persiste somente XML');
nfseExternalAssert(strpos($onDemand, "arquivoDownload((int) \$nfseId, 'xml', \$usuario)") !== false, 'PDF reutiliza autorização e leitura do XML armazenado');
nfseExternalAssert(strpos($onDemand, 'gerarPdfXml') !== false && strpos($onDemand, 'mapearPdf') !== false, 'PDF sob demanda usa gerador e valida resposta');
nfseExternalAssert(strpos($onDemand, 'file_put_contents') === false && strpos($onDemand, 'salvarArquivo') === false, 'serviço sob demanda não grava arquivo');
nfseExternalAssert(strpos($emission, 'consultarPdfManual') === false && strpos($emissionModel, 'function persistirArquivoPdf') === false, 'fluxo antigo de persistência de PDF removido');
nfseExternalAssert(strpos($controller, '->reconsultarManual(') !== false, 'controller usa reconsulta central de XML e eventos');
nfseExternalAssert(strpos($controller, "echo \$arquivo['conteudo'];") !== false, 'rota PDF retorna bytes diretamente ao navegador');
echo "NfseExternalReconciliationAuditTest concluído.\n";
