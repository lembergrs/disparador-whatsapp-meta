<?php

if(!defined('NFSE_CODIGO_TRIBUTACAO_NACIONAL')) define('NFSE_CODIGO_TRIBUTACAO_NACIONAL', '01.02.03');
if(!defined('NFSE_DESCRICAO_SERVICO')) define('NFSE_DESCRICAO_SERVICO', 'Licenciamento de uso da plataforma');
if(!defined('NFSE_PRESTADOR_CNPJ')) define('NFSE_PRESTADOR_CNPJ', '11.534.763/0001-39');
if(!defined('NFSE_AMBIENTE')) define('NFSE_AMBIENTE', 'production');
if(!defined('NFSE_DPS_SERIE')) define('NFSE_DPS_SERIE', '900');

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
class FakeSeq extends NfseDpsSequenciaService { public $reservas = 0; public $lastArgs = []; public function __construct(){} public function reservar($prestadorCnpj = null, $ambiente = null, $serie = null){ $this->reservas++; $this->lastArgs = [$prestadorCnpj, $ambiente, $serie]; return (string) $this->reservas; } }
class FakeBuilderIncompleto extends NfsePayloadBuilder { public function validarParametrosFiscaisConfigurados(array $emissao = []){ throw new \RuntimeException('Configuração fiscal de NFS-e incompleta: código tributário.'); } public function carregarSegredosCertificado(){ throw new \RuntimeException('não deveria ler certificado'); } public function montarEmissao(array $cliente, array $cobranca, array $emissao, array $segredos){ throw new \RuntimeException('não deveria montar payload'); } }
class FakeBuilder extends NfsePayloadBuilder { public function carregarSegredosCertificado(){ return ['cert' => 'CERTIFICADO_FICTICIO_BASE64', 'senhaCert' => 'SENHA_FICTICIA']; } public function montarEmissao(array $cliente, array $cobranca, array $emissao, array $segredos){ return ['cert' => $segredos['cert'], 'senhaCert' => $segredos['senhaCert'], 'dadosNota' => ['numDPS' => $emissao['NFE_NumDps'] ?? '1']]; } }
class FakeClient extends NfseApiClient { public $chamadas = 0; public function __construct(){} public function emitir(array $payload){ $this->chamadas++; return ['http_status' => 200, 'content_type' => 'application/json', 'body' => '{}']; } }
class FakeMapper extends NfseApiResponseMapper { public $resultado; public function mapearEmissao(array $http){ return $this->resultado ?: ['sucesso' => true, 'request_id' => 'req-1', 'id_dps' => 'id-1', 'chave_acesso' => 'chave-1', 'retorno_sanitizado' => ['success' => true]]; } }
class FakeEmissoes extends NfseEmissao {
    public $row; public $statusUpdates = []; public $sucesso = false; public $erro = false; public $numDps = false; public $contextosPreparados = 0;
    public function __construct(){ $this->row = ['NFE_ID' => 7, 'CLI_ID' => 1, 'COB_ID' => 2, 'NFE_Status' => self::STATUS_PENDENTE_DADOS, 'NFE_NumDps' => null, 'NFE_ValorFiscal' => '10.00', 'NFE_Competencia' => '2026-07-15', 'NFE_PrestadorCnpj' => '11534763000139', 'NFE_Ambiente' => 'production', 'NFE_Serie' => '900', 'NFE_CodigoTributacaoNacional' => null, 'NFE_DescricaoServicoSnapshot' => null]; }
    public function criarOuBuscarPorCobranca(array $cobranca, array $opcoes = []){ return $this->row; }
    public function atualizarStatus($nfseId, $status, array $erro = [], $statusAtualEsperado = null){ $this->statusUpdates[] = [$status, $statusAtualEsperado]; $this->row['NFE_Status'] = $status; return true; }
    public function prepararContextoFiscalAntesDaReserva($nfseId, $prestadorCnpj, $ambiente, $serie){ $this->contextosPreparados++; if(empty($this->row['NFE_NumDps']) && empty($this->row['NFE_RequestIdEmissao']) && !in_array($this->row['NFE_Status'], [self::STATUS_EMITIDA, self::STATUS_CANCELADA, self::STATUS_RECONCILIACAO_PENDENTE], true)){ if(empty($this->row['NFE_PrestadorCnpj']) || strlen(preg_replace('/\D/', '', $this->row['NFE_PrestadorCnpj'])) !== 14){ $this->row['NFE_PrestadorCnpj'] = preg_replace('/\D/', '', $prestadorCnpj); } if(empty($this->row['NFE_Ambiente']) || !in_array($this->row['NFE_Ambiente'], ['production','sandbox','homologation','local'], true)){ $this->row['NFE_Ambiente'] = $ambiente; } if(empty($this->row['NFE_Serie'])){ $this->row['NFE_Serie'] = $serie; } } return true; }
    public function prepararSnapshotFiscal($nfseId, $codigoTributacao, $descricaoServico){ if(empty($this->row['NFE_CodigoTributacaoNacional'])){ $this->row['NFE_CodigoTributacaoNacional'] = $codigoTributacao; } if(empty($this->row['NFE_DescricaoServicoSnapshot'])){ $this->row['NFE_DescricaoServicoSnapshot'] = $descricaoServico; } return true; }
    public function atribuirNumDps($nfseId, $numDps){ $this->numDps = true; $this->row['NFE_NumDps'] = $numDps; return true; }
    public function buscarPorId($nfseId){ return $this->row; }
    public function prepararProcessamento($nfseId, $statusAtualEsperado){ return $this->atualizarStatus($nfseId, self::STATUS_PROCESSANDO, [], $statusAtualEsperado); }
    public function persistirSucessoEmissao($nfseId, array $resultado){ $this->sucesso = true; $this->row['NFE_Status'] = self::STATUS_EMITIDA; return true; }
    public function persistirErroEmissao($nfseId, array $resultado, $statusAtualEsperado = self::STATUS_PROCESSANDO){ $this->erro = true; $this->row['NFE_Status'] = !empty($resultado['incerto']) ? self::STATUS_RECONCILIACAO_PENDENTE : self::STATUS_ERRO_DEFINITIVO; return true; }
}

