<?php

namespace Services;

class NfsePayloadBuilder
{
    public function montarEmissao(array $cliente, array $cobranca, array $emissao, array $segredos)
    {
        $this->validarSegredos($segredos);

        $numDps = trim((string) ($emissao['NFE_NumDps'] ?? ''));
        $valor = (float) ($emissao['NFE_ValorFiscal'] ?? ($cobranca['COB_Valor'] ?? 0));
        $competencia = substr((string) ($emissao['NFE_Competencia'] ?? ($cobranca['COB_DataPagamento'] ?? date('Y-m-d'))), 0, 10);
        $prestadorCnpj = NfseConfigService::prestadorCnpj();
        $localEmissao = NfseConfigService::localEmissaoIbge();
        $optSimples = NfseConfigService::optSimplesNacional();
        $tomadorCnpj = $this->somenteDigitos($cliente['CLI_NFSe_CNPJ'] ?? $cliente['CLI_CPF_CNPJ'] ?? '');
        $cep = $this->somenteDigitos($cliente['CLI_NFSe_CEP'] ?? '');
        $ibge = $this->somenteDigitos($cliente['CLI_NFSe_CodigoIBGE'] ?? '');

        if(($cliente['CLI_TipoPessoa'] ?? '') !== 'PJ'){
            throw new \InvalidArgumentException('A emissão de NFS-e nesta etapa exige cliente PJ.');
        }
        if(strlen($tomadorCnpj) !== 14){
            throw new \InvalidArgumentException('CNPJ fiscal do tomador inválido para emissão de NFS-e.');
        }
        if($numDps === '' || !ctype_digit($numDps) || (int) $numDps <= 0){
            throw new \InvalidArgumentException('numDPS inválido para emissão de NFS-e.');
        }
        if($valor <= 0){
            throw new \InvalidArgumentException('Valor fiscal inválido para emissão de NFS-e.');
        }
        if(!$this->dataValida($competencia)){
            throw new \InvalidArgumentException('Competência inválida para emissão de NFS-e.');
        }
        if(strlen($prestadorCnpj) !== 14 || strlen($localEmissao) !== 7 || $optSimples <= 0){
            throw new \RuntimeException('Configuração fiscal do prestador incompleta para emissão de NFS-e.');
        }
        if(strlen($ibge) !== 7){
            throw new \InvalidArgumentException('Código IBGE do tomador inválido para emissão de NFS-e.');
        }

        $dadosNota = [
            'numDPS' => $numDps,
            'dataNota' => $competencia,
            'localEmissao' => $localEmissao,
            'prestador' => [
                'CNPJ' => $prestadorCnpj,
                'optSimplesNacional' => $optSimples
            ],
            'tomador' => [
                'CNPJ' => $tomadorCnpj,
                'nome' => trim((string) ($cliente['CLI_NFSe_RazaoSocial'] ?? $cliente['CLI_RazaoSocial'] ?? $cliente['CLI_Nome'] ?? '')),
                'codMunicipio' => $ibge,
                'CEP' => $cep,
                'logradouro' => trim((string) ($cliente['CLI_NFSe_Logradouro'] ?? '')),
                'numero' => trim((string) ($cliente['CLI_NFSe_Numero'] ?? '')),
                'bairro' => trim((string) ($cliente['CLI_NFSe_Bairro'] ?? ''))
            ],
            'descServico' => $this->descricaoServico($cobranca),
            'valorNota' => $valor
        ];

        $im = NfseConfigService::prestadorIm();
        if($im !== ''){
            $dadosNota['prestador']['IM'] = $im;
        }

        foreach(['fone' => 'CLI_NFSe_Telefone', 'email' => 'CLI_NFSe_Email', 'complemento' => 'CLI_NFSe_Complemento'] as $campo => $origem){
            $valorOpcional = trim((string) ($cliente[$origem] ?? ''));
            if($valorOpcional !== ''){
                $dadosNota['tomador'][$campo] = $campo === 'fone' ? $this->somenteDigitos($valorOpcional) : $valorOpcional;
            }
        }

        foreach(['nome', 'CEP', 'logradouro', 'numero', 'bairro'] as $campoObrigatorio){
            if(trim((string) ($dadosNota['tomador'][$campoObrigatorio] ?? '')) === ''){
                throw new \InvalidArgumentException('Campo fiscal obrigatório ausente para emissão: ' . $campoObrigatorio);
            }
        }

        return [
            'cert' => $segredos['cert'],
            'senhaCert' => $segredos['senhaCert'],
            'dadosNota' => $dadosNota
        ];
    }

    public function carregarSegredosCertificado()
    {
        $path = NfseConfigService::certPath();
        $senha = NfseConfigService::certPassword();

        if($path === '' || $senha === ''){
            throw new \RuntimeException('Certificado NFS-e ou senha não configurados.');
        }
        if(strpos(realpath($path) ?: $path, realpath(dirname(__DIR__, 2) . '/public') ?: dirname(__DIR__, 2) . '/public') === 0){
            throw new \RuntimeException('Certificado NFS-e não pode estar sob o diretório público.');
        }
        if(!is_readable($path)){
            throw new \RuntimeException('Certificado NFS-e não está legível pela aplicação.');
        }

        $conteudo = file_get_contents($path);
        if($conteudo === false || $conteudo === ''){
            throw new \RuntimeException('Certificado NFS-e vazio ou indisponível.');
        }

        return [
            'cert' => base64_encode($conteudo),
            'senhaCert' => $senha
        ];
    }

    private function validarSegredos(array $segredos)
    {
        if(empty($segredos['cert']) || empty($segredos['senhaCert'])){
            throw new \RuntimeException('Credenciais do certificado NFS-e ausentes.');
        }
    }

    private function descricaoServico(array $cobranca)
    {
        $plano = trim((string) ($cobranca['PLA_Nome'] ?? 'Disparador.net'));
        return 'Licenciamento de uso da plataforma Disparador.net - ' . $plano;
    }

    private function somenteDigitos($valor)
    {
        return preg_replace('/\D/', '', (string) $valor);
    }

    private function dataValida($data)
    {
        $dt = \DateTime::createFromFormat('Y-m-d', (string) $data);
        return $dt && $dt->format('Y-m-d') === $data;
    }
}
