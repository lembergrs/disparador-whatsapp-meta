<?php

require_once __DIR__ . '/../app/Models/NfseEmissao.php';
require_once __DIR__ . '/../app/Models/Cliente.php';
require_once __DIR__ . '/../app/Models/Cobranca.php';
require_once __DIR__ . '/../app/Services/NfseAptidaoFiscalService.php';
require_once __DIR__ . '/../app/Services/NfseDpsSequenciaService.php';
require_once __DIR__ . '/../app/Services/NfseConfigService.php';
require_once __DIR__ . '/../app/Services/NfsePayloadBuilder.php';
require_once __DIR__ . '/../app/Services/NfseApiClient.php';
require_once __DIR__ . '/../app/Services/NfseApiResponseMapper.php';
require_once __DIR__ . '/../app/Services/NfseSanitizer.php';
require_once __DIR__ . '/../app/Services/NfseEmissionService.php';

use Models\NfseEmissao;
use Models\Cliente;
use Models\Cobranca;
use Services\NfseAptidaoFiscalService;
use Services\NfseDpsSequenciaService;
use Services\NfsePayloadBuilder;
use Services\NfseApiClient;
use Services\NfseApiResponseMapper;
use Services\NfseEmissionService;

function nfseEmissionServiceAssert($cond, $msg){ if(!$cond){ fwrite(STDERR, "FAIL: {$msg}\n"); exit(1); } }

class FakeClienteModel extends Cliente { public $cliente; public function __construct(){} public function buscar($id){ return $this->cliente; } }
class FakeCobrancaModel extends Cobranca { public $cobranca; public function __construct(){} public function buscar($id){ return $this->cobranca; } }
class FakeAptidao extends NfseAptidaoFiscalService { public $apto = true; public function validarCliente($cliente){ return $this->apto ? ['apto' => true, 'tipo_bloqueio' => null, 'campos_faltantes' => [], 'mensagem' => 'Apto'] : ['apto' => false, 'tipo_bloqueio' => 'dados_incompletos', 'campos_faltantes' => ['cnpj'], 'mensagem' => 'Não apto']; } }
class FakeSeq extends NfseDpsSequenciaService { public $reservas = 0; public function __construct(){} public function reservar($prestadorCnpj = null, $ambiente = null, $serie = null){ $this->reservas++; return '1'; } }
class FakeBuilder extends NfsePayloadBuilder { public function carregarSegredosCertificado(){ return ['cert' => 'CERTIFICADO_FICTICIO_BASE64', 'senhaCert' => 'SENHA_FICTICIA']; } public function montarEmissao(array $cliente, array $cobranca, array $emissao, array $segredos){ return ['cert' => $segredos['cert'], 'senhaCert' => $segredos['senhaCert'], 'dadosNota' => ['numDPS' => $emissao['NFE_NumDps'] ?? '1']]; } }
class FakeClient extends NfseApiClient { public $chamadas = 0; public function __construct(){} public function emitir(array $payload){ $this->chamadas++; return ['http_status' => 200, 'content_type' => 'application/json', 'body' => '{}']; } }
class FakeMapper extends NfseApiResponseMapper { public $resultado; public function mapearEmissao(array $http){ return $this->resultado ?: ['sucesso' => true, 'request_id' => 'req-1', 'id_dps' => 'id-1', 'chave_acesso' => 'chave-1', 'retorno_sanitizado' => ['success' => true]]; } }
class FakeEmissoes extends NfseEmissao {
    public $row; public $statusUpdates = []; public $sucesso = false; public $erro = false; public $numDps = false;
    public function __construct(){ $this->row = ['NFE_ID' => 7, 'CLI_ID' => 1, 'COB_ID' => 2, 'NFE_Status' => self::STATUS_PENDENTE_DADOS, 'NFE_NumDps' => null, 'NFE_ValorFiscal' => '10.00', 'NFE_Competencia' => '2026-07-15']; }
    public function criarOuBuscarPorCobranca(array $cobranca, array $opcoes = []){ return $this->row; }
    public function atualizarStatus($nfseId, $status, array $erro = [], $statusAtualEsperado = null){ $this->statusUpdates[] = [$status, $statusAtualEsperado]; $this->row['NFE_Status'] = $status; return true; }
    public function atribuirNumDps($nfseId, $numDps){ $this->numDps = true; $this->row['NFE_NumDps'] = $numDps; return true; }
    public function buscarPorId($nfseId){ return $this->row; }
    public function prepararProcessamento($nfseId, $statusAtualEsperado){ return $this->atualizarStatus($nfseId, self::STATUS_PROCESSANDO, [], $statusAtualEsperado); }
    public function persistirSucessoEmissao($nfseId, array $resultado){ $this->sucesso = true; $this->row['NFE_Status'] = self::STATUS_EMITIDA; return true; }
    public function persistirErroEmissao($nfseId, array $resultado, $statusAtualEsperado = self::STATUS_PROCESSANDO){ $this->erro = true; $this->row['NFE_Status'] = !empty($resultado['incerto']) ? self::STATUS_RECONCILIACAO_PENDENTE : self::STATUS_ERRO_DEFINITIVO; return true; }
}

