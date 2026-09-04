<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

function onboardingAssert($condition, string $message): void
{
    if(!$condition) throw new RuntimeException($message);
}

function onboardingDb(): PDO
{
    if(!in_array('sqlite', PDO::getAvailableDrivers(), true)){
        throw new RuntimeException('Execute com php -d extension=pdo_sqlite para usar o banco isolado em memória.');
    }
    $db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $db->exec(onboardingSchema());
    return $db;
}

function onboardingSchema(): string
{
    return "CREATE TABLE meta_contas (MTA_ID INTEGER PRIMARY KEY, CLI_ID INTEGER, MTA_Nome VARCHAR(255) DEFAULT 'Minha empresa',
        MTA_NumeroTelefone VARCHAR(255) DEFAULT '5541999990000', MTA_Ativo VARCHAR(1) DEFAULT 'S', MTA_Status TEXT,
        MTA_OnboardingType VARCHAR(30) DEFAULT 'traditional', MTA_PagamentoMetaStatus TEXT,
        MTA_QualityRating VARCHAR(30) DEFAULT 'UNKNOWN', MTA_OperationalStatus VARCHAR(30) DEFAULT 'CONNECTED',
        MTA_UltimaVerificacao TEXT, MTA_MessagingLimit TEXT);
        CREATE TABLE templates_meta (MTA_ID INTEGER, TMP_MetaId VARCHAR(255), TMP_Status VARCHAR(30), TMP_Ativo VARCHAR(1) DEFAULT 'S');
        CREATE TABLE conversas (CVS_ID INTEGER PRIMARY KEY, CLI_ID INTEGER, MTA_ID INTEGER);
        CREATE TABLE conversa_mensagens (MSG_ID INTEGER PRIMARY KEY, CVS_ID INTEGER, MSG_Direcao VARCHAR(30) DEFAULT 'enviada',
            MSG_Origem VARCHAR(30) DEFAULT 'api', MSG_MetaMessageId TEXT, MSG_Status TEXT, MSG_DataMensagem TEXT);
        CREATE TABLE disparos (CLI_ID INTEGER, MTA_ID INTEGER, DSP_Status TEXT, DSP_MessageId TEXT);
        CREATE TABLE disparo_manual_lotes (DML_ID INTEGER PRIMARY KEY, CLI_ID INTEGER, MTA_ID INTEGER);
        CREATE TABLE disparo_manual_itens (DMI_ID INTEGER PRIMARY KEY, DML_ID INTEGER, CLI_ID INTEGER,
            DMI_Status TEXT, DMI_MessageId TEXT, DMI_DataCadastro TEXT);";
}

function onboardingInsert(PDO $db, string $table, array $data): void
{
    $sql = $db->prepare('INSERT INTO ' . $table . ' (' . implode(',', array_keys($data)) . ') VALUES (' . implode(',', array_fill(0, count($data), '?')) . ')');
    $sql->execute(array_values($data));
}

function onboardingAccount(PDO $db, int $id = 1, string $status = 'conectado', $payment = 'confirmado_cliente', int $client = 10): void
{
    onboardingInsert($db, 'meta_contas', ['MTA_ID'=>$id, 'CLI_ID'=>$client, 'MTA_Status'=>$status, 'MTA_PagamentoMetaStatus'=>$payment]);
}

function onboardingTemplate(PDO $db, string $status = 'APPROVED', int $account = 1): void
{
    onboardingInsert($db, 'templates_meta', ['MTA_ID'=>$account, 'TMP_MetaId'=>'tpl-' . $account . '-' . $status, 'TMP_Status'=>$status]);
}

function onboardingMessage(PDO $db, string $status, int $account = 1, int $id = 1, string $origin = 'api', string $direction = 'enviada', int $client = 10): void
{
    $db->prepare('INSERT OR IGNORE INTO conversas (CVS_ID,CLI_ID,MTA_ID) VALUES (?,?,?)')->execute([$account, $client, $account]);
    onboardingInsert($db, 'conversa_mensagens', ['MSG_ID'=>$id, 'CVS_ID'=>$account, 'MSG_Status'=>$status,
        'MSG_MetaMessageId'=>'wamid-' . $id, 'MSG_DataMensagem'=>sprintf('2026-09-04 10:%02d:00', $id),
        'MSG_Origem'=>$origin, 'MSG_Direcao'=>$direction]);
}

function onboardingAccess(bool $preTrial = false): array
{
    return ['operacional'=>!$preTrial, 'pre_trial'=>$preTrial, 'gerenciar'=>true, 'configuracao'=>true];
}

class OnboardingSelectOnlyConnection
{
    public $queries = [];
    private $db;
    public function __construct(PDO $db) { $this->db = $db; }
    public function prepare($sql)
    {
        onboardingAssert(preg_match('/^\s*SELECT\b/i', $sql) === 1, 'Onboarding tentou executar uma escrita.');
        $this->queries[] = $sql;
        return $this->db->prepare($sql);
    }
    public function query($sql)
    {
        $statement = $this->prepare($sql);
        $statement->execute();
        return $statement;
    }
    public function __call($method, $arguments) { throw new RuntimeException('Operação não permitida: ' . $method); }
}

function onboardingCalculate(PDO $db, ?array $access = null, $account = null): array
{
    $db->exec('PRAGMA query_only=ON');
    try{
        $read = new \Models\OnboardingReadModel(new OnboardingSelectOnlyConnection($db));
        return (new \Services\OnboardingChecklistService($read))->calcular(10, $access ?? onboardingAccess(), $account);
    }finally{
        $db->exec('PRAGMA query_only=OFF');
    }
}
