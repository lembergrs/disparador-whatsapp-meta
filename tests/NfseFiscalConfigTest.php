<?php
$root = dirname(__DIR__);
$config = file_get_contents($root . '/config/config.php');
$env = file_get_contents($root . '/.env.example');
$configService = file_get_contents($root . '/app/Services/NfseConfigService.php');
$builder = file_get_contents($root . '/app/Services/NfsePayloadBuilder.php');
$emission = file_get_contents($root . '/app/Services/NfseEmissionService.php');
$view = file_get_contents($root . '/app/Views/nfse/index.php');
$model = file_get_contents($root . '/app/Models/NfseEmissao.php');
$migrationSnapshot = file_get_contents($root . '/database/migrations/20260716_add_nfse_fiscal_snapshot.sql');

function nfseFiscalAssert($cond, $msg){ if(!$cond){ fwrite(STDERR, "FAIL: {$msg}\n"); exit(1); } }
function nfseFiscalHas($haystack, $needle, $msg){ nfseFiscalAssert(strpos($haystack, $needle) !== false, $msg . "\nMissing: {$needle}"); }
function nfseFiscalNot($haystack, $needle, $msg){ nfseFiscalAssert(strpos($haystack, $needle) === false, $msg . "\nUnexpected: {$needle}"); }

nfseFiscalHas($env, 'NFSE_CODIGO_TRIBUTACAO_NACIONAL=', 'env example possui código parametrizado');
nfseFiscalHas($env, 'NFSE_DESCRICAO_SERVICO=Licenciamento de uso da plataforma', 'env example possui descrição placeholder');
nfseFiscalNot($env, 'Disparador.net - Disparador.net', 'env example não duplica marca');
nfseFiscalHas($config, "env_valor('NFSE_CODIGO_TRIBUTACAO_NACIONAL', '')", 'config carrega código sem fallback fiscal');
nfseFiscalHas($config, "env_valor('NFSE_DESCRICAO_SERVICO', '')", 'config carrega descrição sem fallback fiscal');
nfseFiscalHas($configService, 'function codigoTributacaoNacional()', 'ConfigService expõe código tributário');
nfseFiscalHas($configService, 'function descricaoServico()', 'ConfigService expõe descrição');
nfseFiscalHas($configService, 'codigo_tributacao_configurado', 'dados públicos expõem apenas flag de código');
nfseFiscalHas($configService, 'descricao_servico_configurada', 'dados públicos expõem apenas flag de descrição');
nfseFiscalAssert(substr_count($configService, 'codigo_tributacao_nacional') === 0, 'dadosPublicos não retorna código completo');
nfseFiscalHas($builder, 'validarParametrosFiscaisConfigurados', 'PayloadBuilder valida parametrização antes da emissão');
nfseFiscalHas($builder, "NFE_CodigoTributacaoNacional", 'PayloadBuilder reutiliza snapshot de código quando existir');
nfseFiscalHas($builder, "NFE_DescricaoServicoSnapshot", 'PayloadBuilder reutiliza snapshot de descrição quando existir');
nfseFiscalHas($builder, 'TODO(NFS-e): enviar codigoTributacaoNacional', 'PayloadBuilder documenta TODO de contrato futuro');
nfseFiscalHas($builder, "'descServico' => $" . "parametrosFiscais['descricao_servico']", 'PayloadBuilder usa descrição configurada');
nfseFiscalHas($emission, "prepararSnapshotFiscal", 'EmissionService preserva snapshot antes da primeira chamada');
nfseFiscalHas($emission, "registrarLogSeguro('bloqueio_configuracao'", 'bloqueio por configuração é logado de forma segura');
nfseFiscalHas($model, 'function prepararSnapshotFiscal', 'Model persiste snapshot fiscal dedicado');
nfseFiscalHas($migrationSnapshot, 'NFE_CodigoTributacaoNacional', 'migration adiciona snapshot de código');
nfseFiscalHas($migrationSnapshot, 'NFE_DescricaoServicoSnapshot', 'migration adiciona snapshot de descrição');
nfseFiscalHas($view, 'Configuração fiscal incompleta', 'tela avisa configuração incompleta');
nfseFiscalHas($view, 'data-config-fiscal-completa', 'tela bloqueia botão por configuração incompleta');

foreach(['17.06.01', '170601', 'Licenciamento de uso da plataforma Disparador.net - Disparador.net'] as $needle){
    nfseFiscalNot($config, $needle, "config não contém {$needle}");
    nfseFiscalNot($configService, $needle, "ConfigService não contém {$needle}");
    nfseFiscalNot($builder, $needle, "PayloadBuilder não contém {$needle}");
    nfseFiscalNot($emission, $needle, "EmissionService não contém {$needle}");
}

echo "NFS-e fiscal config tests passed\n";
