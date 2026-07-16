<?php

namespace Services;

use Models\NfseEmissao;
use Models\Cliente;
use Models\Cobranca;

class NfseEmissionService
{
    private $emissoes;
    private $clientes;
    private $cobrancas;
    private $aptidao;
    private $sequencias;
    private $builder;
    private $client;
    private $mapper;

    public function __construct(
        ?NfseEmissao $emissoes = null,
        ?Cliente $clientes = null,
        ?Cobranca $cobrancas = null,
        ?NfseAptidaoFiscalService $aptidao = null,
        ?NfseDpsSequenciaService $sequencias = null,
        ?NfsePayloadBuilder $builder = null,
        ?NfseApiClient $client = null,
        ?NfseApiResponseMapper $mapper = null
    ){
        $this->emissoes = $emissoes ?: new NfseEmissao();
        $this->clientes = $clientes ?: new Cliente();
        $this->cobrancas = $cobrancas ?: new Cobranca();
        $this->aptidao = $aptidao ?: new NfseAptidaoFiscalService();
        $this->sequencias = $sequencias ?: new NfseDpsSequenciaService();
        $this->builder = $builder ?: new NfsePayloadBuilder();
        $this->client = $client ?: new NfseApiClient();
        $this->mapper = $mapper ?: new NfseApiResponseMapper();
    }

