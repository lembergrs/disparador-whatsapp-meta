<?php

require_once __DIR__ . '/../app/Services/DocumentoFiscalValidator.php';
require_once __DIR__ . '/../app/Services/NfseAptidaoFiscalService.php';

use Services\NfseAptidaoFiscalService;

function nfseAptidaoAssert($condition, $message)
{
    if(!$condition){
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$service = new NfseAptidaoFiscalService();

$pjCompleto = [
    'CLI_TipoPessoa' => 'PJ',
    'CLI_NFSe_CNPJ' => '11222333000181',
    'CLI_NFSe_RazaoSocial' => 'Empresa Teste LTDA',
    'CLI_NFSe_CEP' => '90000000',
    'CLI_NFSe_Logradouro' => 'Rua Fiscal',
    'CLI_NFSe_Numero' => '100',
    'CLI_NFSe_Bairro' => 'Centro',
    'CLI_NFSe_CodigoIBGE' => '4314902'
];
$resultado = $service->validarCliente($pjCompleto);
nfseAptidaoAssert($resultado['apto'] === true, 'PJ completo deve ser apto');
nfseAptidaoAssert($resultado['campos_faltantes'] === [], 'PJ completo não deve ter faltantes');


$pjMascarado = $pjCompleto;
$pjMascarado['CLI_NFSe_CNPJ'] = '11.222.333/0001-81';
$pjMascarado['CLI_NFSe_CEP'] = '90000-000';
$resultado = $service->validarCliente($pjMascarado);
nfseAptidaoAssert($resultado['apto'] === true, 'CNPJ e CEP com máscara válidos devem ser aceitos');

$pjCnpjInvalido = $pjCompleto;
$pjCnpjInvalido['CLI_NFSe_CNPJ'] = '11.111.111/1111-11';
$resultado = $service->validarCliente($pjCnpjInvalido);
nfseAptidaoAssert($resultado['apto'] === false, 'CNPJ inválido não deve ser apto');
nfseAptidaoAssert(in_array('cnpj_valido', $resultado['campos_faltantes'], true), 'CNPJ inválido retorna campo inválido');

$pjIbgeInvalido = $pjCompleto;
$pjIbgeInvalido['CLI_NFSe_CodigoIBGE'] = '123';
$resultado = $service->validarCliente($pjIbgeInvalido);
nfseAptidaoAssert($resultado['apto'] === false, 'IBGE inválido não deve ser apto');
nfseAptidaoAssert(in_array('codigo_ibge_valido', $resultado['campos_faltantes'], true), 'IBGE inválido retorna campo inválido');

$pjIncompleto = $pjCompleto;
$pjIncompleto['CLI_NFSe_CEP'] = '';
$pjIncompleto['CLI_NFSe_CodigoIBGE'] = '';
$resultado = $service->validarCliente($pjIncompleto);
nfseAptidaoAssert($resultado['apto'] === false, 'PJ incompleto não deve ser apto');
nfseAptidaoAssert(in_array('cep', $resultado['campos_faltantes'], true), 'PJ incompleto retorna CEP faltante');
nfseAptidaoAssert(in_array('codigo_ibge', $resultado['campos_faltantes'], true), 'PJ incompleto retorna IBGE faltante');

$pf = $pjCompleto;
$pf['CLI_TipoPessoa'] = 'PF';
$pf['CLI_NFSe_CNPJ'] = '12345678909';
$resultado = $service->validarCliente($pf);
nfseAptidaoAssert($resultado['apto'] === false, 'PF não deve ser apto nesta etapa');
nfseAptidaoAssert(in_array('cnpj', $resultado['campos_faltantes'], true), 'PF retorna bloqueio de CNPJ/PJ');

$resultado = $service->validarCliente(null);
nfseAptidaoAssert($resultado['tipo_bloqueio'] === 'cliente_inexistente', 'cliente inexistente retorna bloqueio adequado');

$mensagem = json_encode($service->validarCliente($pjIncompleto));
nfseAptidaoAssert(stripos($mensagem, 'senhaCert') === false, 'resultado não contém senhaCert');
nfseAptidaoAssert(stripos($mensagem, 'Bearer') === false, 'resultado não contém Bearer');
nfseAptidaoAssert(stripos($mensagem, 'PFX') === false, 'resultado não contém PFX');

echo "NFS-e aptidão fiscal tests passed\n";
