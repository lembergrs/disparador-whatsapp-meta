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
require_once __DIR__ . '/../app/Services/TaskSchedulerService.php';

use Models\TarefaAgendada;
use Services\TaskSchedulerService;
use Services\Tasks\TaskDispatcher;
use Services\Tasks\TaskProcessor;
use Services\Tasks\TaskRegistry;
use Services\Tasks\TaskHandlerInterface;
use Services\Tasks\TaskPermanentFailureException;

function taskAssert($condicao, $mensagem){ if(!$condicao){ fwrite(STDERR,"FAIL: {$mensagem}\n"); exit(1); } }
function taskDb(){
    $db = new PDO('sqlite::memory:'); $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec("CREATE TABLE tarefas_agendadas (TAG_ID INTEGER PRIMARY KEY AUTOINCREMENT,TAG_Tipo TEXT NOT NULL,TAG_Status TEXT NOT NULL DEFAULT 'pendente',TAG_Prioridade INTEGER NOT NULL DEFAULT 100,TAG_ExecutarEm TEXT NOT NULL,TAG_Payload TEXT NOT NULL,TAG_ChaveIdempotencia TEXT NULL UNIQUE,TAG_Tentativas INTEGER NOT NULL DEFAULT 0,TAG_MaxTentativas INTEGER NOT NULL DEFAULT 3,TAG_ProximaTentativaEm TEXT NULL,TAG_ReservadaEm TEXT NULL,TAG_WorkerId TEXT NULL,TAG_IniciadaEm TEXT NULL,TAG_FinalizadaEm TEXT NULL,TAG_UltimoErro TEXT NULL,TAG_CriadaEm TEXT DEFAULT CURRENT_TIMESTAMP,TAG_AtualizadaEm TEXT DEFAULT CURRENT_TIMESTAMP)");
    return $db;
}

date_default_timezone_set('America/Sao_Paulo');
$db=taskDb(); $repo=new TarefaAgendada($db); $registry=new TaskRegistry(); $scheduler=new TaskSchedulerService($repo,$registry);
$futura=$scheduler->agendar('teste_scheduler',['cliente_id'=>123],new DateTimeImmutable('+1 hour'),'teste:futura',10,4);
$agora=$scheduler->agendarAgora('teste_scheduler',['resultado'=>'sucesso'],'teste:agora',100,3);
taskAssert($futura['criada'] && $agora['criada'], 'deve agendar tarefa futura e imediata');
$linha=$repo->buscar($futura['id']); taskAssert(json_decode($linha['TAG_Payload'],true)['cliente_id']===123 && (int)$linha['TAG_Prioridade']===10 && (int)$linha['TAG_MaxTentativas']===4, 'deve persistir payload, prioridade e tentativas');
$duplicada=$scheduler->agendarAgora('teste_scheduler',[],'teste:futura'); taskAssert(!$duplicada['criada'] && $duplicada['id']===$futura['id'], 'chave idempotente deve impedir duplicidade');
$semChaveA=$scheduler->agendarAgora('teste_scheduler',[]); $semChaveB=$scheduler->agendarAgora('teste_scheduler',[]); taskAssert($semChaveA['id']!==$semChaveB['id'], 'tarefas sem chave podem se repetir');
try{$scheduler->agendarAgora('classe_arbitraria',[]); taskAssert(false,'tipo desconhecido deve falhar');}catch(InvalidArgumentException $e){}
try{$scheduler->agendarAgora('teste_scheduler',['token'=>'segredo']); taskAssert(false,'payload sensível deve falhar');}catch(InvalidArgumentException $e){}
try{$scheduler->agendarAgora('teste_scheduler',['objeto'=>new stdClass()]); taskAssert(false,'objeto no payload deve falhar');}catch(InvalidArgumentException $e){}

$workerA=$repo->reservarProxima('worker-a',15); $workerB=$repo->reservarProxima('worker-b',15);
taskAssert($workerA && $workerB && $workerA['TAG_ID']!==$workerB['TAG_ID'], 'workers devem reservar tarefas diferentes');
taskAssert($repo->buscar($workerA['TAG_ID'])['TAG_WorkerId']==='worker-a', 'reserva deve registrar worker id');
$repo->concluir($workerA['TAG_ID'],'worker-a',date('Y-m-d H:i:s')); $repo->concluir($workerB['TAG_ID'],'worker-b',date('Y-m-d H:i:s'));
$workerC=$repo->reservarProxima('worker-c',15); $repo->concluir($workerC['TAG_ID'],'worker-c',date('Y-m-d H:i:s'));
taskAssert($repo->reservarProxima('worker-c',15)===null, 'tarefa futura e concluídas não devem ser elegíveis');

