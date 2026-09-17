<?php
// All fiscal requests and database writes are simulated.
spl_autoload_register(function($class){
    $path = dirname(__DIR__) . '/app/' . str_replace('\\', '/', $class) . '.php';
    if(is_file($path)){ require_once $path; }
});
define('NFSE_PRESTADOR_CNPJ', '11534763000139');
define('NFSE_AMBIENTE', 'sandbox');
function externalAssert($ok, $message){ if(!$ok){ throw new RuntimeException($message); } }
function externalRejects(callable $action, $fragment){
    try{ $action(); }catch(RuntimeException | InvalidArgumentException $e){
        externalAssert(strpos($e->getMessage(), $fragment) !== false, 'falha esperada: ' . $fragment . '; recebido: ' . $e->getMessage());
        return;
    }
    throw new RuntimeException('operação deveria falhar: ' . $fragment);
}
class ExternalTestEmissoes extends Models\NfseEmissao {
    public $status = self::STATUS_ERRO_TEMPORARIO;
    public function __construct(){}
    public function buscarPorId($id){ return ['NFE_ID' => $id, 'NFE_Status' => $this->status, 'NFE_EmissaoAtiva' => 1, 'CLI_ID' => 12, 'COB_ID' => 34]; }
}
class ExternalTestClientes extends Models\Cliente {
    public $fiscal = '';
    public function __construct(){}
    public function buscar($id){ return ['CLI_ID' => $id, 'CLI_NFSe_CNPJ' => $this->fiscal, 'CLI_CPF_CNPJ' => '22.222.222/0001-22']; }
}
class ExternalTestCobrancas extends Models\Cobranca {
    public function __construct(){}
    public function buscar($id){ return ['COB_ID' => $id, 'CLI_ID' => 12, 'COB_Status' => 'pago', 'COB_Valor' => '10.00']; }
}
class ExternalTestBuilder extends Services\NfsePayloadBuilder {
    public function carregarSegredosCertificado(){ return ['cert' => 'FAKE', 'senhaCert' => 'FAKE']; }
}
class ExternalTestRegistro extends Models\NfseReconciliacaoExterna {
    public $notas = [];
    public $fail = false;
    public function __construct(){}
    public function registrar(array $tentativa, array $cobranca, array $nota){
        $this->notas[] = $nota;
        $path = dirname(__DIR__) . '/' . $nota['xml_path'];
        externalAssert(is_file($path) && hash_file('sha256', $path) === $nota['xml_hash'], 'XML deve existir antes de confirmar o registro');
        externalAssert(!isset($nota['pdf_path']) && $nota['ambiente'] === 'sandbox', 'registro usa ambiente configurado e não tem PDF');
        if($this->fail){ throw new RuntimeException('falha simulada de transação'); }
        return 8;
    }
}
$chave = str_repeat('1', 50);
function externalXml($chave, $prestador = '11534763000139', $tomador = '22222222000122'){
    return '<?xml version="1.0"?><NFSe><infNFSe Id="NFS' . $chave . '"><nNFSe>17</nNFSe><DPS><infDPS><nDPS>35</nDPS><serie>900</serie><dhEmi>2026-09-17T10:00:00-03:00</dhEmi><dCompet>2026-09-17</dCompet><prest><CNPJ>' . $prestador . '</CNPJ></prest><toma><CNPJ>' . $tomador . '</CNPJ></toma><valores><vServPrest>10.00</vServPrest></valores></infDPS></DPS></infNFSe></NFSe>';
}
$xml = externalXml($chave);
$calls = [];
$client = new Services\NfseApiClient(['base_url' => 'https://nfse.invalid', 'auth_token' => 'FAKE'], function($request) use (&$calls, &$xml){
    $calls[] = $request['endpoint'];
    externalAssert($request['endpoint'] === '/acoes/ConsultaNfseChave.php', 'reconciliação consulta apenas XML, sem PDF/emissão/cancelamento');
    return ['http_status' => 200, 'content_type' => 'application/xml', 'body' => $xml];
});
$emissoes = new ExternalTestEmissoes();
$clientes = new ExternalTestClientes();
$registro = new ExternalTestRegistro();
$service = new Services\NfseExternalReconciliationService($emissoes, $clientes, new ExternalTestCobrancas(), $registro, new ExternalTestBuilder(), $client);
$admin = ['nivel' => 'admin'];
try{
    foreach(['processando', 'pendente', 'pendente_dados', 'reconciliacao_pendente', 'emitida', 'cancelada', 'cancelamento_pendente'] as $status){
        $emissoes->status = $status;
        externalRejects(fn() => $service->registrar(7, $chave, $admin), 'não pode ser substituído');
    }
    externalAssert(!$calls && !$registro->notas, 'status bloqueados não consultam API nem alteram registro');
    $emissoes->status = 'erro_temporario';
    foreach(['', '  ', null] as $fiscal){
        $clientes->fiscal = $fiscal;
        $xml = externalXml($chave, '11534763000139', '33333333000133');
        externalRejects(fn() => $service->registrar(7, $chave, $admin), 'tomador');
    }
    externalAssert(!$registro->notas, 'documento fiscal vazio usa cadastro e rejeita outro tomador');
    $xml = externalXml($chave, '22222222000122', '11534763000139');
    externalRejects(fn() => $service->registrar(7, $chave, $admin), 'prestador');
    $clientes->fiscal = '33.333.333/0001-33';
    $xml = externalXml($chave);
    externalRejects(fn() => $service->registrar(7, $chave, $admin), 'tomador');
    $clientes->fiscal = '';
    foreach(['erro_temporario', 'erro_definitivo'] as $status){
        $emissoes->status = $status;
        $result = $service->registrar(7, $chave, $admin);
        externalAssert($result['sucesso'] && $result['nfse_id'] === 8, 'tentativa com erro e XML válido permite reconciliação');
    }
    externalAssert(count($registro->notas) === 2, 'somente XML validado chega à transação');
    $registro->fail = true;
    externalRejects(fn() => $service->registrar(7, $chave, $admin), 'falha simulada');
    $ultima = end($registro->notas);
    externalAssert(!is_file(dirname(__DIR__) . '/' . $ultima['xml_path']), 'falha de transação remove XML órfão');
}finally{
    foreach($registro->notas as $nota){ $path = dirname(__DIR__) . '/' . $nota['xml_path']; if(is_file($path)){ unlink($path); } }
}
echo "NFS-e external reconciliation service tests passed\n";
