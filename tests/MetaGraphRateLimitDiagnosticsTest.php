<?php

require_once __DIR__ . '/../app/Core/Controller.php';
require_once __DIR__ . '/../app/Controllers/ConfiguracaoController.php';

use Controllers\ConfiguracaoController;

function rateLimitAssert($condition, $message){ if(!$condition){ fwrite(STDERR, "FAIL: {$message}\n"); exit(1); } }

$reflection = new ReflectionClass(ConfiguracaoController::class);
$controller = $reflection->newInstanceWithoutConstructor();
$capture = $reflection->getMethod('captureMetaRateLimitHeader');
$capture->setAccessible(true);
$diagnostic = $reflection->getMethod('buildMetaRateLimitDiagnostic');
$diagnostic->setAccessible(true);

$headers = [];
foreach([
    "x-app-usage: {\"call_count\":95,\"access_token\":\"header-secret\"}\r\n",
    "X-BUSINESS-USE-CASE-USAGE: {\"123\":[{\"type\":\"ads_management\",\"call_count\":80}]}\r\n",
    "Retry-After: 120\r\n",
    "Authorization: Bearer secret-token\r\n",
    "Set-Cookie: access_token=cookie-secret\r\n",
    "X-Unknown: unknown-secret\r\n"
] as $line){
    $args = [&$headers, $line];
    $capture->invokeArgs($controller, $args);
}

rateLimitAssert($headers['X-App-Usage'] === '{"call_count":95}', 'captura X-App-Usage');
rateLimitAssert(isset($headers['X-Business-Use-Case-Usage']), 'captura X-Business-Use-Case-Usage');
rateLimitAssert($headers['Retry-After'] === '120', 'captura Retry-After');
rateLimitAssert(count($headers) === 3, 'ignora Authorization, Set-Cookie e headers desconhecidos');

$entry = $diagnostic->invoke($controller, '123/messages?access_token=top-secret&appsecret_proof=proof', 400, [
    'error'=>['code'=>4,'message'=>'Application request limit reached']
], $headers);
rateLimitAssert($entry['etapa'] === 'meta_graph_rate_limit' && $entry['meta_error_code'] === 4, 'erro #4 gera diagnóstico');
rateLimitAssert($entry['endpoint'] === '123/messages', 'endpoint não contém query secrets');
$encoded = json_encode($entry);
rateLimitAssert(strpos($encoded, 'top-secret') === false && strpos($encoded, 'secret-token') === false && strpos($encoded, 'cookie-secret') === false && strpos($encoded, 'header-secret') === false, 'diagnóstico não contém tokens');

$noDiagnostic = $diagnostic->invoke($controller, '123', 400, ['error'=>['code'=>100,'message'=>'Invalid parameter']], []);
rateLimitAssert($noDiagnostic === null, 'erro comum sem headers não gera diagnóstico de rate limit');

$source = file_get_contents(__DIR__ . '/../app/Controllers/ConfiguracaoController.php');
rateLimitAssert(strpos($source, 'CURLOPT_HEADERFUNCTION') !== false, 'graphRequest instala callback de headers');
rateLimitAssert(strpos($source, "if(\$httpCode >= 400 || isset(\$json['error']))") !== false, 'tratamento de erro existente permanece');
rateLimitAssert(strpos($source, "return \$json;") !== false, 'retorno de sucesso permanece inalterado');
rateLimitAssert(strpos($source, "throw new Exception('Erro da Meta HTTP '") !== false, 'exceção existente permanece');

echo "Meta Graph rate-limit diagnostic tests passed\n";