$cliente = ['CLI_ID' => 1, 'CLI_TipoPessoa' => 'PJ'];
$cobranca = ['COB_ID' => 2, 'CLI_ID' => 1, 'COB_Valor' => '10.00', 'COB_Status' => 'pago'];

$emissoes = new FakeEmissoes(); $clientes = new FakeClienteModel(); $clientes->cliente = $cliente; $cobrancas = new FakeCobrancaModel(); $cobrancas->cobranca = $cobranca; $aptidao = new FakeAptidao(); $seq = new FakeSeq(); $builder = new FakeBuilder(); $client = new FakeClient(); $mapper = new FakeMapper();
$service = new NfseEmissionService($emissoes, $clientes, $cobrancas, $aptidao, $seq, $builder, $client, $mapper);
$res = $service->emitirManual(1, 2, ['nivel' => 'admin']);
nfseEmissionServiceAssert($res['sucesso'] === true, 'emissão manual sucesso');
nfseEmissionServiceAssert($seq->reservas === 1 && $emissoes->numDps === true, 'numDPS reservado somente no fluxo apto');
nfseEmissionServiceAssert($seq->lastArgs === ['11534763000139', 'production', '900'], 'numDPS usa contexto fiscal persistido na emissão production');
nfseEmissionServiceAssert($client->chamadas === 1, 'API chamada uma vez no sucesso');
nfseEmissionServiceAssert($emissoes->sucesso === true, 'sucesso persistido');

$emissoesConfig = new FakeEmissoes(); $seqConfig = new FakeSeq(); $clientConfig = new FakeClient();
$serviceConfig = new NfseEmissionService($emissoesConfig, $clientes, $cobrancas, $aptidao, $seqConfig, new FakeBuilderIncompleto(), $clientConfig, $mapper);
$resConfig = $serviceConfig->emitirManual(1, 2, ['nivel' => 'admin']);
nfseEmissionServiceAssert($resConfig['tipo'] === 'configuracao_fiscal_incompleta', 'configuração fiscal incompleta bloqueia emissão');
nfseEmissionServiceAssert($seqConfig->reservas === 0 && $clientConfig->chamadas === 0 && $emissoesConfig->row['NFE_Status'] === NfseEmissao::STATUS_PENDENTE_DADOS, 'configuração incompleta não reserva numDPS, não chama API e mantém estado coerente');

