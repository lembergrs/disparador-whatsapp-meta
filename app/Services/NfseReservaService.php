<?php

namespace Services;

use Models\NfseEmissao;

class NfseReservaService
{
    private $emissoes;
    private $aptidaoFiscal;

    public function __construct(?NfseEmissao $emissoes = null, ?NfseAptidaoFiscalService $aptidaoFiscal = null)
    {
        $this->emissoes = $emissoes ?: new NfseEmissao();
        $this->aptidaoFiscal = $aptidaoFiscal ?: new NfseAptidaoFiscalService();
    }

    public function garantirRegistroLocal(array $cobranca, array $cliente)
    {
        $aptidao = $this->aptidaoFiscal->validarCliente($cliente);
        $status = $aptidao['apto'] ? NfseEmissao::STATUS_PENDENTE : NfseEmissao::STATUS_PENDENTE_DADOS;

        $emissao = $this->emissoes->criarOuBuscarPorCobranca($cobranca, [
            'status' => $status,
            'valor' => $cobranca['COB_Valor'] ?? 0,
            'competencia' => !empty($cobranca['COB_DataPagamento'])
                ? substr((string) $cobranca['COB_DataPagamento'], 0, 10)
                : date('Y-m-d'),
            'prestador_cnpj' => NfseConfigService::prestadorCnpj(),
            'ambiente' => NfseConfigService::ambiente(),
            'serie' => NfseConfigService::dpsSerie()
        ]);

        return [
            'emissao' => $emissao,
            'aptidao' => $aptidao
        ];
    }
}
