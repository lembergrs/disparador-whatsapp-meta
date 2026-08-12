<?php

if(!extension_loaded('pdo_sqlite')){
    echo "IndicacaoCampanhaEdicaoTest SKIP: pdo_sqlite indisponível\n";
    exit(0);
}

$root = dirname(__DIR__);
spl_autoload_register(function($classe) use ($root){
    foreach(['Models\\'=>'/app/Models/', 'Services\\'=>'/app/Services/', 'Core\\'=>'/app/Core/'] as $prefixo=>$diretorio){
        if(strpos($classe, $prefixo) === 0){
            $arquivo = $root . $diretorio . str_replace('\\', '/', substr($classe, strlen($prefixo))) . '.php';
            if(is_file($arquivo)) require_once $arquivo;
            return;
        }
    }
});

use Models\IndicacaoAuditoria;
use Models\IndicacaoCampanha;
use Services\Indicacao\IndicacaoAuditoriaService;
use Services\Indicacao\IndicacaoCampanhaService;

function iceAssert($condicao, $mensagem){
    if(!$condicao){
        fwrite(STDERR, "FAIL: {$mensagem}\n");
        exit(1);
    }
}

function iceThrows(callable $operacao, $mensagem){
    try{
        $operacao();
    }catch(InvalidArgumentException $e){
        return;
    }
    iceAssert(false, $mensagem);
}

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE indicacao_campanhas (ICP_ID INTEGER PRIMARY KEY, ICP_Nome TEXT, ICP_Descricao TEXT, ICP_Percentual TEXT, ICP_DataInicio TEXT, ICP_DataFim TEXT, ICP_Ativo TEXT, ICP_Publica TEXT, ICP_RegrasSnapshot TEXT, ICP_CriadoPor_USU_ID INTEGER);");
$db->exec("CREATE TABLE indicacao_auditoria (IAU_ID INTEGER PRIMARY KEY AUTOINCREMENT, IAU_Entidade TEXT, IAU_EntidadeID INTEGER, IAU_Acao TEXT, IAU_StatusAnterior TEXT, IAU_StatusNovo TEXT, IAU_Motivo TEXT, USU_ID INTEGER, IAU_Correlacao TEXT, IAU_Dados TEXT);");
$db->exec("CREATE TABLE indicacao_creditos (ICR_ID INTEGER PRIMARY KEY, ICR_Percentual TEXT, ICR_Status TEXT);");
$db->exec("CREATE TABLE indicacoes (IND_ID INTEGER PRIMARY KEY, IND_PercentualSnapshot TEXT, IND_Status TEXT);");
$db->exec("CREATE TABLE indicacao_codigos (ICD_ID INTEGER PRIMARY KEY, ICD_Status TEXT);");
$db->exec("CREATE TABLE indicacao_credito_reservas (ICRR_ID INTEGER PRIMARY KEY, ICRR_Status TEXT);");
$db->exec("CREATE TABLE cobrancas (COB_ID INTEGER PRIMARY KEY, COB_DescontoIndicacaoCentavos INTEGER);");
$db->exec("INSERT INTO indicacao_campanhas VALUES (1, 'Campanha inicial', NULL, '15.00', '2026-08-01 00:00:00', '2026-12-31 23:59:00', 'S', 'S', NULL, 1), (2, 'Campanha interna', NULL, '10.00', NULL, NULL, 'S', 'N', NULL, 1);");
$db->exec("INSERT INTO indicacao_creditos VALUES (1, '15.00', 'liberado'); INSERT INTO indicacoes VALUES (1, '15.00', 'aprovada'); INSERT INTO indicacao_codigos VALUES (1, 'ativo'); INSERT INTO indicacao_credito_reservas VALUES (1, 'utilizado'); INSERT INTO cobrancas VALUES (1, 1500);");

