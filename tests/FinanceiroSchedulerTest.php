<?php

require_once __DIR__ . '/../app/Services/Tasks/TaskHandlerInterface.php';
require_once __DIR__ . '/../app/Services/Tasks/TaskRetryException.php';
require_once __DIR__ . '/../app/Services/Tasks/TaskPermanentFailureException.php';
require_once __DIR__ . '/../app/Services/FinanceiroRecorrenciaService.php';
require_once __DIR__ . '/../app/Services/Tasks/FinanceiroGerarCobrancasRecorrentesHandler.php';
require_once __DIR__ . '/../app/Services/TaskSchedulerService.php';
require_once __DIR__ . '/../app/Services/FinanceiroSchedulerBootstrapService.php';

use Services\FinanceiroRecorrenciaService;
use Services\FinanceiroSchedulerBootstrapService;
use Services\TaskSchedulerService;
use Services\Tasks\FinanceiroGerarCobrancasRecorrentesHandler;
use Services\Tasks\TaskRetryException;

function financeiroSchedulerAssert($condicao, $mensagem){
    if(!$condicao){ throw new RuntimeException($mensagem); }
}

class FinanceiroSchedulerFake extends TaskSchedulerService
{
    public $tarefas=[];
    public function __construct(){}
    public function agendar($tipo, array $payload, DateTimeInterface $executarEm, $chaveIdempotencia = null, $prioridade = 100, $maxTentativas = 3): array
    {
        if(isset($this->tarefas[$chaveIdempotencia])) return ['criada'=>false,'id'=>$this->tarefas[$chaveIdempotencia]['id']];
        $id=count($this->tarefas)+1;
        $this->tarefas[$chaveIdempotencia]=compact('id','tipo','payload','executarEm','prioridade','maxTentativas');
        return ['criada'=>true,'id'=>$id];
    }
}

class FinanceiroRecorrenciaHandlerFake extends FinanceiroRecorrenciaService
{
    public $execucoes=0;
    public $resultado=['erros'=>0];
    public function __construct(){}
    public function gerarCobrancasRecorrentes(){ $this->execucoes++; return $this->resultado; }
}

$scheduler=new FinanceiroSchedulerFake();
$bootstrap=new FinanceiroSchedulerBootstrapService($scheduler,function(){return new DateTimeImmutable('2026-08-28 10:30:00');});
$primeira=$bootstrap->garantirExecucaoDiaria();
$segunda=$bootstrap->garantirExecucaoDiaria();
financeiroSchedulerAssert($primeira['criada']===true&&$segunda['criada']===false&&count($scheduler->tarefas)===1,'bootstrap deve criar uma única tarefa por dia');
$tarefa=array_values($scheduler->tarefas)[0];
financeiroSchedulerAssert($tarefa['tipo']===FinanceiroSchedulerBootstrapService::TIPO&&$tarefa['payload']===[]&&$tarefa['maxTentativas']===5,'tarefa diária deve possuir tipo, payload e retry esperados');

$recorrencia=new FinanceiroRecorrenciaHandlerFake();
$handler=new FinanceiroGerarCobrancasRecorrentesHandler($recorrencia);
$handler->executar([]);
financeiroSchedulerAssert($recorrencia->execucoes===1,'handler deve delegar para a fachada financeira');
$recorrencia->resultado=['erros'=>1];
$retry=false;
try{$handler->executar([]);}catch(TaskRetryException $e){$retry=true;}
financeiroSchedulerAssert($retry,'falha transitória deve solicitar retry ao scheduler');

echo "FinanceiroSchedulerTest OK\n";