    public function emitirManual($clienteId, $cobrancaId, array $admin = [])
    {
        if(($admin['nivel'] ?? '') !== 'admin'){
            throw new \RuntimeException('Apenas administradores podem emitir NFS-e manualmente.');
        }

        $cliente = $this->clientes->buscar((int) $clienteId);
        $cobranca = $this->cobrancas->buscar((int) $cobrancaId);

        if(!$cliente || !$cobranca || (int) ($cobranca['CLI_ID'] ?? 0) !== (int) ($cliente['CLI_ID'] ?? 0)){
            throw new \InvalidArgumentException('Cliente ou cobrança inválidos para emissão manual de NFS-e.');
        }

        if(($cobranca['COB_Status'] ?? '') !== 'pago'){
            throw new \InvalidArgumentException('A emissão manual de NFS-e exige cobrança paga.');
        }

        if((float) ($cobranca['COB_Valor'] ?? 0) <= 0){
            throw new \InvalidArgumentException('A emissão manual de NFS-e exige cobrança com valor positivo.');
        }

        $emissao = $this->emissoes->criarOuBuscarPorCobranca($cobranca, [
            'status' => NfseEmissao::STATUS_PENDENTE_DADOS,
            'valor' => $cobranca['COB_Valor'] ?? 0,
            'competencia' => !empty($cobranca['COB_DataPagamento']) ? substr((string) $cobranca['COB_DataPagamento'], 0, 10) : date('Y-m-d'),
            'prestador_cnpj' => NfseConfigService::prestadorCnpj(),
            'ambiente' => NfseConfigService::ambiente(),
            'serie' => NfseConfigService::dpsSerie()
        ]);

        $bloqueio = $this->bloqueioPorStatus($emissao['NFE_Status'] ?? null);
        if($bloqueio !== null){
            return ['sucesso' => false, 'emissao' => $emissao, 'tipo' => 'status_bloqueado', 'mensagem' => $bloqueio];
        }

        $aptidao = $this->aptidao->validarCliente($cliente);
        if(empty($aptidao['apto'])){
            $this->emissoes->atualizarStatus((int) $emissao['NFE_ID'], NfseEmissao::STATUS_PENDENTE_DADOS, [
                'tipo' => 'aptidao_fiscal',
                'codigo' => $aptidao['tipo_bloqueio'] ?? 'dados_incompletos',
                'mensagem' => $aptidao['mensagem'] ?? 'Cliente não apto para emissão fiscal.'
            ], $emissao['NFE_Status'] ?? null);

            return ['sucesso' => false, 'emissao' => $emissao, 'aptidao' => $aptidao, 'tipo' => 'cliente_nao_apto', 'mensagem' => $aptidao['mensagem'] ?? 'Cliente não apto.'];
        }

        try{
            $snapshotFiscal = $this->builder->validarParametrosFiscaisConfigurados($emissao);
        }catch(\Throwable $e){
            $resultadoBloqueio = [
                'sucesso' => false,
                'tipo_erro' => 'configuracao_fiscal',
                'temporario' => false,
                'incerto' => false,
                'error_code' => 'nfse_configuracao_fiscal_incompleta',
                'error_message' => $e->getMessage(),
                'retorno_sanitizado' => ['motivo' => 'configuracao_ausente']
            ];
            $this->emissoes->atualizarStatus((int) $emissao['NFE_ID'], NfseEmissao::STATUS_PENDENTE_DADOS, [
                'tipo' => 'configuracao_fiscal',
                'codigo' => 'nfse_configuracao_fiscal_incompleta',
                'mensagem' => $e->getMessage()
            ], $emissao['NFE_Status'] ?? null);
            $this->registrarLogSeguro('bloqueio_configuracao', $cliente, $cobranca, $emissao, $resultadoBloqueio);

            return ['sucesso' => false, 'emissao' => $emissao, 'tipo' => 'configuracao_fiscal_incompleta', 'mensagem' => $e->getMessage()];
        }

        $this->emissoes->prepararSnapshotFiscal((int) $emissao['NFE_ID'], $snapshotFiscal['codigo_tributacao_nacional'], $snapshotFiscal['descricao_servico']);
        $emissao = $this->emissoes->buscarPorId((int) $emissao['NFE_ID']);

        $statusAtual = $emissao['NFE_Status'] ?? NfseEmissao::STATUS_PENDENTE_DADOS;
        if($statusAtual === NfseEmissao::STATUS_PENDENTE_DADOS){
            $this->emissoes->atualizarStatus((int) $emissao['NFE_ID'], NfseEmissao::STATUS_PENDENTE, [], NfseEmissao::STATUS_PENDENTE_DADOS);
            $statusAtual = NfseEmissao::STATUS_PENDENTE;
            $emissao = $this->emissoes->buscarPorId((int) $emissao['NFE_ID']);
        }

        if(!$this->emissoes->prepararProcessamento((int) $emissao['NFE_ID'], $statusAtual)){
            return ['sucesso' => false, 'tipo' => 'concorrencia', 'mensagem' => 'A emissão foi assumida por outro processo.', 'emissao' => $emissao];
        }

        $emissao = $this->emissoes->buscarPorId((int) $emissao['NFE_ID']);

        try{
            if(empty($emissao['NFE_NumDps'])){
                $numDps = $this->sequencias->reservar(NfseConfigService::prestadorCnpj(), NfseConfigService::ambiente(), NfseConfigService::dpsSerie());
                $this->emissoes->atribuirNumDps((int) $emissao['NFE_ID'], $numDps);
                $emissao = $this->emissoes->buscarPorId((int) $emissao['NFE_ID']);
            }

            $payload = $this->builder->montarEmissao($cliente, $cobranca, $emissao, $this->builder->carregarSegredosCertificado());
            $http = $this->client->emitir($payload);
            $resultado = $this->mapper->mapearEmissao($http);
        }catch(\Throwable $e){
            $resultado = [
                'sucesso' => false,
                'tipo_erro' => 'definitivo',
                'temporario' => false,
                'incerto' => false,
                'error_code' => 'falha_local_pre_envio',
                'error_message' => $e->getMessage(),
                'retorno_sanitizado' => []
            ];
        }

        if(!empty($resultado['sucesso'])){
            $this->emissoes->persistirSucessoEmissao((int) $emissao['NFE_ID'], $resultado);
            $this->salvarXmlDaEmissao((int) $emissao['NFE_ID'], $resultado);
        }else{
            $this->emissoes->persistirErroEmissao((int) $emissao['NFE_ID'], $resultado);
        }

        $this->registrarLogSeguro('emitir', $cliente, $cobranca, $emissao, $resultado);

        return [
            'sucesso' => !empty($resultado['sucesso']),
            'tipo' => !empty($resultado['sucesso']) ? 'emitida' : ($resultado['tipo_erro'] ?? 'erro'),
            'resultado' => $this->resultadoSeguro($resultado),
            'emissao' => $this->emissoes->buscarPorId((int) $emissao['NFE_ID']),
            'aptidao' => $aptidao
        ];
    }

