<?php

require_once __DIR__ . '/../app/Models/NfseEmissao.php';

use Models\NfseEmissao;

class FakeNfseEmissaoDb
{
    public $rows = [];
    public $insertThrows = null;

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
                    if(($row['NFE_Status'] ?? '') !== NfseEmissao::STATUS_CANCELADA){ $this->result = $row; break; }
                }
            }
            return true;
        }

        if(strpos($this->sql, 'INSERT INTO nfse_emissoes') !== false){
            if($this->db->insertThrows instanceof PDOException){
                throw $this->db->insertThrows;
            }

            $cobrancaId = (int) $params[':cobranca'];
            $ativa = false;
            foreach($this->db->rows[$cobrancaId] ?? [] as $row){
                if(($row['NFE_Status'] ?? '') !== NfseEmissao::STATUS_CANCELADA){ $ativa = true; }
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
                'NFE_Serie' => $params[':serie']
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
$terceira = $model->criarOuBuscarPorCobranca($cobranca, ['status' => NfseEmissao::STATUS_PENDENTE, 'prestador_cnpj' => '11.534.763/0001-39', 'ambiente' => 'production', 'serie' => '900']);
nfseEmissaoAssert($terceira['NFE_ID'] !== $primeira['NFE_ID'], 'reemissão após cancelamento cria novo NFE_ID');
nfseEmissaoAssert($terceira['NFE_IdempotencyKey'] !== $primeira['NFE_IdempotencyKey'], 'reemissão após cancelamento cria novo RequestId/idempotência local');
nfseEmissaoAssert($db->rows[123][0]['NFE_Status'] === NfseEmissao::STATUS_CANCELADA, 'histórico cancelado preservado');
nfseEmissaoAssert($terceira['NFE_PrestadorCnpj'] === '11534763000139' && $terceira['NFE_Ambiente'] === 'production' && $terceira['NFE_Serie'] === '900', 'reemissão após cancelamento persiste CNPJ/production/900');

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
