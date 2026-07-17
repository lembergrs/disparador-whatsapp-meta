<?php

require_once __DIR__ . '/../app/Models/NfseEmissao.php';

use Models\NfseEmissao;

class FakeNfseEmissaoDb
{
    public $rows = [];
    public $insertThrows = null;
    public $rowOnDuplicate = null;

    public function prepare($sql)
    {
        return new FakeNfseEmissaoStatement($this, $sql);
    }
}

class FakeNfseEmissaoStatement
{
    private $db;
    private $sql;
    private $result;
    private $rowCount = 0;

    public function __construct($db, $sql)
    {
        $this->db = $db;
        $this->sql = $sql;
    }

    public function execute($params = [])
    {
        if(strpos($this->sql, 'SELECT * FROM nfse_emissoes WHERE COB_ID') !== false){
            $cobrancaId = (int) ($params[0] ?? 0);
            $this->result = false;
            if(isset($this->db->rows[$cobrancaId])){
                foreach(array_reverse($this->db->rows[$cobrancaId]) as $row){
                    if((int) ($row['NFE_EmissaoAtiva'] ?? 0) === 1){ $this->result = $row; break; }
                }
            }
            return true;
        }

        if(strpos($this->sql, 'INSERT INTO nfse_emissoes') !== false){
            $cobrancaId = (int) $params[':cobranca'];
            if($this->db->insertThrows instanceof PDOException){
                if($this->db->rowOnDuplicate){
                    $this->db->rows[$cobrancaId][] = $this->db->rowOnDuplicate;
                    $this->db->rowOnDuplicate = null;
                }
                throw $this->db->insertThrows;
            }

            $ativa = false;
            foreach($this->db->rows[$cobrancaId] ?? [] as $row){
                if((int) ($row['NFE_EmissaoAtiva'] ?? 0) === 1){ $ativa = true; }
            }
            if($ativa){
                $e = new PDOException('Duplicate entry');
                $e->errorInfo = ['23000', 1062, 'Duplicate entry'];
                throw $e;
            }

            $this->db->rows[$cobrancaId][] = [
                'NFE_ID' => array_sum(array_map('count', $this->db->rows)) + 1,
                'CLI_ID' => (int) $params[':cliente'],
                'COB_ID' => $cobrancaId,
                'NFE_IdempotencyKey' => $params[':idempotency'],
                'NFE_Status' => $params[':status'],
                'NFE_PrestadorCnpj' => $params[':prestador_cnpj'],
                'NFE_Ambiente' => $params[':ambiente'],
                'NFE_Serie' => $params[':serie'],
                'NFE_NumDps' => null,
                'NFE_RequestIdEmissao' => null,
                'NFE_EmissaoAtiva' => 1
            ];
            return true;
        }

        if(strpos($this->sql, 'UPDATE nfse_emissoes') !== false){
            $this->rowCount = 1;
            return true;
        }

        return true;
    }

    public function fetch($mode = null)
    {
        return $this->result;
    }

    public function rowCount()
    {
        return $this->rowCount;
    }
}