    public function consultarPdfManual($nfseId, array $admin = [])
    {
        if(($admin['nivel'] ?? '') !== 'admin'){
            throw new \RuntimeException('Apenas administradores podem consultar PDF de NFS-e.');
        }

        $emissao = $this->emissoes->buscarPorId((int) $nfseId);
        if(!$emissao || empty($emissao['NFE_ChaveAcesso'])){
            throw new \InvalidArgumentException('NFS-e sem chave de acesso para consulta de PDF.');
        }

        $segredos = $this->builder->carregarSegredosCertificado();
        $http = $this->client->consultarPdf(['cert' => $segredos['cert'], 'senhaCert' => $segredos['senhaCert'], 'idNota' => $emissao['NFE_ChaveAcesso']]);
        $resultado = $this->mapper->mapearPdf($http);

        if(!empty($resultado['sucesso'])){
            $path = $this->salvarArquivoPrivado('pdf', $resultado['conteudo']);
            $this->emissoes->persistirArquivoPdf((int) $nfseId, $path, $resultado['hash']);
        }

        $this->emissoes->persistirRequestConsulta((int) $nfseId, $resultado, 'consulta');
        $this->registrarLogSeguro('consultar_pdf', ['CLI_ID' => $emissao['CLI_ID'] ?? 0], ['COB_ID' => $emissao['COB_ID'] ?? 0], $emissao, $resultado);

        return $this->resultadoSeguro($resultado);
    }


    public function consultarXmlManual($nfseId, array $admin = [])
    {
        if(($admin['nivel'] ?? '') !== 'admin'){
            throw new \RuntimeException('Apenas administradores podem consultar XML de NFS-e.');
        }

        $emissao = $this->emissoes->buscarPorId((int) $nfseId);
        if(!$emissao || empty($emissao['NFE_ChaveAcesso'])){
            throw new \InvalidArgumentException('NFS-e sem chave de acesso para consulta de XML.');
        }

        $segredos = $this->builder->carregarSegredosCertificado();
        $http = $this->client->consultarXml(['cert' => $segredos['cert'], 'senhaCert' => $segredos['senhaCert'], 'idNota' => $emissao['NFE_ChaveAcesso']]);
        $resultado = $this->mapper->mapearXml($http);

        if(!empty($resultado['sucesso'])){
            $path = $this->salvarArquivoPrivado('xml', $resultado['conteudo']);
            $this->emissoes->persistirArquivoXml((int) $nfseId, $path, $resultado['hash']);
        }

        $this->emissoes->persistirRequestConsulta((int) $nfseId, $resultado, 'consulta');
        $this->registrarLogSeguro('consultar_xml', ['CLI_ID' => $emissao['CLI_ID'] ?? 0], ['COB_ID' => $emissao['COB_ID'] ?? 0], $emissao, $resultado);

        return $this->resultadoSeguro($resultado);
    }

    public function consultarEventosManual($nfseId, array $admin = [])
    {
        if(($admin['nivel'] ?? '') !== 'admin'){
            throw new \RuntimeException('Apenas administradores podem consultar eventos de NFS-e.');
        }

        $emissao = $this->emissoes->buscarPorId((int) $nfseId);
        if(!$emissao || empty($emissao['NFE_ChaveAcesso'])){
            throw new \InvalidArgumentException('NFS-e sem chave de acesso para consulta de eventos.');
        }

        $segredos = $this->builder->carregarSegredosCertificado();
        $http = $this->client->consultarEventos([
            'cert' => $segredos['cert'],
            'senhaCert' => $segredos['senhaCert'],
            'idNota' => $emissao['NFE_ChaveAcesso'],
            'tipoEvento' => '101101',
            'numeroSequencial' => 1
        ]);
        $resultado = $this->mapper->mapearConsultaJson($http);
        $this->emissoes->persistirRequestConsulta((int) $nfseId, $resultado, 'consulta');
        $this->registrarLogSeguro('consultar_eventos', ['CLI_ID' => $emissao['CLI_ID'] ?? 0], ['COB_ID' => $emissao['COB_ID'] ?? 0], $emissao, $resultado);

        return $this->resultadoSeguro($resultado);
    }