$emissoes2 = new FakeEmissoes(); $aptidao2 = new FakeAptidao(); $aptidao2->apto = false; $seq2 = new FakeSeq(); $client2 = new FakeClient();
$service2 = new NfseEmissionService($emissoes2, $clientes, $cobrancas, $aptidao2, $seq2, $builder, $client2, $mapper);
$res2 = $service2->emitirManual(1, 2, ['nivel' => 'admin']);
nfseEmissionServiceAssert($res2['tipo'] === 'cliente_nao_apto', 'cliente não apto bloqueado');
nfseEmissionServiceAssert($seq2->reservas === 0 && $client2->chamadas === 0, 'não apto não reserva numDPS nem chama API');

$emissoes3 = new FakeEmissoes(); $emissoes3->row['NFE_Status'] = NfseEmissao::STATUS_EMITIDA; $client3 = new FakeClient();
$service3 = new NfseEmissionService($emissoes3, $clientes, $cobrancas, $aptidao, $seq, $builder, $client3, $mapper);
$res3 = $service3->emitirManual(1, 2, ['nivel' => 'admin']);
nfseEmissionServiceAssert($res3['tipo'] === 'status_bloqueado' && $client3->chamadas === 0, 'emitida não é reenviada');

$emissoesProcessando = new FakeEmissoes(); $emissoesProcessando->row['NFE_Status'] = NfseEmissao::STATUS_PROCESSANDO; $clientProcessando = new FakeClient();
$serviceProcessando = new NfseEmissionService($emissoesProcessando, $clientes, $cobrancas, $aptidao, $seq, $builder, $clientProcessando, $mapper);
$resProcessando = $serviceProcessando->emitirManual(1, 2, ['nivel' => 'admin']);
nfseEmissionServiceAssert($resProcessando['tipo'] === 'status_bloqueado' && $clientProcessando->chamadas === 0, 'processando não é reenviado');

nfseEmissionServiceAssert(NfseEmissao::transicaoPermitida(NfseEmissao::STATUS_CANCELADA, NfseEmissao::STATUS_PENDENTE) === false, 'reativação de cancelada bloqueada');

$emissoes4 = new FakeEmissoes(); $mapper4 = new FakeMapper(); $mapper4->resultado = ['sucesso' => false, 'incerto' => true, 'temporario' => false, 'tipo_erro' => 'incerto', 'error_code' => 'timeout', 'error_message' => 'timeout'];
$service4 = new NfseEmissionService($emissoes4, $clientes, $cobrancas, $aptidao, new FakeSeq(), $builder, new FakeClient(), $mapper4);
$res4 = $service4->emitirManual(1, 2, ['nivel' => 'admin']);
nfseEmissionServiceAssert($res4['tipo'] === 'incerto' && $emissoes4->row['NFE_Status'] === NfseEmissao::STATUS_RECONCILIACAO_PENDENTE, 'timeout incerto vira reconciliação pendente');


$emissoesReparo = new FakeEmissoes();
$emissoesReparo->row['NFE_Status'] = NfseEmissao::STATUS_ERRO_DEFINITIVO;
$emissoesReparo->row['NFE_PrestadorCnpj'] = '';
$emissoesReparo->row['NFE_Ambiente'] = 'sandbox';
$emissoesReparo->row['NFE_CodigoTributacaoNacional'] = '99.88.77';
$emissoesReparo->row['NFE_DescricaoServicoSnapshot'] = 'Descrição snapshot original';
$seqReparo = new FakeSeq();
$serviceReparo = new NfseEmissionService($emissoesReparo, $clientes, $cobrancas, $aptidao, $seqReparo, $builder, new FakeClient(), $mapper);
$resReparo = $serviceReparo->emitirManual(1, 2, ['nivel' => 'admin']);
nfseEmissionServiceAssert($resReparo['sucesso'] === true && $seqReparo->lastArgs === ['11534763000139', 'sandbox', '900'], 'emissão ativa sem numDPS/request tem CNPJ reparado e reserva com contexto seguro');
nfseEmissionServiceAssert($emissoesReparo->row['NFE_CodigoTributacaoNacional'] === '99.88.77' && $emissoesReparo->row['NFE_DescricaoServicoSnapshot'] === 'Descrição snapshot original', 'reparo não sobrescreve código nem descrição do snapshot');

