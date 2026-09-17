<?php

namespace Services;

use Models\Cliente;
use Models\Cobranca;
use Models\NfseEmissao;
use Models\NfseReconciliacaoExterna;

class NfseExternalReconciliationService
{
    private $emissoes;
    private $clientes;
    private $cobrancas;
    private $reconciliacoes;
    private $builder;
    private $client;
    private $mapper;

    public function __construct(
        ?NfseEmissao $emissoes = null,
        ?Cliente $clientes = null,
        ?Cobranca $cobrancas = null,
        ?NfseReconciliacaoExterna $reconciliacoes = null,
        ?NfsePayloadBuilder $builder = null,
        ?NfseApiClient $client = null,
        ?NfseApiResponseMapper $mapper = null
    ){
        $this->emissoes = $emissoes ?: new NfseEmissao();
        $this->clientes = $clientes ?: new Cliente();
        $this->cobrancas = $cobrancas ?: new Cobranca();
        $this->reconciliacoes = $reconciliacoes ?: new NfseReconciliacaoExterna();
        $this->builder = $builder ?: new NfsePayloadBuilder();
        $this->client = $client ?: new NfseApiClient();
        $this->mapper = $mapper ?: new NfseApiResponseMapper();
    }

    public function registrar($nfseId, $chaveAcesso, array $admin = [])
    {
        if(($admin['nivel'] ?? '') !== 'admin'){
            throw new \RuntimeException('Apenas administradores podem reconciliar NFS-e externa.');
        }

        $chave = preg_replace('/\D/', '', (string) $chaveAcesso);
        if(strlen($chave) !== 50){
            throw new \InvalidArgumentException('Informe uma chave de acesso de NFS-e válida com 50 dígitos.');
        }

        $tentativa = $this->emissoes->buscarPorId((int) $nfseId);
        if(!$tentativa || (int) ($tentativa['NFE_EmissaoAtiva'] ?? 0) !== 1){
            throw new \InvalidArgumentException('Tentativa fiscal ativa não encontrada.');
        }
        if(in_array($tentativa['NFE_Status'] ?? '', [NfseEmissao::STATUS_EMITIDA, NfseEmissao::STATUS_CANCELAMENTO_PENDENTE, NfseEmissao::STATUS_CANCELADA], true)){
            throw new \RuntimeException('Este registro não pode ser substituído por uma emissão externa.');
        }

        $cliente = $this->clientes->buscar((int) $tentativa['CLI_ID']);
        $cobranca = $this->cobrancas->buscar((int) $tentativa['COB_ID']);
        if(!$cliente || !$cobranca || ($cobranca['COB_Status'] ?? '') !== 'pago' || (float) ($cobranca['COB_Valor'] ?? 0) <= 0){
            throw new \RuntimeException('A cobrança vinculada não está apta para reconciliação fiscal.');
        }

        $segredos = $this->builder->carregarSegredosCertificado();
        $http = $this->client->consultarXml(['cert' => $segredos['cert'], 'senhaCert' => $segredos['senhaCert'], 'idNota' => $chave]);
        $resultado = $this->mapper->mapearXml($http);
        if(empty($resultado['sucesso']) || empty($resultado['conteudo'])){
            throw new \RuntimeException($resultado['error_message'] ?? 'A NFS-e não pôde ser confirmada no ambiente nacional.');
        }

        $xml = (string) $resultado['conteudo'];
        $nota = $this->extrairEValidar($xml, $chave, $cliente, $cobranca);
        $novoId = $this->reconciliacoes->registrar($tentativa, $cobranca, $nota);

        $pathXml = $this->salvarArquivoPrivado('xml', $xml);
        $this->emissoes->persistirArquivoXml($novoId, $pathXml, hash('sha256', $xml));

        try{
            $pdfHttp = $this->client->consultarPdf(['cert' => $segredos['cert'], 'senhaCert' => $segredos['senhaCert'], 'idNota' => $chave]);
            $pdf = $this->mapper->mapearPdf($pdfHttp);
            if(!empty($pdf['sucesso']) && !empty($pdf['conteudo'])){
                $pathPdf = $this->salvarArquivoPrivado('pdf', $pdf['conteudo']);
                $this->emissoes->persistirArquivoPdf($novoId, $pathPdf, $pdf['hash'] ?? hash('sha256', $pdf['conteudo']));
            }
        }catch(\Throwable $e){
            $this->emissoes->registrarFalhaDocumento($novoId, 'consulta_pdf', 'pdf_externo_nao_obtido', 'NFS-e reconciliada; PDF poderá ser consultado novamente.');
        }

        return ['sucesso' => true, 'nfse_id' => $novoId, 'mensagem' => 'NFS-e externa confirmada e vinculada à cobrança. A tentativa automática anterior foi preservada no histórico.'];
    }