    public function reconsultarManual($nfseId, array $admin = [])
    {
        $xml = $this->consultarXmlManual($nfseId, $admin);
        $pdf = $this->consultarPdfManual($nfseId, $admin);
        $eventos = $this->consultarEventosManual($nfseId, $admin);

        return ['sucesso' => !empty($xml['sucesso']) || !empty($pdf['sucesso']) || !empty($eventos['sucesso']), 'xml' => $xml, 'pdf' => $pdf, 'eventos' => $eventos];
    }

    public function cancelarManual($nfseId, $codigoMotivo, $motivo, array $admin = [])
    {
        if(($admin['nivel'] ?? '') !== 'admin'){
            throw new \RuntimeException('Apenas administradores podem cancelar NFS-e.');
        }

        $codigoMotivo = (int) $codigoMotivo;
        $motivo = trim((string) $motivo);
        if(!in_array($codigoMotivo, [1,2,3,4,5,9], true) || $motivo === ''){
            throw new \InvalidArgumentException('Motivo fiscal de cancelamento inválido.');
        }

        $emissao = $this->emissoes->buscarPorId((int) $nfseId);
        if(!$emissao || empty($emissao['NFE_ChaveAcesso'])){
            throw new \InvalidArgumentException('NFS-e sem chave de acesso para cancelamento.');
        }

        $segredos = $this->builder->carregarSegredosCertificado();
        $http = $this->client->cancelar([
            'cert' => $segredos['cert'],
            'senhaCert' => $segredos['senhaCert'],
            'idNota' => $emissao['NFE_ChaveAcesso'],
            'cnpjEmitente' => NfseConfigService::prestadorCnpj(),
            'codigoMotivo' => $codigoMotivo,
            'motivo' => $motivo
        ]);
        $resultado = $this->mapper->mapearConsultaJson($http);
        $this->emissoes->persistirRequestConsulta((int) $nfseId, $resultado, 'cancelamento');
        $this->registrarLogSeguro('cancelar', ['CLI_ID' => $emissao['CLI_ID'] ?? 0], ['COB_ID' => $emissao['COB_ID'] ?? 0], $emissao, $resultado);

        return $this->resultadoSeguro($resultado);
    }

    public function arquivoDownload($nfseId, $tipo, array $admin = [])
    {
        if(($admin['nivel'] ?? '') !== 'admin'){
            throw new \RuntimeException('Apenas administradores podem baixar documentos de NFS-e.');
        }

        $tipo = $tipo === 'pdf' ? 'pdf' : 'xml';
        $info = $this->emissoes->arquivoPrivado((int) $nfseId, $tipo);
        $pathRelativo = $info['path'] ?? '';
        if(!$info || $pathRelativo === '' || strpos($pathRelativo, '..') !== false || $pathRelativo[0] === '/'){
            throw new \InvalidArgumentException('Documento fiscal não encontrado.');
        }

        $path = dirname(__DIR__, 2) . '/' . ltrim(str_replace('\\', '/', $pathRelativo), '/');
        $base = dirname(__DIR__, 2) . '/storage/nfse/' . $tipo . '/';
        $real = realpath($path);
        $realBase = realpath($base);
        if(!$real || !$realBase || strpos($real, $realBase) !== 0 || !is_file($real)){
            throw new \InvalidArgumentException('Documento fiscal indisponível.');
        }

        return [
            'path' => $real,
            'filename' => 'nfse-' . (int) $nfseId . '.' . $tipo,
            'content_type' => $tipo === 'pdf' ? 'application/pdf' : 'application/xml'
        ];
    }

