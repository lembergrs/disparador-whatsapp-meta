<?php

namespace Services;

class NfseApiResponseMapper
{
    public function mapearEmissao(array $http)
    {
        if(!empty($http['transport_error'])){
            return $this->erroTransporte($http);
        }

        $json = $this->json($http);
        if($json === null){
            return $this->erroEstrutural($http, 'json_invalido', 'Resposta JSON inválida da API NFS-e.', $this->incertoPorHttp($http));
        }

        if(($json['success'] ?? false) === true){
            $data = is_array($json['data'] ?? null) ? $json['data'] : [];
            return [
                'sucesso' => true,
                'tipo_resultado' => 'emissao',
                'request_id' => $http['request_id'] ?? ($json['requestId'] ?? null),
                'operation' => $json['operation'] ?? ($http['operation'] ?? null),
                'id_dps' => $data['idDps'] ?? null,
                'chave_dps' => $data['chaveDps'] ?? null,
                'chave_acesso' => $data['chaveAcesso'] ?? null,
                'data_hora_processamento' => $data['dataHoraProcessamento'] ?? null,
                'tipo_ambiente' => $data['tipoAmbiente'] ?? null,
                'versao_aplicativo' => $data['versaoAplicativo'] ?? null,
                'xml_gzip_base64' => $data['nfseXmlGZipB64'] ?? null,
                'warnings' => is_array($json['warnings'] ?? null) ? NfseSanitizer::dados($json['warnings']) : [],
                'http_status' => (int) ($http['http_status'] ?? 0),
                'duration_ms' => (int) ($http['duration_ms'] ?? 0),
                'retorno_sanitizado' => NfseSanitizer::dados($json)
            ];
        }

        return $this->erroEnvelope($http, $json);
    }

    public function mapearPdf(array $http)
    {
        $contentType = strtolower((string) ($http['content_type'] ?? ''));
        $body = (string) ($http['body'] ?? '');

        if(!empty($http['transport_error'])){
            return $this->erroTransporte($http);
        }

        if((int) ($http['http_status'] ?? 0) === 200 && strpos($contentType, 'application/pdf') !== false && strncmp($body, '%PDF-', 5) === 0 && strlen($body) >= 8 && strlen($body) <= 10485760){
            return [
                'sucesso' => true,
                'tipo_resultado' => 'pdf',
                'request_id' => $http['request_id'] ?? null,
                'content_type' => $http['content_type'] ?? '',
                'conteudo' => $body,
                'hash' => hash('sha256', $body),
                'tamanho' => strlen($body),
                'http_status' => (int) ($http['http_status'] ?? 0),
                'duration_ms' => (int) ($http['duration_ms'] ?? 0)
            ];
        }

        return $this->mapearErroNaoJsonOuEnvelope($http, 'pdf_invalido', 'Resposta da API não é PDF válido.');
    }

    public function mapearXml(array $http)
    {
        $contentType = strtolower((string) ($http['content_type'] ?? ''));
        $body = trim((string) ($http['body'] ?? ''));

        if(!empty($http['transport_error'])){
            return $this->erroTransporte($http);
        }

        if((int) ($http['http_status'] ?? 0) === 200 && strlen($body) > 0 && strlen($body) <= 10485760 && (strpos($contentType, 'xml') !== false || strncmp($body, '<?xml', 5) === 0) && $this->xmlValido($body)){
            return [
                'sucesso' => true,
                'tipo_resultado' => 'xml',
                'request_id' => $http['request_id'] ?? null,
                'content_type' => $http['content_type'] ?? '',
                'conteudo' => $body,
                'hash' => hash('sha256', $body),
                'tamanho' => strlen($body),
                'http_status' => (int) ($http['http_status'] ?? 0),
                'duration_ms' => (int) ($http['duration_ms'] ?? 0)
            ];
        }

        return $this->mapearErroNaoJsonOuEnvelope($http, 'xml_invalido', 'Resposta da API não é XML válido.');
    }

    public function mapearConsultaJson(array $http)
    {
        if(!empty($http['transport_error'])){
            return $this->erroTransporte($http);
        }

        $json = $this->json($http);
        if($json === null){
            return $this->erroEstrutural($http, 'json_invalido', 'Resposta JSON inválida da API NFS-e.', $this->incertoPorHttp($http));
        }

        if(($json['success'] ?? false) === true){
            return [
                'sucesso' => true,
                'tipo_resultado' => 'consulta',
                'request_id' => $http['request_id'] ?? ($json['requestId'] ?? null),
                'operation' => $json['operation'] ?? ($http['operation'] ?? null),
                'data' => NfseSanitizer::dados(is_array($json['data'] ?? null) ? $json['data'] : []),
                'warnings' => NfseSanitizer::dados(is_array($json['warnings'] ?? null) ? $json['warnings'] : []),
                'http_status' => (int) ($http['http_status'] ?? 0),
                'duration_ms' => (int) ($http['duration_ms'] ?? 0)
            ];
        }

        return $this->erroEnvelope($http, $json);
    }

