<?php

require_once __DIR__ . '/../app/Services/NfseSanitizer.php';
require_once __DIR__ . '/../app/Services/NfseApiResponseMapper.php';

use Services\NfseApiResponseMapper;

function nfseMapperAssert($cond, $msg){ if(!$cond){ fwrite(STDERR, "FAIL: {$msg}\n"); exit(1); } }

$mapper = new NfseApiResponseMapper();
$sucesso = $mapper->mapearEmissao([
    'http_status' => 200,
    'content_type' => 'application/json',
    'request_id' => 'req-1',
    'duration_ms' => 12,
    'body' => json_encode(['success' => true, 'requestId' => 'req-1', 'operation' => 'nfse.gerarDps', 'data' => ['idDps' => 'id-1', 'chaveAcesso' => 'chave-1', 'nfseXmlGZipB64' => 'abc'], 'warnings' => ['w']])
]);
nfseMapperAssert($sucesso['sucesso'] === true, 'emissão com sucesso mapeada');
nfseMapperAssert($sucesso['id_dps'] === 'id-1' && $sucesso['chave_dps'] === null, 'não presume chaveDps');
nfseMapperAssert($sucesso['chave_acesso'] === 'chave-1', 'chaveAcesso mapeada');

$erro400 = $mapper->mapearEmissao([
    'http_status' => 400,
    'content_type' => 'application/json',
    'body' => json_encode(['success' => false, 'requestId' => 'req-2', 'operation' => 'nfse.gerarDps', 'error' => ['code' => 'VALIDACAO', 'message' => 'Campo inválido senhaCert=abc', 'details' => ['Authorization' => 'Bearer segredo']]])
]);
nfseMapperAssert($erro400['sucesso'] === false && $erro400['tipo_erro'] === 'definitivo', 'HTTP 400 definitivo');
nfseMapperAssert(strpos(json_encode($erro400), 'segredo') === false && strpos(json_encode($erro400), 'abc') === false, 'erro sanitizado');

$erro502 = $mapper->mapearEmissao(['http_status' => 502, 'body' => json_encode(['success' => false, 'error' => ['code' => 'EXTERNO', 'message' => 'Serviço externo indisponível']])]);
nfseMapperAssert($erro502['temporario'] === true, 'HTTP 502 temporário');

$incerto = $mapper->mapearEmissao(['transport_error' => true, 'timeout' => true, 'error_code' => 'curl_28', 'error_message' => 'timeout']);
nfseMapperAssert($incerto['incerto'] === true && $incerto['tipo_erro'] === 'incerto', 'timeout é incerto');

$pdf = $mapper->mapearPdf(['http_status' => 200, 'content_type' => 'application/pdf', 'body' => '%PDF-1.4']);
nfseMapperAssert($pdf['sucesso'] === true && $pdf['hash'] === hash('sha256', '%PDF-1.4'), 'PDF válido mapeado');
$badPdf = $mapper->mapearPdf(['http_status' => 200, 'content_type' => 'application/json', 'body' => json_encode(['success' => false, 'error' => ['message' => 'erro']])]);
nfseMapperAssert($badPdf['sucesso'] === false, 'JSON de erro não é PDF');

$xml = $mapper->mapearXml(['http_status' => 200, 'content_type' => 'application/xml', 'body' => '<?xml version="1.0"?><nota></nota>']);
nfseMapperAssert($xml['sucesso'] === true, 'XML válido mapeado');
$html = $mapper->mapearXml(['http_status' => 200, 'content_type' => 'text/html', 'body' => '<html></html>']);
nfseMapperAssert($html['sucesso'] === false, 'HTML não é XML fiscal');

echo "NFS-e response mapper tests passed\n";
