<?php

require_once __DIR__ . '/../app/Services/NfseConfigService.php';
require_once __DIR__ . '/../app/Services/NfseSanitizer.php';
require_once __DIR__ . '/../app/Services/NfseApiClient.php';

use Services\NfseApiClient;

function nfseClientAssert($cond, $msg){ if(!$cond){ fwrite(STDERR, "FAIL: {$msg}\n"); exit(1); } }

$calls = [];
$client = new NfseApiClient([
    'base_url' => 'https://api.disparador.net',
    'auth_token' => 'TOKEN_FICTICIO_INVALIDO',
    'connect_timeout' => 2,
    'request_timeout' => 5
], function($request) use (&$calls){
    $calls[] = $request;
    return [
        'http_status' => 200,
        'headers' => ['X-Request-Id' => 'req-1', 'Content-Type' => 'application/json'],
        'body' => json_encode(['success' => true, 'requestId' => 'req-1', 'operation' => 'nfse.gerarDps', 'data' => []])
    ];
});

$res = $client->emitir(['dadosNota' => []]);
nfseClientAssert($res['http_status'] === 200, 'status HTTP capturado');
nfseClientAssert($res['request_id'] === 'req-1', 'X-Request-Id capturado');
nfseClientAssert(strpos($calls[0]['url'], '/acoes/GeraDps.php') !== false, 'endpoint de emissão correto');
nfseClientAssert(in_array('Authorization: Bearer TOKEN_FICTICIO_INVALIDO', $calls[0]['headers'], true), 'Bearer enviado');
nfseClientAssert(in_array('Content-Type: application/json', $calls[0]['headers'], true), 'Content-Type JSON enviado');
nfseClientAssert($calls[0]['connect_timeout'] === 2 && $calls[0]['request_timeout'] === 5, 'timeouts enviados');

$timeout = new NfseApiClient(['base_url' => 'https://api.disparador.net', 'auth_token' => 'TOKEN_FICTICIO_INVALIDO'], function(){
    return ['transport_error' => true, 'timeout' => true, 'error_code' => 'curl_28', 'error_message' => 'timeout Bearer segredo', 'duration_ms' => 30];
});
$res = $timeout->emitir([]);
nfseClientAssert($res['transport_error'] === true && $res['timeout'] === true, 'timeout de transporte classificado');
nfseClientAssert(strpos($res['error_message'], 'segredo') === false, 'erro de transporte sanitizado');

$pdf = new NfseApiClient(['base_url' => 'https://api.disparador.net', 'auth_token' => 'TOKEN_FICTICIO_INVALIDO'], function(){
    return ['http_status' => 200, 'content_type' => 'application/pdf', 'body' => '%PDF-1.4 teste'];
});
nfseClientAssert($pdf->consultarPdf(['idNota' => 'abc'])['content_type'] === 'application/pdf', 'PDF binário capturado');

$xml = new NfseApiClient(['base_url' => 'https://api.disparador.net', 'auth_token' => 'TOKEN_FICTICIO_INVALIDO'], function(){
    return ['http_status' => 200, 'content_type' => 'application/xml', 'body' => '<?xml version="1.0"?><n/>'];
});
nfseClientAssert(strpos($xml->consultarXml(['idNota' => 'abc'])['body'], '<n/>') !== false, 'XML textual capturado');

try{
    (new NfseApiClient(['base_url' => '', 'auth_token' => '']))->emitir([]);
    nfseClientAssert(false, 'configuração ausente deveria falhar');
}catch(RuntimeException $e){
    nfseClientAssert(stripos($e->getMessage(), 'URL') !== false, 'erro seguro de configuração ausente');
}

echo "NFS-e API client tests passed\n";