    private function extrairEValidar($xml, $chave, array $cliente, array $cobranca)
    {
        $dom = new \DOMDocument();
        $anterior = libxml_use_internal_errors(true);
        $ok = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($anterior);
        if(!$ok){
            throw new \RuntimeException('O ambiente nacional retornou um XML inválido.');
        }

        $xp = new \DOMXPath($dom);
        $ids = $xp->query('//*[@Id or @ID or @id]');
        $chaveConfirmada = false;
        foreach($ids ?: [] as $node){
            foreach(['Id','ID','id'] as $attr){
                if($node->hasAttribute($attr) && strpos(preg_replace('/\D/', '', $node->getAttribute($attr)), $chave) !== false){
                    $chaveConfirmada = true;
                    break 2;
                }
            }
        }
        if(!$chaveConfirmada){
            throw new \RuntimeException('O XML consultado não corresponde à chave de acesso informada.');
        }

        $prestador = preg_replace('/\D/', '', NfseConfigService::prestadorCnpj());
        $cnpjs = $this->valores($xp, '//*[local-name()="CNPJ"]');
        $cnpjs = array_map(function($v){ return preg_replace('/\D/', '', $v); }, $cnpjs);
        if(strlen($prestador) !== 14 || !in_array($prestador, $cnpjs, true)){
            throw new \RuntimeException('A NFS-e consultada não pertence ao CNPJ prestador configurado no Disparador.');
        }

        $documentoCliente = preg_replace('/\D/', '', (string) ($cliente['CLI_NFSe_CNPJ'] ?? $cliente['CLI_CPF_CNPJ'] ?? ''));
        if($documentoCliente !== ''){
            $documentosXml = array_merge($cnpjs, array_map(function($v){ return preg_replace('/\D/', '', $v); }, $this->valores($xp, '//*[local-name()="CPF"]')));
            if(!in_array($documentoCliente, $documentosXml, true)){
                throw new \RuntimeException('O tomador da NFS-e consultada não corresponde ao cliente da cobrança.');
            }
        }

        $numeroNfse = $this->primeiro($xp, '//*[local-name()="nNFSe"]');
        $numDps = $this->primeiro($xp, '//*[local-name()="nDPS"]');
        $serie = $this->primeiro($xp, '//*[local-name()="serie"]');
        $dataEmissaoRaw = $this->primeiro($xp, '//*[local-name()="dhEmi"]');
        $competenciaRaw = $this->primeiro($xp, '//*[local-name()="dCompet"]');
        $valorRaw = $this->primeiro($xp, '//*[local-name()="vServPrest"]') ?: $this->primeiro($xp, '//*[local-name()="vLiq"]');

        if($numeroNfse === '' || $numDps === '' || $serie === '' || $dataEmissaoRaw === '' || $valorRaw === ''){
            throw new \RuntimeException('O XML oficial não contém todos os dados necessários para registrar a NFS-e externa com segurança.');
        }

        $valor = (float) str_replace(',', '.', $valorRaw);
        if(abs($valor - (float) ($cobranca['COB_Valor'] ?? 0)) > 0.01){
            throw new \RuntimeException('O valor da NFS-e consultada é diferente do valor da cobrança.');
        }

        try{
            $dataEmissao = (new \DateTimeImmutable($dataEmissaoRaw))->format('Y-m-d H:i:s');
        }catch(\Throwable $e){
            throw new \RuntimeException('Data de emissão inválida no XML oficial.');
        }
        $competencia = $competenciaRaw !== '' ? substr($competenciaRaw, 0, 10) : substr($dataEmissao, 0, 10);

        return [
            'chave_acesso' => $chave,
            'prestador_cnpj' => $prestador,
            'ambiente' => 'production',
            'numero_nfse' => $numeroNfse,
            'num_dps' => $numDps,
            'serie' => $serie,
            'data_emissao' => $dataEmissao,
            'competencia' => $competencia,
            'valor' => $valor,
            'codigo_tributacao' => $this->primeiro($xp, '//*[local-name()="cTribNac"]'),
            'descricao_servico' => $this->primeiro($xp, '//*[local-name()="xDescServ"]')
        ];
    }

    private function primeiro(\DOMXPath $xp, $query)
    {
        $nodes = $xp->query($query);
        return ($nodes && $nodes->length) ? trim((string) $nodes->item(0)->textContent) : '';
    }

    private function valores(\DOMXPath $xp, $query)
    {
        $nodes = $xp->query($query);
        $out = [];
        if($nodes){
            foreach($nodes as $node){ $out[] = trim((string) $node->textContent); }
        }
        return $out;
    }

    private function salvarArquivoPrivado($tipo, $conteudo)
    {
        $tipo = $tipo === 'pdf' ? 'pdf' : 'xml';
        $base = dirname(__DIR__, 2) . '/storage/nfse/' . $tipo;
        if(!is_dir($base)){ mkdir($base, 0770, true); }
        $nome = date('YmdHis') . '_' . bin2hex(random_bytes(12)) . '.' . $tipo;
        $path = $base . '/' . $nome;
        $tmp = $path . '.tmp';
        file_put_contents($tmp, $conteudo, LOCK_EX);
        chmod($tmp, 0660);
        rename($tmp, $path);
        return 'storage/nfse/' . $tipo . '/' . $nome;
    }
}