    private function mapearErroNaoJsonOuEnvelope(array $http, $codigo, $mensagem)
    {
        $json = $this->json($http);
        if($json !== null && (($json['success'] ?? null) === false || isset($json['error']))){
            return $this->erroEnvelope($http, $json);
        }

        return $this->erroEstrutural($http, $codigo, $mensagem, $this->incertoPorHttp($http));
    }

    private function erroEnvelope(array $http, array $json)
    {
        $error = is_array($json['error'] ?? null) ? $json['error'] : [];
        $httpStatus = (int) ($http['http_status'] ?? 0);
        $codigo = (string) ($error['code'] ?? 'erro_api_nfse');
        $mensagem = (string) ($error['message'] ?? 'Erro retornado pela API NFS-e.');

        return [
            'sucesso' => false,
            'request_id' => $http['request_id'] ?? ($json['requestId'] ?? null),
            'operation' => $json['operation'] ?? ($http['operation'] ?? null),
            'error_code' => NfseSanitizer::mensagem($codigo),
            'error_message' => NfseSanitizer::mensagem($mensagem),
            'error_details' => NfseSanitizer::dados(is_array($error['details'] ?? null) ? $error['details'] : []),
            'http_status' => $httpStatus,
            'tipo_erro' => $this->tipoErro($httpStatus, $codigo),
            'temporario' => $this->temporario($httpStatus, $codigo),
            'incerto' => false,
            'duration_ms' => (int) ($http['duration_ms'] ?? 0),
            'retorno_sanitizado' => NfseSanitizer::dados($json)
        ];
    }

    private function erroTransporte(array $http)
    {
        $incerto = !empty($http['incerto']) || (!empty($http['timeout']) && (($http['failure_stage'] ?? '') !== 'temporario_pre_envio'));
        return [
            'sucesso' => false,
            'request_id' => $http['request_id'] ?? null,
            'operation' => $http['operation'] ?? null,
            'error_code' => NfseSanitizer::mensagem($http['error_code'] ?? 'erro_transporte'),
            'error_message' => NfseSanitizer::mensagem($http['error_message'] ?? 'Falha de transporte na API NFS-e.'),
            'error_details' => [],
            'http_status' => (int) ($http['http_status'] ?? 0),
            'tipo_erro' => $incerto ? 'incerto' : 'temporario',
            'temporario' => !$incerto,
            'incerto' => $incerto,
            'duration_ms' => (int) ($http['duration_ms'] ?? 0),
            'retorno_sanitizado' => []
        ];
    }

    private function erroEstrutural(array $http, $codigo, $mensagem, $incerto)
    {
        return [
            'sucesso' => false,
            'request_id' => $http['request_id'] ?? null,
            'operation' => $http['operation'] ?? null,
            'error_code' => $codigo,
            'error_message' => NfseSanitizer::mensagem($mensagem),
            'error_details' => [],
            'http_status' => (int) ($http['http_status'] ?? 0),
            'tipo_erro' => $incerto ? 'incerto' : 'definitivo',
            'temporario' => false,
            'incerto' => $incerto,
            'duration_ms' => (int) ($http['duration_ms'] ?? 0),
            'retorno_sanitizado' => []
        ];
    }

    private function json(array $http)
    {
        $body = (string) ($http['body'] ?? '');
        if(trim($body) === ''){
            return null;
        }

        $json = json_decode($body, true);
        return json_last_error() === JSON_ERROR_NONE && is_array($json) ? $json : null;
    }

    private function xmlValido($xml)
    {
        if(stripos($xml, '<html') !== false){
            return false;
        }
        if(function_exists('simplexml_load_string')){
            libxml_use_internal_errors(true);
            $ok = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET) !== false;
            libxml_clear_errors();
            return $ok;
        }
        return strpos($xml, '<') !== false && strpos($xml, '>') !== false;
    }

    private function incertoPorHttp(array $http)
    {
        return (int) ($http['http_status'] ?? 0) === 0;
    }

    private function temporario($httpStatus, $codigo)
    {
        return $httpStatus === 502 || $httpStatus >= 500 || stripos((string) $codigo, 'tempor') !== false;
    }

    private function tipoErro($httpStatus, $codigo)
    {
        if($this->temporario($httpStatus, $codigo)){
            return 'temporario';
        }
        return in_array($httpStatus, [400, 401, 405], true) ? 'definitivo' : 'definitivo';
    }
}
