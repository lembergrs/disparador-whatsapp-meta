<?php

require_once __DIR__ . '/../app/Services/NfseSanitizer.php';

use Services\NfseSanitizer;

function nfseSanitizerAssert($cond, $msg){ if(!$cond){ fwrite(STDERR, "FAIL: {$msg}\n"); exit(1); } }

$dados = [
    'Authorization' => 'Bearer segredo-real',
    'nested' => [
        'senhaCert' => 'senha-real',
        'mensagem' => 'Bearer outro-segredo token=abc123 /tmp/certificado.pfx',
        'requestId' => 'req-123',
        'http_status' => 400
    ],
    'xml' => '<xml>conteudo</xml>'
];

$limpo = NfseSanitizer::dados($dados);
$json = json_encode($limpo);
nfseSanitizerAssert(strpos($json, 'segredo-real') === false, 'remove Authorization');
nfseSanitizerAssert(strpos($json, 'senha-real') === false, 'remove senhaCert');
nfseSanitizerAssert(strpos($json, 'outro-segredo') === false && strpos($json, 'abc123') === false, 'remove bearer/token em string');
nfseSanitizerAssert(strpos($json, '/tmp/certificado.pfx') === false, 'remove caminho sensível');
nfseSanitizerAssert(strpos($json, 'req-123') !== false && strpos($json, '400') !== false, 'preserva requestId e status HTTP');

echo "NFS-e sanitizer tests passed\n";
