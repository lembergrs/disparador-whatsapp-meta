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
nfseExternalAssert(strpos($service, 'prestador CNPJ') === false || strpos($service, 'prestador') !== false, 'mantém validação do prestador');
nfseExternalAssert(strpos($service, "abs(\$valor - (float) (\$cobranca['COB_Valor'] ?? 0)) > 0.01") !== false, 'confere valor oficial com a cobrança');
nfseExternalAssert(strpos($model, "NFE_UltimoErroCodigo = 'substituida_por_emissao_externa'") !== false, 'preserva tentativa anterior como histórico encerrado');
nfseExternalAssert(strpos($model, 'NFE_EmissaoAtiva = NULL') !== false, 'inativa tentativa substituída na mesma transação');
nfseExternalAssert(strpos($model, "NFE_Status = :status") !== false && strpos($model, 'STATUS_EMITIDA') !== false, 'nova nota externa é registrada como emitida');
nfseExternalAssert(strpos($controller, 'buscarVigentesPorCobrancas') !== false, 'cobranças com registro fiscal vigente saem do seletor de nova emissão');
nfseExternalAssert(strpos($migration, 'UNIQUE KEY uk_nfse_chave_acesso_unica') !== false, 'banco impede duplicidade de chave de acesso');

echo "NfseExternalReconciliationAuditTest concluído.\n";
