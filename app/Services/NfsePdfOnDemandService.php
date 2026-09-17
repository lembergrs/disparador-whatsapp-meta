<?php

namespace Services;

class NfsePdfOnDemandService
{
    private $emissionService;
    private $client;
    private $mapper;

    public function __construct(
        ?NfseEmissionService $emissionService = null,
        ?NfseApiClient $client = null,
        ?NfseApiResponseMapper $mapper = null
    ){
        $this->emissionService = $emissionService ?: new NfseEmissionService();
        $this->client = $client ?: new NfseApiClient();
        $this->mapper = $mapper ?: new NfseApiResponseMapper();
    }

    public function gerar($nfseId, array $usuario = [])
    {
        $xml = $this->emissionService->arquivoDownload((int) $nfseId, 'xml', $usuario);
        $conteudoXml = file_get_contents($xml['path']);
        if($conteudoXml === false || trim((string) $conteudoXml) === ''){
            throw new \RuntimeException('XML da NFS-e indisponível para geração do PDF.');
        }

        $http = $this->client->gerarPdfXml((string) $conteudoXml);
        $resultado = $this->mapper->mapearPdf($http);
        if(empty($resultado['sucesso']) || empty($resultado['conteudo'])){
            throw new \RuntimeException($resultado['error_message'] ?? 'Não foi possível gerar o PDF da NFS-e.');
        }

        return [
            'conteudo' => (string) $resultado['conteudo'],
            'filename' => 'nfse-' . (int) $nfseId . '.pdf',
            'content_type' => 'application/pdf'
        ];
    }
}
