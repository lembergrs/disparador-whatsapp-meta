<?php

$root = dirname(__DIR__);
$migration = file_get_contents($root . '/database/migrations/20260715_create_nfse_foundation.sql');
$model = file_get_contents($root . '/app/Models/NfseEmissao.php');
$sequencia = file_get_contents($root . '/app/Services/NfseDpsSequenciaService.php');
$clienteModel = file_get_contents($root . '/app/Models/Cliente.php');
$config = file_get_contents($root . '/config/config.php');
$docs = file_get_contents($root . '/docs/NFSE_LEVANTAMENTO_TECNICO.md');

function nfseAssert($condition, $message)
{
    if(!$condition){
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function nfseContains($haystack, $needle, $message)
{
    nfseAssert(strpos($haystack, $needle) !== false, $message . "\nMissing: {$needle}");
}

function nfseNotContains($haystack, $needle, $message)
{
    nfseAssert(strpos($haystack, $needle) === false, $message . "\nUnexpected: {$needle}");
}

nfseContains($migration, 'CREATE TABLE nfse_emissoes', 'migration creates nfse_emissoes');
nfseContains($migration, 'CREATE TABLE nfse_dps_sequencias', 'migration creates nfse_dps_sequencias');
nfseContains($migration, 'UNIQUE KEY uk_nfse_cobranca (COB_ID)', 'migration guarantees one nfse per cobrança');
nfseContains($migration, 'UNIQUE KEY uk_nfse_idempotency (NFE_IdempotencyKey)', 'migration has unique idempotency key');
nfseContains($migration, 'UNIQUE KEY uk_nfse_numdps_contexto (NFE_PrestadorCnpj, NFE_Ambiente, NFE_Serie, NFE_NumDps)', 'migration has contextual unique numDPS');
nfseContains($migration, 'UNIQUE KEY uk_nfse_dps_contexto (NDS_PrestadorCnpj, NDS_Ambiente, NDS_Serie)', 'sequence separated by prestador/ambiente/série');
nfseContains($migration, 'CLI_NFSe_CodigoIBGE', 'migration adds fiscal IBGE code');
nfseNotContains($migration, 'NFE_API_AUTH_TOKEN', 'migration does not store API token column');
nfseNotContains($migration, 'NFE_senhaCert', 'migration does not store senhaCert column');
nfseNotContains($migration, 'NFE_Authorization', 'migration does not store Authorization column');

nfseContains($model, "return 'nfse:cobranca:' . (int) \$cobrancaId;", 'idempotency key is stable by cobrança');
require_once $root . '/app/Models/NfseEmissao.php';
$key1 = \Models\NfseEmissao::chaveIdempotencia(10);
$key2 = \Models\NfseEmissao::chaveIdempotencia(11);
nfseAssert($key1 === 'nfse:cobranca:10', 'idempotency key has expected format');
nfseAssert($key1 !== $key2, 'different COB_ID generates different idempotency key');
nfseAssert(\Models\NfseEmissao::transicaoPermitida(\Models\NfseEmissao::STATUS_EMITIDA, \Models\NfseEmissao::STATUS_PENDENTE) === false, 'emitida does not return to pendente');
nfseAssert(\Models\NfseEmissao::transicaoPermitida(\Models\NfseEmissao::STATUS_CANCELADA, \Models\NfseEmissao::STATUS_PENDENTE) === false, 'cancelada is not reissued automatically');
nfseAssert(\Models\NfseEmissao::transicaoPermitida(\Models\NfseEmissao::STATUS_PROCESSANDO, \Models\NfseEmissao::STATUS_RECONCILIACAO_PENDENTE) === true, 'processing can become reconciliation pending');
$emissao = new \Models\NfseEmissao(new class { public function prepare($sql){} });
$sanitized = $emissao->sanitizarMensagem('Authorization: Bearer segredo senhaCert=abc PFX=/tmp/certificado.pfx');
nfseAssert(strpos($sanitized, 'segredo') === false && strpos($sanitized, 'abc') === false && strpos($sanitized, '/tmp/certificado.pfx') === false, 'sanitization removes secrets and sensitive paths');
nfseContains($model, 'STATUS_RECONCILIACAO_PENDENTE', 'model has reconciliation status');
nfseContains($model, 'criarOuBuscarPorCobranca', 'model exposes future idempotent reservation method');

nfseNotContains(strtolower($sequencia), 'max(', 'sequence service does not use max + 1');
nfseContains($sequencia, 'FOR UPDATE', 'sequence service locks row for atomic reservation');
nfseContains($sequencia, 'Ambiente fiscal inválido', 'sequence service validates environment');
nfseContains($sequencia, 'PHP_INT_MAX', 'sequence service considers overflow');
nfseContains($sequencia, 'NDS_ProximoNumero = NDS_ProximoNumero + 1', 'sequence service increments persistent counter atomically');
nfseContains($clienteModel, 'array_key_exists($chave, $dados)', 'cliente fiscal partial update only touches posted fields');
nfseContains($clienteModel, '$sets[] = $coluna', 'cliente fiscal SQL columns come from internal map');

foreach([
    'NFSE_API_BASE_URL',
    'NFSE_API_AUTH_TOKEN',
    'NFSE_PRESTADOR_CNPJ',
    'NFSE_PRESTADOR_IM',
    'NFSE_PRESTADOR_OP_SIMPLES',
    'NFSE_LOCAL_EMISSAO_IBGE',
    'NFSE_DPS_SERIE',
    'NFSE_CODIGO_TRIBUTACAO_NACIONAL',
    'NFSE_DESCRICAO_SERVICO',
    'NFSE_CERT_PATH',
    'NFSE_CERT_PASSWORD',
    'NFSE_CONNECT_TIMEOUT',
    'NFSE_REQUEST_TIMEOUT'
] as $constante){
    nfseContains($config, "env_valor('{$constante}'", "config loads {$constante} from environment");
}

nfseContains($docs, 'Implementação — Etapa 1', 'documentation includes Etapa 1 section');
nfseContains($docs, 'Não chama a API RL2 NFS-e', 'documentation records no API calls in this step');

echo "NFS-e foundation static checks passed\n";
