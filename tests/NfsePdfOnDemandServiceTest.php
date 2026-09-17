<?php
// No database or external API is used: dependencies below are explicit test doubles.
spl_autoload_register(function($class){
    $path = dirname(__DIR__) . '/app/' . str_replace('\\', '/', $class) . '.php';
    if(is_file($path)){ require_once $path; }
});

function pdfAssert($condition, $message){ if(!$condition){ throw new RuntimeException($message); } }
function pdfRejects(callable $action, $message){
    try{ $action(); }catch(InvalidArgumentException | RuntimeException $e){ return; }
    throw new RuntimeException($message);
}
function pdfFiles(){
    $base = dirname(__DIR__) . '/storage/nfse';
    $files = [];
    if(is_dir($base)){
        foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)) as $file){
            if($file->isFile()){ $files[$file->getPathname()] = hash_file('sha256', $file->getPathname()); }
        }
    }
    ksort($files);
    return $files;
}
class PdfTestEmissoes extends Models\NfseEmissao {
    public $path;
    public $xmlWrites = [];
    public $consultas = [];
    public function __construct(){}
    public function buscarPorId($id){ return ['NFE_ID' => $id, 'CLI_ID' => 12, 'COB_ID' => 34, 'NFE_ChaveAcesso' => str_repeat('1', 50)]; }
    public function arquivoPrivado($id, $tipo){
        pdfAssert($tipo === 'xml', 'PDF precisa ler o XML, nunca PDF legado');
        return $id === 7 ? ['path' => $this->path, 'CLI_ID' => 12, 'COB_ID' => 34] : false;
    }
    public function persistirArquivoXml($id, $path, $hash){ $this->xmlWrites[] = [$id, $path, $hash]; return true; }
    public function persistirArquivoPdf($id, $path, $hash){ throw new RuntimeException('PDF não pode ser persistido'); }
    public function persistirRequestConsulta($id, array $resultado, $tipo = 'consulta'){ $this->consultas[] = [$id, $resultado, $tipo]; return true; }
}
class PdfTestCobrancas extends Models\Cobranca {
    public $clienteId = 12;
    public function __construct(){}
    public function buscar($id){ return ['COB_ID' => $id, 'CLI_ID' => $this->clienteId]; }
}
class PdfTestBuilder extends Services\NfsePayloadBuilder {
    public function carregarSegredosCertificado(){ return ['cert' => 'FAKE_CERT', 'senhaCert' => 'FAKE_PASSWORD']; }
}
function pdfService($emissoes, $cobrancas, $client){
    return new Services\NfseEmissionService(
        $emissoes,
        (new ReflectionClass(Models\Cliente::class))->newInstanceWithoutConstructor(),
        $cobrancas,
        new Services\NfseAptidaoFiscalService(),
        (new ReflectionClass(Services\NfseDpsSequenciaService::class))->newInstanceWithoutConstructor(),
        new PdfTestBuilder(), $client, new Services\NfseApiResponseMapper()
    );
}