function nfseEmissaoAssert($condition, $message)
{
    if(!$condition){
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$db = new FakeNfseEmissaoDb();
$model = new NfseEmissao($db);
$cobranca = ['COB_ID' => 123, 'CLI_ID' => 45, 'COB_Valor' => '99.90'];

$primeira = $model->criarOuBuscarPorCobranca($cobranca, ['status' => NfseEmissao::STATUS_PENDENTE, 'prestador_cnpj' => '11.534.763/0001-39', 'ambiente' => 'production', 'serie' => '900']);
nfseEmissaoAssert($primeira['COB_ID'] === 123, 'primeira chamada cria registro local');
nfseEmissaoAssert(strpos($primeira['NFE_IdempotencyKey'], 'nfse:cobranca:123:') === 0, 'chave idempotente única por emissão');
nfseEmissaoAssert($primeira['NFE_PrestadorCnpj'] === '11534763000139' && $primeira['NFE_Ambiente'] === 'production' && $primeira['NFE_Serie'] === '900', 'nova emissão comum persiste CNPJ/production/série recebidos do service');

$segunda = $model->criarOuBuscarPorCobranca($cobranca, ['status' => NfseEmissao::STATUS_PENDENTE]);
nfseEmissaoAssert($segunda === $primeira, 'chamada repetida retorna registro existente');
$db->rows[123][0]['NFE_Status'] = NfseEmissao::STATUS_CANCELADA;
$db->rows[123][0]['NFE_EmissaoAtiva'] = null;
$terceira = $model->criarOuBuscarPorCobranca($cobranca, ['status' => NfseEmissao::STATUS_PENDENTE, 'prestador_cnpj' => '11.534.763/0001-39', 'ambiente' => 'production', 'serie' => '900']);
nfseEmissaoAssert($terceira['NFE_ID'] !== $primeira['NFE_ID'], 'reemissão após cancelamento cria novo NFE_ID');
nfseEmissaoAssert($terceira['NFE_IdempotencyKey'] !== $primeira['NFE_IdempotencyKey'], 'reemissão após cancelamento cria novo RequestId/idempotência local');
nfseEmissaoAssert((int) $terceira['NFE_EmissaoAtiva'] === 1, 'nova emissão nasce ativa');
nfseEmissaoAssert($db->rows[123][0]['NFE_Status'] === NfseEmissao::STATUS_CANCELADA && $db->rows[123][0]['NFE_EmissaoAtiva'] === null, 'histórico cancelado preservado e inativo');
nfseEmissaoAssert($terceira['NFE_PrestadorCnpj'] === '11534763000139' && $terceira['NFE_Ambiente'] === 'production' && $terceira['NFE_Serie'] === '900', 'reemissão após cancelamento persiste CNPJ/production/900');

$dbHistorico = new FakeNfseEmissaoDb();
$dbHistorico->rows[456][] = ['NFE_ID' => 10, 'CLI_ID' => 45, 'COB_ID' => 456, 'NFE_Status' => NfseEmissao::STATUS_CANCELADA, 'NFE_NumDps' => '32', 'NFE_RequestIdEmissao' => 'req-cancelada', 'NFE_EmissaoAtiva' => null];
$dbHistorico->rows[456][] = ['NFE_ID' => 11, 'CLI_ID' => 45, 'COB_ID' => 456, 'NFE_Status' => NfseEmissao::STATUS_ERRO_DEFINITIVO, 'NFE_NumDps' => '1', 'NFE_RequestIdEmissao' => null, 'NFE_UltimoErroCodigo' => 'descartada_pre_envio_contexto_incorreto', 'NFE_EmissaoAtiva' => null];
$modelHistorico = new NfseEmissao($dbHistorico);
$novaAposHistorico = $modelHistorico->criarOuBuscarPorCobranca(['COB_ID' => 456, 'CLI_ID' => 45, 'COB_Valor' => '50.00'], ['status' => NfseEmissao::STATUS_PENDENTE, 'prestador_cnpj' => '11.534.763/0001-39', 'ambiente' => 'production', 'serie' => '900']);
nfseEmissaoAssert($novaAposHistorico['NFE_ID'] !== 10 && $novaAposHistorico['NFE_ID'] !== 11, 'cancelada/erro definitivo inativos não são reutilizados nem bloqueiam nova emissão');
nfseEmissaoAssert((int) $novaAposHistorico['NFE_EmissaoAtiva'] === 1, 'duas históricas inativas e nenhuma ativa geram nova ativa');
nfseEmissaoAssert($dbHistorico->rows[456][0]['NFE_EmissaoAtiva'] === null && $dbHistorico->rows[456][1]['NFE_EmissaoAtiva'] === null, 'nenhuma emissão antiga é reativada');

$dbConflito = new FakeNfseEmissaoDb();
$dbConflito->rows[789][] = ['NFE_ID' => 20, 'CLI_ID' => 45, 'COB_ID' => 789, 'NFE_Status' => NfseEmissao::STATUS_ERRO_DEFINITIVO, 'NFE_NumDps' => '1', 'NFE_RequestIdEmissao' => null, 'NFE_EmissaoAtiva' => null];
$duplicidade = new PDOException('Duplicate entry uk_nfse_cobranca_ativa');
$duplicidade->errorInfo = ['23000', 1062, 'Duplicate entry'];
$dbConflito->insertThrows = $duplicidade;
$dbConflito->rowOnDuplicate = ['NFE_ID' => 21, 'CLI_ID' => 45, 'COB_ID' => 789, 'NFE_Status' => NfseEmissao::STATUS_PENDENTE, 'NFE_NumDps' => null, 'NFE_RequestIdEmissao' => null, 'NFE_EmissaoAtiva' => 1];
$modelConflito = new NfseEmissao($dbConflito);
$vencedora = $modelConflito->criarOuBuscarPorCobranca(['COB_ID' => 789, 'CLI_ID' => 45, 'COB_Valor' => '50.00'], ['status' => NfseEmissao::STATUS_PENDENTE]);
nfseEmissaoAssert($vencedora['NFE_ID'] === 21 && (int) $vencedora['NFE_EmissaoAtiva'] === 1, 'recuperação após conflito de unique busca somente a emissão ativa vencedora');

$dbAtivaErro = new FakeNfseEmissaoDb();
$dbAtivaErro->rows[987][] = ['NFE_ID' => 30, 'CLI_ID' => 45, 'COB_ID' => 987, 'NFE_Status' => NfseEmissao::STATUS_ERRO_DEFINITIVO, 'NFE_NumDps' => '1', 'NFE_RequestIdEmissao' => null, 'NFE_EmissaoAtiva' => 1];
$modelAtivaErro = new NfseEmissao($dbAtivaErro);
$ativaErro = $modelAtivaErro->criarOuBuscarPorCobranca(['COB_ID' => 987, 'CLI_ID' => 45, 'COB_Valor' => '50.00'], ['status' => NfseEmissao::STATUS_PENDENTE]);
nfseEmissaoAssert($ativaErro['NFE_ID'] === 30, 'emissão ativa em erro definitivo continua sendo a emissão operacional atual');

try{
    $model->criarOuBuscarPorCobranca(['COB_ID' => null, 'CLI_ID' => 45]);
    nfseEmissaoAssert(false, 'COB_ID inválido deveria falhar');
}catch(InvalidArgumentException $e){
    nfseEmissaoAssert(true, 'COB_ID inválido falhou');
}

$dbErro = new FakeNfseEmissaoDb();
$erro = new PDOException('Erro real de banco');
$erro->errorInfo = ['HY000', 1205, 'Lock wait timeout'];
$dbErro->insertThrows = $erro;
$modelErro = new NfseEmissao($dbErro);
try{
    $modelErro->criarOuBuscarPorCobranca(['COB_ID' => 999, 'CLI_ID' => 1]);
    nfseEmissaoAssert(false, 'erro real de banco deveria propagar');
}catch(PDOException $e){
    nfseEmissaoAssert($e->getMessage() === 'Erro real de banco', 'erro real de banco propagado');
}

nfseEmissaoAssert(NfseEmissao::transicaoPermitida(NfseEmissao::STATUS_PROCESSANDO, NfseEmissao::STATUS_EMITIDA), 'transição processando para emitida permitida');
nfseEmissaoAssert(!NfseEmissao::transicaoPermitida(NfseEmissao::STATUS_CANCELADA, NfseEmissao::STATUS_PENDENTE), 'cancelada não volta para pendente');

try{
    $model->atualizarStatus(1, NfseEmissao::STATUS_PENDENTE, [], NfseEmissao::STATUS_EMITIDA);
    nfseEmissaoAssert(false, 'transição inválida deveria falhar');
}catch(InvalidArgumentException $e){
    nfseEmissaoAssert(true, 'transição inválida falhou');
}

echo "NFS-e emissão model tests passed\n";
