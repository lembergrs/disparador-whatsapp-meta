<?php

/**
 * Diagnóstico SOMENTE LEITURA para reconciliação de NFS-e externa.
 *
 * Uso:
 *   php scripts/nfse_diagnostico_externa.php <NFE_ID> <CHAVE_50_DIGITOS>
 *
 * Não grava banco, não salva XML/PDF e não emite/cancela NFS-e.
 * Apenas lê tentativa/cliente/cobrança, consulta o XML oficial e executa
 * as mesmas validações usadas pela reconciliação.
 */

if(PHP_SAPI !== 'cli'){
    fwrite(STDERR, "Este diagnóstico só pode ser executado via CLI.\n");
    exit(1);
}

$root = dirname(__DIR__);
require_once $root . '/config/env.php';
require_once $root . '/config/config.php';

if(file_exists($root . '/vendor/autoload.php')){
    require_once $root . '/vendor/autoload.php';
}

spl_autoload_register(function($class) use ($root){
    $file = $root . '/app/' . str_replace('\\', '/', $class) . '.php';
    if(is_file($file)){
        require_once $file;
    }
});

function nfseDiagnosticoResumoResposta(array $http)
{
    $body = (string) ($http['body'] ?? '');
    $trimmed = ltrim($body, "\xEF\xBB\xBF\x00\x09\x0A\x0D\x20");
    $primeiros = substr($trimmed, 0, 160);
    $primeiros = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '?', $primeiros);
    $primeiros = preg_replace('/\s+/', ' ', $primeiros);

    $domValido = false;
    if($trimmed !== ''){
        $dom = new DOMDocument();
        $anterior = libxml_use_internal_errors(true);
        $domValido = $dom->loadXML($trimmed, LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($anterior);
    }

    $jsonValido = false;
    $jsonTipo = '';
    if($trimmed !== ''){
        $json = json_decode($trimmed, true);
        $jsonValido = json_last_error() === JSON_ERROR_NONE;
        if($jsonValido){
            $jsonTipo = is_array($json) ? 'array/object' : gettype($json);
        }
    }

    fwrite(STDERR, "--- RESUMO SEGURO DA RESPOSTA ---\n");
    fwrite(STDERR, 'HTTP status: ' . (int) ($http['http_status'] ?? 0) . "\n");
    fwrite(STDERR, 'Content-Type: ' . (string) ($http['content_type'] ?? '') . "\n");
    fwrite(STDERR, 'Tamanho: ' . strlen($body) . " bytes\n");
    fwrite(STDERR, 'Transport error: ' . (!empty($http['transport_error']) ? 'sim' : 'nao') . "\n");
    fwrite(STDERR, 'DOMDocument XML valido: ' . ($domValido ? 'sim' : 'nao') . "\n");
    fwrite(STDERR, 'JSON valido: ' . ($jsonValido ? 'sim (' . $jsonTipo . ')' : 'nao') . "\n");
    fwrite(STDERR, 'Inicio sanitizado: ' . ($primeiros !== '' ? $primeiros : '[vazio]') . "\n");
    fwrite(STDERR, "--- FIM DO RESUMO ---\n");
}

$nfseId = isset($argv[1]) ? (int) $argv[1] : 0;
$chave = preg_replace('/\D/', '', (string) ($argv[2] ?? ''));
if($nfseId <= 0 || strlen($chave) !== 50){
    fwrite(STDERR, "Uso: php scripts/nfse_diagnostico_externa.php <NFE_ID> <CHAVE_50_DIGITOS>\n");
    exit(2);
}

try{
    $emissoes = new Models\NfseEmissao();
    $clientes = new Models\Cliente();
    $cobrancas = new Models\Cobranca();
    $builder = new Services\NfsePayloadBuilder();
    $client = new Services\NfseApiClient();
    $mapper = new Services\NfseApiResponseMapper();

    $tentativa = $emissoes->buscarPorId($nfseId);
    if(!$tentativa){
        throw new RuntimeException('Tentativa fiscal não encontrada.');
    }
    if((int) ($tentativa['NFE_EmissaoAtiva'] ?? 0) !== 1){
        throw new RuntimeException('A tentativa fiscal não está ativa.');
    }

    $cliente = $clientes->buscar((int) ($tentativa['CLI_ID'] ?? 0));
    $cobranca = $cobrancas->buscar((int) ($tentativa['COB_ID'] ?? 0));
    if(!$cliente || !$cobranca){
        throw new RuntimeException('Cliente ou cobrança vinculada não encontrado.');
    }
    if((int) ($cobranca['CLI_ID'] ?? 0) !== (int) ($tentativa['CLI_ID'] ?? 0)){
        throw new RuntimeException('A cobrança não pertence ao cliente da tentativa fiscal.');
    }

    $segredos = $builder->carregarSegredosCertificado();
    $http = $client->consultarXml([
        'cert' => $segredos['cert'],
        'senhaCert' => $segredos['senhaCert'],
        'idNota' => $chave
    ]);
    $resultado = $mapper->mapearXml($http);
    if(empty($resultado['sucesso']) || empty($resultado['conteudo'])){
        nfseDiagnosticoResumoResposta($http);
        throw new RuntimeException($resultado['error_message'] ?? 'A NFS-e não pôde ser confirmada no ambiente nacional.');
    }

    $service = new Services\NfseExternalReconciliationService();
    $reflection = new ReflectionMethod($service, 'extrairEValidar');
    $reflection->setAccessible(true);
    $nota = $reflection->invoke($service, (string) $resultado['conteudo'], $chave, $cliente, $cobranca);

    echo "DIAGNOSTICO OK - nenhuma alteracao foi realizada.\n";
    echo 'NFE_ID: ' . $nfseId . "\n";
    echo 'CLI_ID: ' . (int) ($tentativa['CLI_ID'] ?? 0) . "\n";
    echo 'COB_ID: ' . (int) ($tentativa['COB_ID'] ?? 0) . "\n";
    echo 'Status tentativa: ' . (string) ($tentativa['NFE_Status'] ?? '') . "\n";
    echo 'Chave: ' . $nota['chave_acesso'] . "\n";
    echo 'Numero NFS-e: ' . $nota['numero_nfse'] . "\n";
    echo 'DPS: ' . $nota['num_dps'] . "\n";
    echo 'Serie: ' . $nota['serie'] . "\n";
    echo 'Data emissao: ' . $nota['data_emissao'] . "\n";
    echo 'Competencia: ' . $nota['competencia'] . "\n";
    echo 'Valor oficial: ' . number_format((float) $nota['valor'], 2, '.', '') . "\n";
    echo 'Valor cobranca: ' . number_format((float) ($cobranca['COB_Valor'] ?? 0), 2, '.', '') . "\n";
    echo 'Prestador CNPJ: ' . $nota['prestador_cnpj'] . "\n";
    echo 'Codigo tributacao: ' . (string) ($nota['codigo_tributacao'] ?? '') . "\n";
    echo 'Descricao servico: ' . (string) ($nota['descricao_servico'] ?? '') . "\n";
    exit(0);
}catch(Throwable $e){
    fwrite(STDERR, 'DIAGNOSTICO FALHOU: ' . $e->getMessage() . "\n");
    fwrite(STDERR, "Nenhuma alteracao de reconciliacao foi realizada por este script.\n");
    exit(1);
}
