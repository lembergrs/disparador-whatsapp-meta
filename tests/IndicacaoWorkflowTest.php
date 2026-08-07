<?php

if(!extension_loaded('pdo_sqlite')){
    echo "IndicacaoWorkflowTest SKIP: pdo_sqlite indisponível\n";
    exit(0);
}

$root = dirname(__DIR__);
spl_autoload_register(function($classe) use ($root){
    $mapa = [
        'Models\\' => $root . '/app/Models/',
        'Services\\' => $root . '/app/Services/',
        'Core\\' => $root . '/app/Core/'
    ];
    foreach($mapa as $prefixo=>$base){
        if(strpos($classe, $prefixo) === 0){
            $arquivo = $base . str_replace('\\','/',substr($classe, strlen($prefixo))) . '.php';
            if(is_file($arquivo)) require_once $arquivo;
            return;
        }
    }
});

use Models\Cliente;
use Models\Indicacao;
use Models\IndicacaoAuditoria;
use Models\IndicacaoCampanha;
use Models\IndicacaoCodigo;
use Services\Indicacao\CodigoIndicacaoNormalizer;
use Services\Indicacao\CodigoIndicacaoPadraoGenerator;
use Services\Indicacao\IndicacaoAuditoriaService;
use Services\Indicacao\IndicacaoCodigoService;
use Services\Indicacao\IndicacaoService;
use Services\Indicacao\IndicacaoStatusTransitionService;
use Services\Indicacao\IndicacaoWorkflowService;

function iwAssert($ok, $mensagem)
{
    if(!$ok){
        fwrite(STDERR, "FAIL: {$mensagem}\n");
        exit(1);
    }
}

function iwDomain(callable $callback, $mensagem)
{
    try{
        $callback();
        iwAssert(false, $mensagem);
    }catch(DomainException $e){
        iwAssert(strpos($e->getMessage(), 'SQL') === false, $mensagem . ' sem SQL bruto');
    }
}

