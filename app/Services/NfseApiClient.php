<?php

namespace Services;

class NfseApiClient
{
    private $baseUrl;
    private $token;
    private $connectTimeout;
    private $requestTimeout;
    private $transport;

    public function __construct(array $config = [], ?callable $transport = null)
    {
        $this->baseUrl = rtrim((string) ($config['base_url'] ?? NfseConfigService::baseUrl()), '/');
        $this->token = (string) ($config['auth_token'] ?? NfseConfigService::authToken());
        $this->connectTimeout = max(1, (int) ($config['connect_timeout'] ?? NfseConfigService::connectTimeout()));
        $this->requestTimeout = max(1, (int) ($config['request_timeout'] ?? NfseConfigService::requestTimeout()));
        $this->transport = $transport;
    }

    public function emitir(array $payload)
    {
        return $this->postJson('/acoes/GeraDps.php', $payload, 'nfse.gerarDps');
    }

    public function consultarPdf(array $payload)
    {
        return $this->postJson('/acoes/ConsultaDanfse.php', $payload, 'nfse.consultarDanfse');
    }

    public function consultarXml(array $payload)
    {
        return $this->postJson('/acoes/ConsultaNfseChave.php', $payload, 'nfse.consultarNfseChave');
    }

    public function consultarDps(array $payload)
    {
        return $this->postJson('/acoes/ConsultaDpsChave.php', $payload, 'nfse.consultarDpsChave');
    }

    public function consultarEventos(array $payload)
    {
        return $this->postJson('/acoes/ConsultaNfseEventos.php', $payload, 'nfse.consultarEventos');
    }

    public function cancelar(array $payload)
    {
        return $this->postJson('/acoes/CancelaNfse.php', $payload, 'nfse.cancelar');
    }

    private function postJson($endpoint, array $payload, $operation)
    {
        $this->validarConfiguracao();

        $url = $this->baseUrl . $endpoint;
        $inicio = microtime(true);
        $headers = [
            'Authorization: Bearer ' . $this->token,
            'Content-Type: application/json',
            'Accept: application/json, application/pdf, application/xml, text/xml'
        ];
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if($body === false){
            return $this->erroTransporte($operation, 'json_encode_failed', 'Falha ao preparar payload JSON.', $inicio);
        }

        if($this->transport){
            $raw = call_user_func($this->transport, [
                'url' => $url,
                'endpoint' => $endpoint,
                'headers' => $headers,
                'body' => $body,
                'connect_timeout' => $this->connectTimeout,
                'request_timeout' => $this->requestTimeout,
                'operation' => $operation
            ]);

            return $this->normalizarRespostaTransporte($raw, $operation, $inicio);
        }

        if(!function_exists('curl_init')){
            return $this->erroTransporte($operation, 'curl_missing', 'Extensão cURL indisponível.', $inicio);
        }

        $responseHeaders = [];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_TIMEOUT => $this->requestTimeout,
            CURLOPT_HEADERFUNCTION => function($curl, $header) use (&$responseHeaders){
                $len = strlen($header);
                $parts = explode(':', $header, 2);
                if(count($parts) === 2){
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return $len;
            }
        ]);

        $bodyResposta = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        $durationMs = (int) round((microtime(true) - $inicio) * 1000);

        if($errno !== 0){
            return [
                'transport_error' => true,
                'timeout' => in_array($errno, [CURLE_OPERATION_TIMEDOUT, CURLE_COULDNT_CONNECT], true),
                'operation' => $operation,
                'http_status' => 0,
                'content_type' => '',
                'request_id' => $responseHeaders['x-request-id'] ?? null,
                'body' => null,
                'error_code' => 'curl_' . $errno,
                'error_message' => NfseSanitizer::mensagem($error ?: 'Falha de comunicação com a API NFS-e.'),
                'duration_ms' => $durationMs
            ];
        }

        return [
            'transport_error' => false,
            'timeout' => false,
            'operation' => $operation,
            'http_status' => $httpStatus,
            'content_type' => $contentType,
            'request_id' => $responseHeaders['x-request-id'] ?? null,
            'body' => $bodyResposta === false ? '' : (string) $bodyResposta,
            'error_code' => null,
            'error_message' => null,
            'duration_ms' => $durationMs
        ];
    }

    private function validarConfiguracao()
    {
        if($this->baseUrl === ''){
            throw new \RuntimeException('Configuração da URL da API NFS-e ausente.');
        }

        if($this->token === ''){
            throw new \RuntimeException('Token da API NFS-e não configurado.');
        }
    }

    private function normalizarRespostaTransporte($raw, $operation, $inicio)
    {
        $raw = is_array($raw) ? $raw : [];
        $headers = array_change_key_case($raw['headers'] ?? [], CASE_LOWER);

        return [
            'transport_error' => (bool) ($raw['transport_error'] ?? false),
            'timeout' => (bool) ($raw['timeout'] ?? false),
            'operation' => $operation,
            'http_status' => (int) ($raw['http_status'] ?? 0),
            'content_type' => (string) ($raw['content_type'] ?? ($headers['content-type'] ?? '')),
            'request_id' => $raw['request_id'] ?? ($headers['x-request-id'] ?? null),
            'body' => (string) ($raw['body'] ?? ''),
            'error_code' => $raw['error_code'] ?? null,
            'error_message' => NfseSanitizer::mensagem($raw['error_message'] ?? null),
            'duration_ms' => (int) ($raw['duration_ms'] ?? round((microtime(true) - $inicio) * 1000))
        ];
    }

    private function erroTransporte($operation, $codigo, $mensagem, $inicio)
    {
        return [
            'transport_error' => true,
            'timeout' => false,
            'operation' => $operation,
            'http_status' => 0,
            'content_type' => '',
            'request_id' => null,
            'body' => null,
            'error_code' => $codigo,
            'error_message' => NfseSanitizer::mensagem($mensagem),
            'duration_ms' => (int) round((microtime(true) - $inicio) * 1000)
        ];
    }
}
