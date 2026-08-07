<?php
$root=dirname(__DIR__); $cli=file_get_contents($root.'/processar_tarefas.php'); $migration=file_get_contents($root.'/database/migrations/20260807_create_tarefas_agendadas.sql'); $registry=file_get_contents($root.'/app/Services/Tasks/TaskRegistry.php'); $service=file_get_contents($root.'/app/Services/TaskSchedulerService.php'); $execution=file_get_contents($root.'/app/Services/Tasks/TaskExecutionService.php');
function taskStaticAssert($c,$m){if(!$c){fwrite(STDERR,"FAIL: {$m}\n");exit(1);}}
taskStaticAssert(strpos($cli,"PHP_SAPI !== 'cli'")!==false,'CLI deve rejeitar HTTP');
taskStaticAssert(strpos($cli,'flock($lock, LOCK_EX | LOCK_NB)')!==false,'CLI deve possuir lock não bloqueante');
taskStaticAssert(strpos($cli,'TASK_SCHEDULER_BATCH_SIZE')!==false && strpos($cli,'Processadas: ')!==false,'CLI deve executar lote finito e imprimir resumo');
taskStaticAssert(strpos($cli,'TaskExecutionService')!==false && strpos($cli,'processarSobDemanda')!==false,'CLI deve reutilizar execução sob demanda');
taskStaticAssert(strpos($execution,'TaskProcessor')!==false && strpos($execution,'processarLote')!==false,'execução sob demanda deve delegar ao TaskProcessor');
taskStaticAssert(strpos($migration,'UNIQUE KEY uk_tarefas_agendadas_idempotencia')!==false,'idempotência deve estar no banco');
taskStaticAssert(strpos($migration,'idx_tarefas_agendadas_elegiveis')!==false && strpos($migration,'idx_tarefas_agendadas_lease')!==false,'migration deve indexar elegibilidade e lease');
taskStaticAssert(substr_count($registry,'teste_scheduler')===1 && strpos($registry,'liberar_credito_indicacao')===false,'catálogo inicial deve conter apenas teste interno');
foreach(['eval(','unserialize(','shell_exec(','system(','passthru('] as $perigoso){taskStaticAssert(strpos($service.$registry.$execution,$perigoso)===false,"não deve conter {$perigoso}");}
taskStaticAssert(strpos($cli,'while(true)')===false,'processador não deve ser daemon');
echo "TaskSchedulerStaticTest OK\n";
