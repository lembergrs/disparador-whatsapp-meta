<?php
if(!extension_loaded('pdo_sqlite')){ echo "TaskSchedulerTest SKIP: pdo_sqlite indisponível\n"; exit(0); }
require_once __DIR__ . '/../app/Models/TarefaAgendada.php';
require_once __DIR__ . '/../app/Services/Tasks/TaskHandlerInterface.php';
require_once __DIR__ . '/../app/Services/Tasks/TaskRetryException.php';
require_once __DIR__ . '/../app/Services/Tasks/TaskPermanentFailureException.php';
require_once __DIR__ . '/../app/Services/Tasks/TesteSchedulerHandler.php';
require_once __DIR__ . '/../app/Services/Tasks/TaskRegistry.php';
require_once __DIR__ . '/../app/Services/Tasks/TaskRetryPolicy.php';
require_once __DIR__ . '/../app/Services/Tasks/TaskDispatcher.php';
require_once __DIR__ . '/../app/Services/Tasks/TaskProcessor.php';
require_once __DIR__ . '/../app/Services/Tasks/TaskExecutionService.php';
require_once __DIR__ . '/../app/Services/TaskSchedulerService.php';

use Models\TarefaAgendada;
use Services\TaskSchedulerService;
use Services\Tasks\TaskDispatcher;
use Services\Tasks\TaskExecutionService;
use Services\Tasks\TaskProcessor;
use Services\Tasks\TaskRegistry;
use Services\Tasks\TaskHandlerInterface;
use Services\Tasks\TaskPermanentFailureException;

function taskAssert($condicao,$mensagem){if(!$condicao){fwrite(STDERR,"FAIL: {$mensagem}\n");exit(1);}}
function taskDb(){
    $db=new PDO('sqlite::memory:'); $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec("CREATE TABLE tarefas_agendadas (TAG_ID INTEGER PRIMARY KEY AUTOINCREMENT,TAG_Tipo TEXT NOT NULL,TAG_Status TEXT NOT NULL DEFAULT 'pendente',TAG_Prioridade INTEGER NOT NULL DEFAULT 100,TAG_ExecutarEm TEXT NOT NULL,TAG_Payload TEXT NOT NULL,TAG_ChaveIdempotencia TEXT NULL UNIQUE,TAG_Tentativas INTEGER NOT NULL DEFAULT 0,TAG_MaxTentativas INTEGER NOT NULL DEFAULT 3,TAG_ProximaTentativaEm TEXT NULL,TAG_ReservadaEm TEXT NULL,TAG_WorkerId TEXT NULL,TAG_IniciadaEm TEXT NULL,TAG_FinalizadaEm TEXT NULL,TAG_UltimoErro TEXT NULL,TAG_CriadaEm TEXT DEFAULT CURRENT_TIMESTAMP,TAG_AtualizadaEm TEXT DEFAULT CURRENT_TIMESTAMP)");
    return $db;
}

date_default_timezone_set('America/Sao_Paulo');
$db=taskDb(); $repo=new TarefaAgendada($db); $registry=new TaskRegistry(); $scheduler=new TaskSchedulerService($repo,$registry);
$futura=$scheduler->agendar('teste_scheduler',['cliente_id'=>123],new DateTimeImmutable('+1 hour'),'teste:futura',10,4);
$agora=$scheduler->agendarAgora('teste_scheduler',['resultado'=>'sucesso'],'teste:agora',100,3);
taskAssert($futura['criada'] && $agora['criada'],'deve agendar futura e imediata');
$duplicada=$scheduler->agendarAgora('teste_scheduler',[],'teste:futura'); taskAssert(!$duplicada['criada'] && $duplicada['id']===$futura['id'],'idempotência deve impedir duplicidade');
try{$scheduler->agendarAgora('classe_arbitraria',[]); taskAssert(false,'tipo desconhecido deve falhar');}catch(InvalidArgumentException $e){}
try{$scheduler->agendarAgora('teste_scheduler',['token'=>'segredo']); taskAssert(false,'payload sensível deve falhar');}catch(InvalidArgumentException $e){}

$workerA=$repo->reservarProxima('worker-a',15); taskAssert($workerA && $workerA['TAG_ID']===$agora['id'],'worker A deve reservar imediata');
taskAssert($repo->reservarProxima('worker-b',15)===null,'worker B não pode reservar mesma tarefa nem futura');
$repo->concluir($workerA['TAG_ID'],'worker-a',date('Y-m-d H:i:s'));