$root = dirname(__DIR__);
$dir = $root . '/storage/nfse/xml';
if(!is_dir($dir)){ mkdir($dir, 0770, true); }
$fixture = 'storage/nfse/xml/test-demand-' . bin2hex(random_bytes(8)) . '.xml';
$xml = '<?xml version="1.0"?><NFSe><infNFSe Id="NFS' . str_repeat('1', 50) . '"><nNFSe>17</nNFSe></infNFSe></NFSe>';
$pdf = "%PDF-1.4\nfixture\n%%EOF";
file_put_contents($root . '/' . $fixture, $xml);
$emissoes = new PdfTestEmissoes();
$emissoes->path = $fixture;
$cobrancas = new PdfTestCobrancas();
$calls = [];
$response = ['http_status' => 200, 'content_type' => 'application/pdf', 'body' => $pdf];
$client = new Services\NfseApiClient(['base_url' => 'https://nfse.invalid', 'auth_token' => 'FAKE_TOKEN'], function($request) use (&$calls, &$response, $xml){
    $calls[] = $request;
    pdfAssert($request['endpoint'] === '/acoes/GeraDanfse.php', 'geração não pode consultar XML nacional, emitir ou cancelar');
    $payload = json_decode($request['body'], true);
    pdfAssert(array_keys($payload) === ['nfseXmlGZipB64'], 'somente XML deve ser enviado');
    pdfAssert(gzdecode(base64_decode($payload['nfseXmlGZipB64'], true)) === $xml, 'gerador recebe XML armazenado íntegro');
    return $response;
});
$service = pdfService($emissoes, $cobrancas, $client);
$onDemand = new Services\NfsePdfOnDemandService($service, $client);
$owner = ['nivel' => 'cliente', 'CLI_ID' => 12];
$admin = ['nivel' => 'admin'];
try{
    $before = pdfFiles();
    foreach([$admin, $owner, ['nivel' => 'cliente_admin', 'CLI_ID' => 12], ['nivel' => 'cliente_usuario', 'CLI_ID' => 12]] as $usuario){
        $result = $onDemand->gerar(7, $usuario);
        pdfAssert($result === ['conteudo' => $pdf, 'filename' => 'nfse-7.pdf', 'content_type' => 'application/pdf'], 'PDF deve retornar bytes e metadados ao navegador');
    }
    pdfAssert(count($calls) === 4, 'cada clique gera novamente, sem cache de PDF');
    pdfAssert(pdfFiles() === $before && !$emissoes->xmlWrites && !$emissoes->consultas, 'geração não grava arquivos nem altera banco');
    foreach([[], ['nivel' => 'cliente', 'CLI_ID' => 99], ['nivel' => 'desconhecido', 'CLI_ID' => 12]] as $usuario){
        pdfRejects(fn() => $onDemand->gerar(7, $usuario), 'acesso indevido deveria falhar');
    }
    $cobrancas->clienteId = 99;
    pdfRejects(fn() => $onDemand->gerar(7, $owner), 'cobrança de outro cliente deveria falhar');
    $cobrancas->clienteId = 12;
    pdfRejects(fn() => $onDemand->gerar(999, $admin), 'nota inexistente deveria falhar');
    foreach(['', 'storage/nfse/xml/missing.xml', '../fora.xml'] as $path){
        $emissoes->path = $path;
        pdfRejects(fn() => $onDemand->gerar(7, $admin), 'XML ausente ou caminho inválido deveria falhar');
    }
    $outsideDir = $root . '/storage/nfse/xml-other-' . bin2hex(random_bytes(8));
    mkdir($outsideDir, 0770, true);
    file_put_contents($outsideDir . '/fixture.xml', $xml);
    try{
        $emissoes->path = 'storage/nfse/' . basename($outsideDir) . '/fixture.xml';
        pdfRejects(fn() => $onDemand->gerar(7, $admin), 'pasta com prefixo semelhante não pertence ao storage XML');
    }finally{
        unlink($outsideDir . '/fixture.xml');
        rmdir($outsideDir);
    }
    $emissoes->path = $fixture;
    file_put_contents($root . '/' . $fixture, '  ');
    pdfRejects(fn() => $onDemand->gerar(7, $admin), 'XML vazio deveria falhar');
    file_put_contents($root . '/' . $fixture, $xml);
    pdfAssert(count($calls) === 4, 'sem autorização ou XML não chama gerador');
    foreach([
        ['http_status' => 502, 'content_type' => 'application/json', 'body' => '{"success":false,"error":{"code":"UNAVAILABLE"}}'],
        ['http_status' => 200, 'content_type' => 'application/pdf', 'body' => 'not a PDF'],
        ['transport_error' => true, 'error_code' => 'timeout', 'error_message' => 'timeout']
    ] as $error){
        $response = $error;
        pdfRejects(fn() => $onDemand->gerar(7, $admin), 'erro do gerador não pode virar PDF');
        pdfAssert(pdfFiles() === $before, 'falha do gerador não persiste arquivo');
    }

    $reconsultaCalls = [];
    $xmlOk = true;
    $eventosOk = true;
    $consultaClient = new Services\NfseApiClient(['base_url' => 'https://nfse.invalid', 'auth_token' => 'FAKE_TOKEN'], function($request) use (&$reconsultaCalls, &$xmlOk, &$eventosOk, $xml){
        $reconsultaCalls[] = $request['endpoint'];
        if($request['endpoint'] === '/acoes/ConsultaNfseChave.php' && $xmlOk){ return ['http_status' => 200, 'content_type' => 'application/xml', 'body' => $xml]; }
        if($request['endpoint'] === '/acoes/ConsultaNfseEventos.php' && $eventosOk){ return ['http_status' => 200, 'content_type' => 'application/json', 'body' => '{"success":true,"data":{}}']; }
        pdfAssert(in_array($request['endpoint'], ['/acoes/ConsultaNfseChave.php', '/acoes/ConsultaNfseEventos.php'], true), 'reconsulta não pode gerar PDF, emitir ou cancelar');
        return ['http_status' => 502, 'content_type' => 'application/json', 'body' => '{"success":false,"error":{"code":"UNAVAILABLE"}}'];
    });
    $consulta = pdfService($emissoes, $cobrancas, $consultaClient);
    foreach([[true,true], [true,false], [false,true], [false,false]] as [$xmlOk, $eventosOk]){
        $reconsultaCalls = [];
        $writesBefore = count($emissoes->xmlWrites);
        $filesBefore = pdfFiles();
        $result = $consulta->reconsultarManual(7, $admin);
        pdfAssert($reconsultaCalls === ['/acoes/ConsultaNfseChave.php', '/acoes/ConsultaNfseEventos.php'], 'reconsulta chama somente XML e eventos');
        pdfAssert(array_keys($result) === ['sucesso', 'xml', 'eventos'] && $result['sucesso'] === ($xmlOk || $eventosOk), 'resultado reflete sucesso parcial sem PDF');
        pdfAssert(count($emissoes->xmlWrites) === $writesBefore + (int) $xmlOk, 'somente XML válido é persistido');
        $added = array_diff_key(pdfFiles(), $filesBefore);
        pdfAssert(count($added) === (int) $xmlOk, 'reconsulta cria somente o arquivo XML esperado');
        foreach($added as $path => $hash){ pdfAssert(substr($path, -4) === '.xml' && $hash === hash('sha256', $xml), 'reconsulta armazena apenas XML íntegro'); }
    }
    pdfAssert(count($emissoes->consultas) === 8, 'XML e eventos têm resultados de consulta registrados');
    $reconsultaCalls = [];
    pdfRejects(fn() => $consulta->reconsultarManual(7, $owner), 'reconsulta exige admin');
    pdfAssert(!$reconsultaCalls, 'reconsulta sem admin não chama API');
    pdfAssert(!method_exists(Services\NfseEmissionService::class, 'consultarPdfManual'), 'fluxo administrativo persistente removido');
    pdfAssert(!method_exists(Models\NfseEmissao::class, 'persistirArquivoPdf'), 'modelo não permite novas gravações de PDF');
}finally{
    foreach($emissoes->xmlWrites as $write){ if(is_file($root . '/' . $write[1])){ unlink($root . '/' . $write[1]); } }
    unlink($root . '/' . $fixture);
}
echo "NFS-e PDF on-demand and XML-only reconsultation tests passed\n";