$model = new IndicacaoCampanha($db);
$audit = new IndicacaoAuditoriaService(new IndicacaoAuditoria($db));
$service = new IndicacaoCampanhaService($model, $audit, $db);
$service->editar(1, [
    'nome'=>'  Campanha atualizada  ', 'percentual'=>'20',
    'data_inicio'=>'2026-08-15T09:30', 'data_fim'=>'2026-12-30T18:00', 'publica'=>'S'
], 9);

$campanha = $model->buscar(1);
iceAssert($campanha['ICP_Nome'] === 'Campanha atualizada' && $campanha['ICP_Percentual'] === '20.00', 'admin pode editar nome e percentual');
iceAssert($campanha['ICP_DataInicio'] === '2026-08-15 09:30:00' && $campanha['ICP_DataFim'] === '2026-12-30 18:00:00', 'datas de edição são normalizadas');
iceAssert($campanha['ICP_Publica'] === 'S' && $campanha['ICP_Ativo'] === 'S', 'edição preserva status ativo e permite editar a própria campanha pública');
iceAssert((string)$db->query('SELECT ICR_Percentual FROM indicacao_creditos WHERE ICR_ID=1')->fetchColumn() === '15.00', 'edição não altera snapshot de crédito histórico');
iceAssert((string)$db->query('SELECT IND_PercentualSnapshot FROM indicacoes WHERE IND_ID=1')->fetchColumn() === '15.00', 'edição não altera snapshot de indicação');
iceAssert((string)$db->query('SELECT ICD_Status FROM indicacao_codigos WHERE ICD_ID=1')->fetchColumn() === 'ativo', 'edição não altera códigos existentes');
iceAssert((string)$db->query('SELECT ICRR_Status FROM indicacao_credito_reservas WHERE ICRR_ID=1')->fetchColumn() === 'utilizado', 'edição não altera reservas');
iceAssert((int)$db->query('SELECT COB_DescontoIndicacaoCentavos FROM cobrancas WHERE COB_ID=1')->fetchColumn() === 1500, 'edição não altera histórico financeiro');
$auditoria = $db->query("SELECT IAU_Acao, IAU_Dados FROM indicacao_auditoria WHERE IAU_Entidade='campanha' ORDER BY IAU_ID DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$dadosAuditoria = json_decode($auditoria['IAU_Dados'], true);
iceAssert($auditoria['IAU_Acao'] === 'configuracao_editada' && $dadosAuditoria['percentual_anterior'] === '15.00' && $dadosAuditoria['percentual_novo'] === '20.00', 'edição registra auditoria antes/depois');

iceThrows(fn()=>$service->editar(1, ['nome'=>'', 'percentual'=>'20', 'data_inicio'=>null, 'data_fim'=>null, 'publica'=>'S'], 9), 'nome inválido deve ser rejeitado');
iceThrows(fn()=>$service->editar(1, ['nome'=>'Válida', 'percentual'=>'0', 'data_inicio'=>null, 'data_fim'=>null, 'publica'=>'S'], 9), 'percentual inválido deve ser rejeitado');
iceThrows(fn()=>$service->editar(1, ['nome'=>'Válida', 'percentual'=>'20', 'data_inicio'=>'2026-15-40T10:00', 'data_fim'=>null, 'publica'=>'S'], 9), 'data inválida deve ser rejeitada');
iceThrows(fn()=>$service->editar(1, ['nome'=>'Válida', 'percentual'=>'20', 'data_inicio'=>'2026-09-02T10:00', 'data_fim'=>'2026-09-01T10:00', 'publica'=>'S'], 9), 'fim anterior ao início deve ser rejeitado');
iceThrows(fn()=>$service->editar(2, ['nome'=>'Conflitante', 'percentual'=>'10', 'data_inicio'=>null, 'data_fim'=>null, 'publica'=>'S'], 9), 'segunda campanha ativa pública deve conflitar');
iceThrows(fn()=>$service->editar(999, ['nome'=>'Ausente', 'percentual'=>'10', 'data_inicio'=>null, 'data_fim'=>null, 'publica'=>'N'], 9), 'campanha inexistente deve ser rejeitada');

echo "IndicacaoCampanhaEdicaoTest OK\n";
