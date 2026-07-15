<?php

require_once __DIR__ . '/../app/Services/NfseConfigService.php';
require_once __DIR__ . '/../app/Services/NfseDpsSequenciaService.php';
require_once __DIR__ . '/../app/Models/NfseEmissao.php';

use Services\NfseDpsSequenciaService;

class FakeNfseSequenceDb
{
    public $sequences = [];
    public $begins = 0;
    public $commits = 0;
    public $rollbacks = 0;

    public function beginTransaction(){ $this->begins++; }
    public function commit(){ $this->commits++; }
    public function rollBack(){ $this->rollbacks++; }

    public function prepare($sql)
    {
        return new FakeNfseSequenceStatement($this, $sql);
    }
}

class FakeNfseSequenceStatement
{
    private $db;
    private $sql;
    private $lastKey;

    public function __construct($db, $sql)
    {
        $this->db = $db;
        $this->sql = $sql;
    }

    public function execute($params = [])
    {
        $key = implode('|', $params);
        $this->lastKey = $key;

        if(strpos($this->sql, 'INSERT INTO nfse_dps_sequencias') !== false){
            if(!isset($this->db->sequences[$key])){
                $this->db->sequences[$key] = 1;
            }
            return true;
        }

        if(strpos($this->sql, 'UPDATE nfse_dps_sequencias') !== false){
            $this->db->sequences[$key]++;
            return true;
        }

        return true;
    }

    public function fetchColumn()
    {
        return $this->db->sequences[$this->lastKey] ?? false;
    }
}

function nfseSeqAssert($condition, $message)
{
    if(!$condition){
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$db = new FakeNfseSequenceDb();
$service = new NfseDpsSequenciaService($db);

nfseSeqAssert($service->reservar('11.222.333/0001-81', 'production', '900') === '1', 'primeira reserva retorna 1');
nfseSeqAssert($service->reservar('11222333000181', 'production', '900') === '2', 'segunda reserva retorna 2');
nfseSeqAssert($service->reservar('11222333000181', 'sandbox', '900') === '1', 'ambiente diferente isola sequência');
nfseSeqAssert($service->reservar('11222333000181', 'production', '901') === '1', 'série diferente isola sequência');
nfseSeqAssert($service->reservar('22333444000155', 'production', '900') === '1', 'prestador diferente isola sequência');
nfseSeqAssert($db->begins === 5 && $db->commits === 5 && $db->rollbacks === 0, 'transações bem-sucedidas finalizam com commit');

try{
    $service->reservar('11222333000181', 'invalid', '900');
    nfseSeqAssert(false, 'ambiente inválido deveria falhar');
}catch(InvalidArgumentException $e){
    nfseSeqAssert(true, 'ambiente inválido falhou');
}

try{
    $service->reservar('123', 'production', '900');
    nfseSeqAssert(false, 'CNPJ inválido deveria falhar');
}catch(InvalidArgumentException $e){
    nfseSeqAssert(true, 'CNPJ inválido falhou');
}

$dbFalha = new FakeNfseSequenceDb();
$dbFalha->sequences['11222333000181|production|900'] = 0;
$serviceFalha = new NfseDpsSequenciaService($dbFalha);
try{
    $serviceFalha->reservar('11222333000181', 'production', '900');
    nfseSeqAssert(false, 'sequência zero deveria falhar');
}catch(RuntimeException $e){
    nfseSeqAssert($dbFalha->rollbacks === 1, 'falha após begin executa rollback');
}

echo "NFS-e DPS sequence tests passed\n";