    private function bloqueioPorStatus($status)
    {
        if($status === NfseEmissao::STATUS_EMITIDA){ return 'NFS-e já emitida para esta cobrança.'; }
        if($status === NfseEmissao::STATUS_CANCELADA){ return 'NFS-e cancelada não pode ser reemitida automaticamente.'; }
        if($status === NfseEmissao::STATUS_PROCESSANDO){ return 'NFS-e já está em processamento.'; }
        if($status === NfseEmissao::STATUS_RECONCILIACAO_PENDENTE){ return 'NFS-e em reconciliação pendente; não reenviar emissão.'; }
        if($status === NfseEmissao::STATUS_ERRO_DEFINITIVO){ return 'NFS-e com erro definitivo exige ação administrativa explícita.'; }
        return null;
    }

    private function salvarXmlDaEmissao($nfseId, array $resultado)
    {
        $gzipB64 = $resultado['xml_gzip_base64'] ?? null;
        if(!$gzipB64){
            return;
        }

        $binario = base64_decode((string) $gzipB64, true);
        if($binario === false){
            $this->emissoes->registrarFalhaDocumento($nfseId, 'persistencia_xml', 'xml_base64_invalido', 'XML retornado pela emissão não pôde ser decodificado.');
            return;
        }

        if(strlen($binario) > 10485760){
            $this->emissoes->registrarFalhaDocumento($nfseId, 'persistencia_xml', 'xml_gzip_grande', 'XML compactado excedeu o limite operacional.');
            return;
        }

        $xml = function_exists('gzdecode') ? gzdecode($binario) : false;
        if($xml === false || strlen((string) $xml) > 10485760 || stripos((string) $xml, '<') === false){
            $this->emissoes->registrarFalhaDocumento($nfseId, 'persistencia_xml', 'xml_invalido', 'XML retornado pela emissão não pôde ser validado.');
            return;
        }

        $path = $this->salvarArquivoPrivado('xml', (string) $xml);
        $this->emissoes->persistirArquivoXml($nfseId, $path, hash('sha256', (string) $xml));
    }

    private function salvarArquivoPrivado($tipo, $conteudo)
    {
        $tipo = $tipo === 'pdf' ? 'pdf' : 'xml';
        $base = dirname(__DIR__, 2) . '/storage/nfse/' . $tipo;
        if(!is_dir($base)){
            mkdir($base, 0770, true);
        }

        $nome = date('YmdHis') . '_' . bin2hex(random_bytes(12)) . '.' . $tipo;
        $path = $base . '/' . $nome;
        $tmp = $path . '.tmp';
        file_put_contents($tmp, $conteudo, LOCK_EX);
        chmod($tmp, 0660);
        rename($tmp, $path);

        return 'storage/nfse/' . $tipo . '/' . $nome;
    }

    private function resultadoSeguro(array $resultado)
    {
        unset($resultado['conteudo'], $resultado['xml_gzip_base64']);
        return NfseSanitizer::dados($resultado);
    }

    private function registrarLogSeguro($acao, array $cliente, array $cobranca, array $emissao, array $resultado)
    {
        $dir = dirname(__DIR__, 2) . '/storage/logs';
        if(!is_dir($dir)){
            mkdir($dir, 0770, true);
        }
        $logPath = $dir . '/nfse.log';
        if(is_file($logPath) && filesize($logPath) !== false && filesize($logPath) > 10485760){
            rename($logPath, $dir . '/nfse-' . date('YmdHis') . '.log');
        }

        if(!is_file($logPath)){
            touch($logPath);
            chmod($logPath, 0660);
        }

        $linha = [
            'timestamp' => date('c'),
            'acao' => $acao,
            'CLI_ID' => (int) ($cliente['CLI_ID'] ?? 0),
            'COB_ID' => (int) ($cobranca['COB_ID'] ?? 0),
            'NFE_ID' => (int) ($emissao['NFE_ID'] ?? 0),
            'operation' => $resultado['operation'] ?? null,
            'requestId' => $resultado['request_id'] ?? null,
            'http_status' => $resultado['http_status'] ?? null,
            'duration_ms' => $resultado['duration_ms'] ?? null,
            'status_local' => !empty($resultado['sucesso']) ? NfseEmissao::STATUS_EMITIDA : ($resultado['tipo_erro'] ?? 'erro'),
            'codigo_erro' => $resultado['error_code'] ?? null
        ];

        error_log(json_encode(NfseSanitizer::dados($linha), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, 3, $logPath);
    }
}