function iwDb()
{
    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("PRAGMA foreign_keys=ON;
        CREATE TABLE clientes(CLI_ID INTEGER PRIMARY KEY,CLI_Nome TEXT);
        CREATE TABLE indicacao_campanhas(ICP_ID INTEGER PRIMARY KEY,ICP_Nome TEXT,ICP_Descricao TEXT,ICP_Percentual REAL,ICP_DataInicio TEXT,ICP_DataFim TEXT,ICP_Ativo TEXT,ICP_Publica TEXT,ICP_RegrasSnapshot TEXT,ICP_CriadoPor_USU_ID INTEGER);
        CREATE TABLE indicacao_codigos(ICD_ID INTEGER PRIMARY KEY,CLI_ID INTEGER,ICP_ID INTEGER,ICD_Codigo TEXT,ICD_CodigoNormalizado TEXT UNIQUE,ICD_Status TEXT,ICD_LiberadoEm TEXT,ICD_SuspensoEm TEXT,ICD_CanceladoEm TEXT,UNIQUE(CLI_ID,ICP_ID));
        CREATE TABLE indicacoes(IND_ID INTEGER PRIMARY KEY AUTOINCREMENT,ICD_ID INTEGER,ICP_ID INTEGER,CLI_Indicador_ID INTEGER,CLI_Indicado_ID INTEGER UNIQUE,IND_PercentualSnapshot REAL,IND_Origem TEXT,IND_Status TEXT,IND_CadastradaEm TEXT DEFAULT CURRENT_TIMESTAMP,IND_PagamentoConfirmadoEm TEXT,IND_ConfirmacaoAte TEXT,IND_AprovadaEm TEXT,IND_CanceladaEm TEXT,IND_FraudeEm TEXT,IND_InelegivelEm TEXT,IND_Motivo TEXT);
        CREATE TABLE indicacao_auditoria(IAU_ID INTEGER PRIMARY KEY AUTOINCREMENT,IAU_Entidade TEXT,IAU_EntidadeID INTEGER,IAU_Acao TEXT,IAU_StatusAnterior TEXT,IAU_StatusNovo TEXT,IAU_Motivo TEXT,USU_ID INTEGER,IAU_Correlacao TEXT,IAU_Dados TEXT,IAU_CriadoEm TEXT DEFAULT CURRENT_TIMESTAMP);");

    $db->exec("INSERT INTO clientes VALUES(1,'Indicador'),(2,'Indicado link'),(3,'Indicado manual'),(4,'Outro indicado'),(10,'Dono 10'),(11,'Dono 11'),(12,'Dono 12'),(13,'Dono 13'),(14,'Dono 14'),(15,'Dono 15'),(16,'Dono 16');
        INSERT INTO indicacao_campanhas VALUES
        (1,'Inicial',NULL,15,'2020-01-01 00:00:00',NULL,'S','S','{}',NULL),
        (2,'Inativa',NULL,20,'2020-01-01 00:00:00',NULL,'N','S','{}',NULL),
        (3,'Futura',NULL,25,'2099-01-01 00:00:00',NULL,'S','S','{}',NULL),
        (4,'Encerrada',NULL,25,'2020-01-01 00:00:00','2020-02-01 00:00:00','S','S','{}',NULL),
        (5,'Privada',NULL,30,'2020-01-01 00:00:00',NULL,'S','N','{}',NULL);
        INSERT INTO indicacao_codigos VALUES
        (1,1,1,'ROD-8XJ4P','ROD-8XJ4P','ativo',NULL,NULL,NULL),
        (2,10,1,'NAO-ABCDE','NAO-ABCDE','nao_liberado',NULL,NULL,NULL),
        (3,11,1,'SUS-ABCDE','SUS-ABCDE','suspenso',NULL,NULL,NULL),
        (4,12,1,'CAN-ABCDE','CAN-ABCDE','cancelado',NULL,NULL,NULL),
        (5,13,2,'INA-ABCDE','INA-ABCDE','ativo',NULL,NULL,NULL),
        (6,14,3,'FUT-ABCDE','FUT-ABCDE','ativo',NULL,NULL,NULL),
        (7,15,4,'ENC-ABCDE','ENC-ABCDE','ativo',NULL,NULL,NULL),
        (8,16,5,'PRI-ABCDE','PRI-ABCDE','ativo',NULL,NULL,NULL);");
    return $db;
}

function iwWorkflow(PDO $db)
{
    $auditModel = new IndicacaoAuditoria($db);
    $audit = new IndicacaoAuditoriaService($auditModel);
    $campanhas = new IndicacaoCampanha($db);
    $indicacoes = new Indicacao($db);
    $codigosModel = new IndicacaoCodigo($db);
    $transition = new IndicacaoStatusTransitionService();
    $codigoService = new IndicacaoCodigoService(
        $codigosModel,
        new CodigoIndicacaoPadraoGenerator(),
        new CodigoIndicacaoNormalizer(),
        $audit,
        $transition,
        $db
    );
    $indicacaoService = new IndicacaoService($indicacoes,$campanhas,$codigosModel,$audit,$transition,$db);
    return [
        new IndicacaoWorkflowService($db,$codigoService,$campanhas,$indicacoes,new Cliente($db),$indicacaoService),
        $indicacoes,
        $auditModel
    ];
}

$db = iwDb();
[$workflow,$indicacoes,$auditoria] = iwWorkflow($db);

$validado = $workflow->validarCodigo('  rod-8xj4p  ');
iwAssert((int)$validado['codigo']['ICD_ID'] === 1, 'normalização central do código');

$id = $workflow->registrarIndicacao(2, 'ROD-8XJ4P', 'link');
$indicacao = $indicacoes->buscar($id);
iwAssert($indicacao['IND_Status'] === 'aguardando_pagamento', 'estado inicial operacional');
iwAssert((int)$indicacao['ICP_ID'] === 1, 'campanha congelada');
iwAssert((float)$indicacao['IND_PercentualSnapshot'] === 15.0, 'percentual congelado');
iwAssert($indicacao['IND_Origem'] === 'link', 'origem link');

$db->exec("UPDATE indicacao_campanhas SET ICP_Percentual=77 WHERE ICP_ID=1");
iwAssert((float)$indicacoes->buscar($id)['IND_PercentualSnapshot'] === 15.0, 'edição posterior não altera snapshot');
$db->exec("UPDATE indicacao_campanhas SET ICP_Percentual=15 WHERE ICP_ID=1");

$idManual = $workflow->registrarIndicacao(3, 'ROD-8XJ4P', 'manual');
iwAssert($indicacoes->buscar($idManual)['IND_Origem'] === 'manual', 'origem manual');

foreach(['NAO-ABCDE','SUS-ABCDE','CAN-ABCDE','INA-ABCDE','FUT-ABCDE','ENC-ABCDE','PRI-ABCDE'] as $codigo){
    iwDomain(function() use ($workflow,$codigo){ $workflow->validarCodigo($codigo); }, 'código/campanha inelegível');
}

iwDomain(function() use ($workflow){ $workflow->registrarIndicacao(1,'ROD-8XJ4P','manual'); }, 'autoindicação bloqueada');
iwDomain(function() use ($workflow){ $workflow->registrarIndicacao(2,'ROD-8XJ4P','manual'); }, 'duplicidade bloqueada');

try{
    $workflow->registrarIndicacao(4,'ROD-8XJ4P','outra');
    iwAssert(false, 'origem inválida deveria falhar');
}catch(InvalidArgumentException $e){
    iwAssert(true, 'origem inválida rejeitada');
}

$eventos = $auditoria->listar('indicacao',$id);
iwAssert(array_column($eventos,'IAU_Acao') === ['indicacao_criada','status_alterado'], 'auditoria de criação e transição');

$workflow->cancelarIndicacao($idManual, 'cancelamento administrativo');
$eventosManual = $auditoria->listar('indicacao',$idManual);
iwAssert(end($eventosManual)['IAU_Acao'] === 'indicacao_cancelada', 'cancelamento auditado');

echo "IndicacaoWorkflowTest OK\n";
