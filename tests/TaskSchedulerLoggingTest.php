<?php

require_once __DIR__ . '/../app/Services/Tasks/TaskSchedulerCliOutput.php';
require_once __DIR__ . '/../app/Services/Tasks/TaskSchedulerLoggingException.php';
require_once __DIR__ . '/../app/Services/Tasks/TaskSchedulerLogger.php';

use Services\Tasks\TaskSchedulerCliOutput;
use Services\Tasks\TaskSchedulerLogger;

function taskLogAssert($condicao, $mensagem){
    if(!$condicao){ fwrite(STDERR, "FAIL: {$mensagem}\n"); exit(1); }
}

$saida = new TaskSchedulerCliOutput();
$resumo = ['processadas'=>0,'concluidas'=>0,'retry'=>0,'falhas'=>0];
taskLogAssert($saida->formatar($resumo, false) === '', 'execução padrão deve ser silenciosa');
$verbose = $saida->formatar($resumo, true);
taskLogAssert(strpos($verbose, 'Processadas: 0') !== false, 'verbose deve exibir processadas');
taskLogAssert(strpos($verbose, 'Concluídas: 0') !== false, 'verbose deve exibir concluídas');
taskLogAssert(strpos($verbose, 'Retry: 0') !== false, 'verbose deve exibir retry');
taskLogAssert(strpos($verbose, 'Falhas: 0') !== false, 'verbose deve exibir falhas');

$arquivo = sys_get_temp_dir() . '/task-scheduler-logging-' . bin2hex(random_bytes(4)) . '.log';
$logger = new TaskSchedulerLogger($arquivo);
$logger([
    'data'=>date('c'),'tarefa_id'=>1,'tipo'=>'teste_scheduler','status'=>'concluida',
    'tentativa'=>1,'duracao_ms'=>10,'payload'=>'NAO_DEVE_APARECER'
]);
$logger([
    'data'=>date('c'),'tarefa_id'=>2,'tipo'=>'teste_scheduler','status'=>'retry',
    'tentativa'=>1,'duracao_ms'=>20,'erro_codigo'=>'falha_temporaria','token'=>'SEGREDO'
]);
$logger([
    'data'=>date('c'),'tarefa_id'=>3,'tipo'=>'teste_scheduler','status'=>'falha',
    'tentativa'=>2,'duracao_ms'=>30,'erro_codigo'=>'falha_permanente'
]);
$logger->operacional('falha operacional com espaços e token=segredo');

$linhas = file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
taskLogAssert(count($linhas) === 4, 'logger deve registrar somente chamadas reais');
$registros = array_map(static fn($linha) => json_decode($linha, true), $linhas);
taskLogAssert($registros[0]['nivel'] === 'INFO', 'conclusão deve ser INFO');
taskLogAssert($registros[1]['nivel'] === 'WARNING', 'retry deve ser WARNING');
taskLogAssert($registros[2]['nivel'] === 'ERROR', 'falha deve ser ERROR');
taskLogAssert($registros[3]['nivel'] === 'ERROR' && $registros[3]['status'] === 'erro_operacional', 'erro operacional deve ser ERROR');

$conteudo = file_get_contents($arquivo);
taskLogAssert(strpos($conteudo, 'NAO_DEVE_APARECER') === false, 'payload não deve aparecer no log');
taskLogAssert(strpos($conteudo, 'SEGREDO') === false && strpos($conteudo, 'segredo') === false, 'credenciais não devem aparecer no log');
taskLogAssert(strpos($conteudo, 'token=') === false, 'mensagem bruta de erro não deve aparecer');

@unlink($arquivo);
echo "TaskSchedulerLoggingTest OK\n";
