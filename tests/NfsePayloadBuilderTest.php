<?php

if(!defined('NFSE_PRESTADOR_CNPJ')) define('NFSE_PRESTADOR_CNPJ', '22.333.444/0001-55');
if(!defined('NFSE_PRESTADOR_IM')) define('NFSE_PRESTADOR_IM', '12345');
if(!defined('NFSE_PRESTADOR_OP_SIMPLES')) define('NFSE_PRESTADOR_OP_SIMPLES', '1');
if(!defined('NFSE_LOCAL_EMISSAO_IBGE')) define('NFSE_LOCAL_EMISSAO_IBGE', '4314902');
if(!defined('NFSE_DPS_SERIE')) define('NFSE_DPS_SERIE', '900');
if(!defined('NFSE_AMBIENTE')) define('NFSE_AMBIENTE', 'sandbox');

require_once __DIR__ . '/../app/Services/NfseConfigService.php';
require_once __DIR__ . '/../app/Services/NfsePayloadBuilder.php';

use Services\NfsePayloadBuilder;

function nfsePayloadAssert($cond, $msg){ if(!$cond){ fwrite(STDERR, "FAIL: {$msg}\n"); exit(1); } }

$builder = new NfsePayloadBuilder();
$cliente = [
    'CLI_TipoPessoa' => 'PJ',
    'CLI_NFSe_CNPJ' => '11.222.333/0001-81',
    'CLI_NFSe_RazaoSocial' => 'Empresa Teste LTDA',
    'CLI_NFSe_CEP' => '90000-000',
    'CLI_NFSe_Logradouro' => 'Rua Fiscal',
    'CLI_NFSe_Numero' => 'S/N',
    'CLI_NFSe_Bairro' => 'Centro',
    'CLI_NFSe_CodigoIBGE' => '4314902',
    'CLI_NFSe_Email' => 'fiscal@example.invalid',
    'CLI_NFSe_Telefone' => '(51) 99999-0000'
];
$cobranca = ['COB_ID' => 10, 'COB_Valor' => '99.90', 'PLA_Nome' => 'Plano Teste'];
$emissao = ['NFE_NumDps' => '1', 'NFE_ValorFiscal' => '99.90', 'NFE_Competencia' => '2026-07-15'];
$payload = $builder->montarEmissao($cliente, $cobranca, $emissao, ['cert' => 'CERTIFICADO_FICTICIO_BASE64', 'senhaCert' => 'SENHA_FICTICIA']);

nfsePayloadAssert($payload['dadosNota']['prestador']['CNPJ'] === '22333444000155', 'CNPJ prestador normalizado');
nfsePayloadAssert($payload['dadosNota']['tomador']['CNPJ'] === '11222333000181', 'CNPJ tomador normalizado');
nfsePayloadAssert($payload['dadosNota']['tomador']['CEP'] === '90000000', 'CEP normalizado');
nfsePayloadAssert($payload['dadosNota']['tomador']['codMunicipio'] === '4314902', 'IBGE mantido com 7 dígitos');
nfsePayloadAssert($payload['dadosNota']['valorNota'] === 99.90, 'valor fiscal positivo');
nfsePayloadAssert($payload['dadosNota']['numDPS'] === '1', 'numDPS informado');
nfsePayloadAssert(isset($payload['cert'], $payload['senhaCert']), 'segredos só no payload em memória');

foreach([
    ['cliente PF', array_merge($cliente, ['CLI_TipoPessoa' => 'PF']), $cobranca, $emissao],
    ['valor inválido', $cliente, ['COB_Valor' => 0], ['NFE_NumDps' => '1', 'NFE_ValorFiscal' => 0, 'NFE_Competencia' => '2026-07-15']],
    ['numDPS inválido', $cliente, $cobranca, ['NFE_NumDps' => '0', 'NFE_ValorFiscal' => '99.90', 'NFE_Competencia' => '2026-07-15']]
] as $caso){
    try{
        $builder->montarEmissao($caso[1], $caso[2], $caso[3], ['cert' => 'CERTIFICADO_FICTICIO_BASE64', 'senhaCert' => 'SENHA_FICTICIA']);
        nfsePayloadAssert(false, $caso[0] . ' deveria falhar');
    }catch(Exception $e){
        nfsePayloadAssert(true, $caso[0] . ' falhou');
    }
}

echo "NFS-e payload builder tests passed\n";