$presa=$scheduler->agendarAgora('teste_scheduler',[],'teste:lease',100,3);
$db->exec("UPDATE tarefas_agendadas SET TAG_Status='processando',TAG_Tentativas=1,TAG_ReservadaEm='2020-01-01 00:00:00',TAG_WorkerId='morto' WHERE TAG_ID=".(int)$presa['id']);
$recuperada=$repo->reservarProxima('worker-recuperacao',15);
taskAssert($recuperada['TAG_ID']===$presa['id'] && $recuperada['recuperada'],'lease expirado deve ser recuperado');
$repo->concluir($recuperada['TAG_ID'],'worker-recuperacao',date('Y-m-d H:i:s'));

$logs=[]; $processor=new TaskProcessor($repo,new TaskDispatcher($registry),null,15,function($linha)use(&$logs){$logs[]=$linha;});
$sucesso=$scheduler->agendarAgora('teste_scheduler',['resultado'=>'sucesso'],'exec:sucesso');
$retry=$scheduler->agendarAgora('teste_scheduler',['resultado'=>'retry'],'exec:retry',100,2);
$permanente=$scheduler->agendarAgora('teste_scheduler',['resultado'=>'falha'],'exec:falha');
$resumo=$processor->processarLote(10,'worker-processador');
taskAssert($resumo===['processadas'=>3,'concluidas'=>1,'retry'=>1,'falhas'=>1],'processador deve separar sucesso, retry e falha');
taskAssert($repo->buscar($retry['id'])['TAG_Status']==='pendente','retry deve voltar a pendente');
taskAssert($repo->buscar($permanente['id'])['TAG_Status']==='falha','falha permanente deve finalizar');
taskAssert($logs && !array_key_exists('payload',$logs[0]),'log não deve conter payload');

$dbImediato=taskDb(); $repoImediato=new TarefaAgendada($dbImediato); $registryImediato=new TaskRegistry();
$schedulerImediato=new TaskSchedulerService($repoImediato,$registryImediato);
$execution=new TaskExecutionService(new TaskProcessor($repoImediato,new TaskDispatcher($registryImediato)),10);
$imediata=$schedulerImediato->agendarAgora('teste_scheduler',['resultado'=>'sucesso'],'imediato:sucesso');
$resumoImediato=$execution->processarSobDemanda(1,'worker-sob-demanda');
taskAssert($resumoImediato['concluidas']===1 && $repoImediato->buscar($imediata['id'])['TAG_Status']==='concluida','imediata deve processar sem esperar cron');
$futura2=$schedulerImediato->agendar('teste_scheduler',['resultado'=>'sucesso'],new DateTimeImmutable('+1 hour'),'imediato:futura');
$resumoFutura=$execution->processarSobDemanda(1,'worker-sob-demanda');
taskAssert($resumoFutura['processadas']===0 && $repoImediato->buscar($futura2['id'])['TAG_Status']==='pendente','não deve antecipar tarefa futura');
$fallback=$schedulerImediato->agendarAgora('teste_scheduler',['resultado'=>'sucesso'],'imediato:fallback');
taskAssert($repoImediato->buscar($fallback['id'])['TAG_Status']==='pendente','sem gatilho imediato deve aguardar cron');
$resumoFallback=$execution->processarSobDemanda(1,'worker-cron-equivalente');
taskAssert($resumoFallback['concluidas']===1 && $repoImediato->buscar($fallback['id'])['TAG_Status']==='concluida','cron deve processar fallback');

$handlerSensivel=new class implements TaskHandlerInterface { public function executar(array $payload): void { throw new TaskPermanentFailureException('token=secreto payload=privado falha'); } };
$registrySeguro=new TaskRegistry(['teste_scheduler'=>$handlerSensivel]); $dbSeguro=taskDb(); $repoSeguro=new TarefaAgendada($dbSeguro); $schedulerSeguro=new TaskSchedulerService($repoSeguro,$registrySeguro);
$segura=$schedulerSeguro->agendarAgora('teste_scheduler',[],'seguranca:erro');
(new TaskProcessor($repoSeguro,new TaskDispatcher($registrySeguro)))->processarLote(1,'worker-seguro');
$erroSeguro=$repoSeguro->buscar($segura['id'])['TAG_UltimoErro'];
taskAssert(strpos($erroSeguro,'secreto')===false && strpos($erroSeguro,'privado')===false,'erro persistido deve ser sanitizado');

echo "TaskSchedulerTest OK\n";