$cliente = ['CLI_ID' => 1, 'CLI_TipoPessoa' => 'PJ'];
$cobranca = ['COB_ID' => 2, 'CLI_ID' => 1, 'COB_Valor' => '10.00'];

$emissoes = new FakeEmissoes(); $clientes = new FakeClienteModel(); $clientes->cliente = $cliente; $cobrancas = new FakeCobrancaModel(); $cobrancas->cobranca = $cobranca; $aptidao = new FakeAptidao(); $seq = new FakeSeq(); $builder = new FakeBuilder(); $client = new FakeClient(); $mapper = new FakeMapper();
$service = new NfseEmissionService($emissoes, $clientes, $cobrancas, $aptidao, $seq, $builder, $client, $mapper);
$res = $service->emitirManual(1, 2, ['nivel' => 'admin']);
nfseEmissionServiceAssert($res['sucesso'] === true, 'emissão manual sucesso');
nfseEmissionServiceAssert($seq->reservas === 1 && $emissoes->numDps === true, 'numDPS reservado somente no fluxo apto');
nfseEmissionServiceAssert($client->chamadas === 1, 'API chamada uma vez no sucesso');
nfseEmissionServiceAssert($emissoes->sucesso === true, 'sucesso persistido');

$emissoes2 = new FakeEmissoes(); $aptidao2 = new FakeAptidao(); $aptidao2->apto = false; $seq2 = new FakeSeq(); $client2 = new FakeClient();
$service2 = new NfseEmissionService($emissoes2, $clientes, $cobrancas, $aptidao2, $seq2, $builder, $client2, $mapper);
$res2 = $service2->emitirManual(1, 2, ['nivel' => 'admin']);
nfseEmissionServiceAssert($res2['tipo'] === 'cliente_nao_apto', 'cliente não apto bloqueado');
nfseEmissionServiceAssert($seq2->reservas === 0 && $client2->chamadas === 0, 'não apto não reserva numDPS nem chama API');

$emissoes3 = new FakeEmissoes(); $emissoes3->row['NFE_Status'] = NfseEmissao::STATUS_EMITIDA; $client3 = new FakeClient();
$service3 = new NfseEmissionService($emissoes3, $clientes, $cobrancas, $aptidao, $seq, $builder, $client3, $mapper);
$res3 = $service3->emitirManual(1, 2, ['nivel' => 'admin']);
nfseEmissionServiceAssert($res3['tipo'] === 'status_bloqueado' && $client3->chamadas === 0, 'emitida não é reenviada');

$emissoes4 = new FakeEmissoes(); $mapper4 = new FakeMapper(); $mapper4->resultado = ['sucesso' => false, 'incerto' => true, 'temporario' => false, 'tipo_erro' => 'incerto', 'error_code' => 'timeout', 'error_message' => 'timeout'];
$service4 = new NfseEmissionService($emissoes4, $clientes, $cobrancas, $aptidao, new FakeSeq(), $builder, new FakeClient(), $mapper4);
$res4 = $service4->emitirManual(1, 2, ['nivel' => 'admin']);
nfseEmissionServiceAssert($res4['tipo'] === 'incerto' && $emissoes4->row['NFE_Status'] === NfseEmissao::STATUS_RECONCILIACAO_PENDENTE, 'timeout incerto vira reconciliação pendente');

echo "NFS-e emission service tests passed\n";