$emissoesComDps = new FakeEmissoes();
$emissoesComDps->row['NFE_NumDps'] = '32';
$emissoesComDps->row['NFE_PrestadorCnpj'] = '';
$serviceComDps = new NfseEmissionService($emissoesComDps, $clientes, $cobrancas, $aptidao, new FakeSeq(), $builder, new FakeClient(), $mapper);
$resComDps = $serviceComDps->emitirManual(1, 2, ['nivel' => 'admin']);
nfseEmissionServiceAssert($emissoesComDps->row['NFE_PrestadorCnpj'] === '', 'emissão com numDPS não tem contexto alterado pelo reparo seguro');

$emissoesSemReparoSeguro = new FakeEmissoes();
$emissoesSemReparoSeguro->row['NFE_PrestadorCnpj'] = '';
$emissoesSemReparoSeguro->row['NFE_RequestIdEmissao'] = 'req-ja-iniciado';
$seqSemReparoSeguro = new FakeSeq();
$clientSemReparoSeguro = new FakeClient();
$serviceSemReparoSeguro = new NfseEmissionService($emissoesSemReparoSeguro, $clientes, $cobrancas, $aptidao, $seqSemReparoSeguro, $builder, $clientSemReparoSeguro, $mapper);
$resSemReparoSeguro = $serviceSemReparoSeguro->emitirManual(1, 2, ['nivel' => 'admin']);
nfseEmissionServiceAssert($resSemReparoSeguro['sucesso'] === false && $seqSemReparoSeguro->reservas === 0 && $clientSemReparoSeguro->chamadas === 0, 'contexto inválido não reparável falha sem consumir sequência e sem API');

$emissoesCanceladaProtegida = new FakeEmissoes();
$emissoesCanceladaProtegida->row['NFE_Status'] = NfseEmissao::STATUS_CANCELADA;
$emissoesCanceladaProtegida->row['NFE_PrestadorCnpj'] = '';
$serviceCanceladaProtegida = new NfseEmissionService($emissoesCanceladaProtegida, $clientes, $cobrancas, $aptidao, new FakeSeq(), $builder, new FakeClient(), $mapper);
$resCanceladaProtegida = $serviceCanceladaProtegida->emitirManual(1, 2, ['nivel' => 'admin']);
nfseEmissionServiceAssert($resCanceladaProtegida['tipo'] === 'status_bloqueado' && $emissoesCanceladaProtegida->row['NFE_PrestadorCnpj'] === '', 'cancelada não tem contexto reparado nem reativado');

$emissoesErroDefinitivoAtivo = new FakeEmissoes();
$emissoesErroDefinitivoAtivo->row['NFE_Status'] = NfseEmissao::STATUS_ERRO_DEFINITIVO;
$emissoesErroDefinitivoAtivo->row['NFE_NumDps'] = '1';
$emissoesErroDefinitivoAtivo->row['NFE_RequestIdEmissao'] = null;
$seqErroDefinitivoAtivo = new FakeSeq();
$clientErroDefinitivoAtivo = new FakeClient();
$serviceErroDefinitivoAtivo = new NfseEmissionService($emissoesErroDefinitivoAtivo, $clientes, $cobrancas, $aptidao, $seqErroDefinitivoAtivo, $builder, $clientErroDefinitivoAtivo, $mapper);
$resErroDefinitivoAtivo = $serviceErroDefinitivoAtivo->emitirManual(1, 2, ['nivel' => 'admin']);
nfseEmissionServiceAssert($resErroDefinitivoAtivo['tipo'] === 'status_bloqueado' && $seqErroDefinitivoAtivo->reservas === 0 && $clientErroDefinitivoAtivo->chamadas === 0, 'emissão ativa em erro definitivo não preparável continua bloqueando sem reservar ou chamar API');


echo "NFS-e emission service tests passed\n";
