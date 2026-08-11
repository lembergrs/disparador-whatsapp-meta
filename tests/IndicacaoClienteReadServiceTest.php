<?php

if(!extension_loaded('pdo_sqlite')){
    echo "IndicacaoClienteReadServiceTest SKIP: pdo_sqlite indisponível\n";
    exit(0);
}

$root = dirname(__DIR__);
spl_autoload_register(function($classe) use ($root){
    if(strpos($classe, 'Services\\') === 0){
        $arquivo = $root . '/app/Services/' . str_replace('\\', '/', substr($classe, 9)) . '.php';
        if(is_file($arquivo)) require_once $arquivo;
    }
});

use Services\Indicacao\IndicacaoClienteReadService;

function icrAssert($condicao, $mensagem){ if(!$condicao){ throw new RuntimeException($mensagem); } }

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE clientes(CLI_ID INTEGER PRIMARY KEY, CLI_StatusPagamento TEXT, CLI_Nome TEXT, CLI_NomeFantasia TEXT);
CREATE TABLE cobrancas(COB_ID INTEGER PRIMARY KEY, CLI_ID INTEGER, COB_Status TEXT);
CREATE TABLE indicacao_campanhas(ICP_ID INTEGER PRIMARY KEY, ICP_Nome TEXT, ICP_Percentual TEXT, ICP_Ativo TEXT, ICP_Publica TEXT, ICP_DataInicio TEXT, ICP_DataFim TEXT);
CREATE TABLE indicacao_codigos(ICD_ID INTEGER PRIMARY KEY, CLI_ID INTEGER, ICP_ID INTEGER, ICD_Codigo TEXT, ICD_Status TEXT);
CREATE TABLE indicacoes(IND_ID INTEGER PRIMARY KEY, CLI_Indicador_ID INTEGER, CLI_Indicado_ID INTEGER, IND_Status TEXT, IND_CadastradaEm TEXT);
CREATE TABLE indicacao_creditos(ICR_ID INTEGER PRIMARY KEY, IND_ID INTEGER, CLI_Indicador_ID INTEGER, ICR_Percentual TEXT, ICR_Status TEXT, ICR_LiberadoEm TEXT, ICR_CriadoEm TEXT);
CREATE TABLE indicacao_credito_reservas(ICRR_ID INTEGER PRIMARY KEY, ICR_ID INTEGER, ICRR_ReservadoEm TEXT, ICRR_UtilizadoEm TEXT);
INSERT INTO clientes VALUES(1, 'pendente', 'Empresa Indicadora', 'Indicadora'),(2, 'pago', 'Outra Empresa', 'Outra'),(3, 'pago', 'Cliente Indicado Privado', 'Privado'),(4, 'pago', 'Cliente de Outro Indicador', 'Outro'),(5, 'pendente', 'Nunca Pagou', 'Nunca'),(6, 'pendente', 'Código Preparando', 'Preparando'),(7, 'pago', 'Código Suspenso', 'Suspenso'),(8, 'pago', 'Código Cancelado', 'Cancelado');
INSERT INTO cobrancas VALUES(1, 1, 'pago'),(2, 2, 'pago'),(3, 6, 'pago'),(4, 7, 'pago'),(5, 8, 'pago');
INSERT INTO indicacao_campanhas VALUES(1, 'Campanha atual', '15.00', 'S', 'S', NULL, NULL);
INSERT INTO indicacao_codigos VALUES(1, 1, 1, 'CODIGO-UM', 'ativo'),(2, 2, 1, 'CODIGO-DOIS', 'ativo'),(3, 7, 1, 'CODIGO-SUSPENSO', 'suspenso'),(4, 8, 1, 'CODIGO-CANCELADO', 'cancelado');
INSERT INTO indicacoes VALUES(1, 1, 3, 'em_confirmacao', '2026-08-01 10:00:00'),(2, 2, 4, 'aguardando_pagamento', '2026-08-02 10:00:00');
INSERT INTO indicacao_creditos VALUES(1, 1, 1, '15.00', 'liberado', '2026-08-08 10:00:00', '2026-08-08 10:00:00'),(2, 2, 2, '15.00', 'reservado', '2026-08-09 10:00:00', '2026-08-09 10:00:00');
INSERT INTO indicacao_credito_reservas VALUES(1, 2, '2026-08-09 10:00:00', NULL);");

$leitura = new IndicacaoClienteReadService($db);
$dados = $leitura->obterParaCliente(1);
icrAssert($dados['compartilhamento']['disponivel'] === true && $dados['compartilhamento']['codigo'] === 'CODIGO-UM', 'cliente com pagamento histórico e código ativo permanece compartilhável mesmo pendente');
icrAssert($dados['resumo']['total_indicacoes'] === 1 && $dados['resumo']['em_confirmacao'] === 1 && $dados['resumo']['creditos_disponiveis'] === 1, 'totais mapeiam estados persistidos');
icrAssert(count($dados['indicacoes']) === 1 && !isset($dados['indicacoes'][0]['CLI_Nome']) && !isset($dados['indicacoes'][0]['CLI_NomeFantasia']), 'lista não retorna PII de outro cliente');
icrAssert(count($dados['creditos']) === 1 && $dados['creditos'][0]['ICR_Status'] === 'liberado', 'histórico é isolado por indicador');

$dadosOutro = $leitura->obterParaCliente(2);
icrAssert($dadosOutro['compartilhamento']['codigo'] === 'CODIGO-DOIS' && $dadosOutro['resumo']['creditos_reservados'] === 1, 'segundo cliente não lê dados do primeiro');

$nuncaPagou = $leitura->obterParaCliente(5);
icrAssert($nuncaPagou['compartilhamento']['estado'] === 'primeiro_pagamento_pendente', 'cliente sem cobrança paga aguarda primeiro pagamento');
$preparando = $leitura->obterParaCliente(6);
icrAssert($preparando['compartilhamento']['estado'] === 'codigo_nao_encontrado', 'cliente já pago sem código aguarda preparação');
$suspenso = $leitura->obterParaCliente(7);
icrAssert(!$suspenso['compartilhamento']['disponivel'] && $suspenso['compartilhamento']['estado'] === 'suspenso', 'código suspenso permanece indisponível');
$cancelado = $leitura->obterParaCliente(8);
icrAssert(!$cancelado['compartilhamento']['disponivel'] && $cancelado['compartilhamento']['estado'] === 'cancelado', 'código cancelado permanece indisponível');

echo "IndicacaoClienteReadServiceTest OK\n";