$cancelada=$scheduler->agendarAgora('teste_scheduler',[],'teste:cancelada'); $scheduler->cancelar($cancelada['id']);
taskAssert($repo->reservarProxima('worker-c',15)===null, 'cancelada não deve processar');
$falha=$scheduler->agendarAgora('teste_scheduler',[],'teste:falha'); $db->exec("UPDATE tarefas_agendadas SET TAG_Status='falha' WHERE TAG_ID=".(int)$falha['id']);
taskAssert($repo->reservarProxima('worker-c',15)===null, 'falha final não deve processar automaticamente');

$presa=$scheduler->agendarAgora('teste_scheduler',[],'teste:lease',100,3);
$db->exec("UPDATE tarefas_agendadas SET TAG_Status='processando',TAG_Tentativas=1,TAG_ReservadaEm='2020-01-01 00:00:00',TAG_WorkerId='morto' WHERE TAG_ID=".(int)$presa['id']);
$recuperada=$repo->reservarProxima('worker-recuperacao',15);
taskAssert($recuperada['TAG_ID']===$presa['id'] && $recuperada['recuperada'] && (int)$recuperada['TAG_Tentativas']===2, 'lease expirado deve ser recuperado sem loop');
$repo->concluir($recuperada['TAG_ID'],'worker-recuperacao',date('Y-m-d H:i:s'));

$logs=[]; $processor=new TaskProcessor($repo,new TaskDispatcher($registry),null,15,function($linha)use(&$logs){$logs[]=$linha;});
$sucesso=$scheduler->agendarAgora('teste_scheduler',['resultado'=>'sucesso'],'exec:sucesso');
$retry=$scheduler->agendarAgora('teste_scheduler',['resultado'=>'retry'],'exec:retry',100,2);
$permanente=$scheduler->agendarAgora('teste_scheduler',['resultado'=>'falha'],'exec:falha');
$resumo=$processor->processarLote(10,'worker-processador');
taskAssert($resumo===['processadas'=>3,'concluidas'=>1,'retry'=>1,'falhas'=>1], 'processador deve separar sucesso, retry e falha');
$retryLinha=$repo->buscar($retry['id']); taskAssert($retryLinha['TAG_Status']==='pendente' && $retryLinha['TAG_ProximaTentativaEm']>date('Y-m-d H:i:s'), 'retry deve aplicar backoff futuro');
taskAssert($repo->buscar($permanente['id'])['TAG_Status']==='falha', 'falha permanente deve finalizar');
$db->prepare("UPDATE tarefas_agendadas SET TAG_ProximaTentativaEm=? WHERE TAG_ID=?")->execute(['2020-01-01 00:00:00',$retry['id']]);
$maximo=$processor->processarLote(1,'worker-processador'); taskAssert($maximo['falhas']===1 && $repo->buscar($retry['id'])['TAG_Status']==='falha', 'máximo de tentativas deve finalizar falha transitória');
taskAssert($logs && !array_key_exists('payload',$logs[0]), 'log não deve conter payload');

$dbConcorrencia=taskDb(); $repoConcorrencia=new TarefaAgendada($dbConcorrencia); $schedulerConcorrencia=new TaskSchedulerService($repoConcorrencia,$registry);
$unica=$schedulerConcorrencia->agendarAgora('teste_scheduler',[],'concorrencia:unica');
taskAssert($repoConcorrencia->reservarProxima('worker-a',15)['TAG_ID']===$unica['id'], 'worker A deve reservar tarefa única');
taskAssert($repoConcorrencia->reservarProxima('worker-b',15)===null, 'worker B não pode reservar a mesma tarefa');

$handlerSensivel=new class implements TaskHandlerInterface { public function executar(array $payload): void { throw new TaskPermanentFailureException('token=secreto payload=privado falha'); } };
$registrySeguro=new TaskRegistry(['teste_scheduler'=>$handlerSensivel]); $dbSeguro=taskDb(); $repoSeguro=new TarefaAgendada($dbSeguro); $schedulerSeguro=new TaskSchedulerService($repoSeguro,$registrySeguro);
$segura=$schedulerSeguro->agendarAgora('teste_scheduler',[],'seguranca:erro');
(new TaskProcessor($repoSeguro,new TaskDispatcher($registrySeguro)))->processarLote(1,'worker-seguro');
$erroSeguro=$repoSeguro->buscar($segura['id'])['TAG_UltimoErro'];
taskAssert(strpos($erroSeguro,'secreto')===false && strpos($erroSeguro,'privado')===false, 'erro persistido deve remover token e payload');

echo "TaskSchedulerTest OK\n";
