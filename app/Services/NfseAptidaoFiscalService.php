<?php

namespace Services;

class NfseAptidaoFiscalService
{
    public function validarCliente($cliente)
    {
        if(!$cliente || !is_array($cliente)){
            return $this->resultado(false, 'cliente_inexistente', ['cliente'], 'Cliente não encontrado.');
        }

        $faltantes = [];
        $tipoPessoa = strtoupper(trim((string) ($cliente['CLI_TipoPessoa'] ?? '')));
        $cnpj = $this->valorFiscal($cliente, 'CLI_NFSe_CNPJ', 'CLI_CPF_CNPJ');

        if(!in_array($tipoPessoa, ['PJ', 'J'], true) || strlen($cnpj) !== 14){
            $faltantes[] = 'cnpj';
        }

        if(strlen($cnpj) === 14 && !DocumentoFiscalValidator::valido($cnpj)){
            $faltantes[] = 'cnpj_valido';
        }

        $razao = $this->valorFiscal($cliente, 'CLI_NFSe_RazaoSocial', 'CLI_RazaoSocial', false);
        if($razao === ''){
            $faltantes[] = 'razao_social';
        }

        $campos = [
            'cep' => $this->valorFiscal($cliente, 'CLI_NFSe_CEP', 'CLI_CEP'),
            'logradouro' => $this->valorFiscal($cliente, 'CLI_NFSe_Logradouro', 'CLI_Logradouro', false),
            'numero' => $this->valorFiscal($cliente, 'CLI_NFSe_Numero', 'CLI_Numero', false),
            'bairro' => $this->valorFiscal($cliente, 'CLI_NFSe_Bairro', 'CLI_Bairro', false),
            'codigo_ibge' => $this->valorFiscal($cliente, 'CLI_NFSe_CodigoIBGE', null)
        ];

        foreach($campos as $campo => $valor){
            if($valor === ''){
                $faltantes[] = $campo;
            }
        }

        if($campos['cep'] !== '' && strlen($campos['cep']) !== 8){
            $faltantes[] = 'cep_valido';
        }

        if($campos['codigo_ibge'] !== '' && !preg_match('/^\d{7}$/', $campos['codigo_ibge'])){
            $faltantes[] = 'codigo_ibge_valido';
        }

        $faltantes = array_values(array_unique($faltantes));

        if(!empty($faltantes)){
            return $this->resultado(false, 'dados_fiscais_incompletos', $faltantes, 'Cliente não apto para emissão fiscal automática.');
        }

        return $this->resultado(true, null, [], 'Cliente apto para emissão fiscal automática.');
    }

    private function valorFiscal(array $cliente, $campoFiscal, $campoFallback = null, $somenteNumeros = true)
    {
        $valor = trim((string) ($cliente[$campoFiscal] ?? ''));

        if($valor === '' && $campoFallback !== null){
            $valor = trim((string) ($cliente[$campoFallback] ?? ''));
        }

        return $somenteNumeros ? preg_replace('/\D/', '', $valor) : $valor;
    }

    private function resultado($apto, $bloqueio, array $faltantes, $mensagem)
    {
        return [
            'apto' => (bool) $apto,
            'tipo_bloqueio' => $bloqueio,
            'campos_faltantes' => $faltantes,
            'mensagem' => $mensagem
        ];
    }
}
