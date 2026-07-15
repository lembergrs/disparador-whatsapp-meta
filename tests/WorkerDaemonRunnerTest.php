<?php

defined('WORKER_PROCESSING_TIMEOUT_MINUTES') || define('WORKER_PROCESSING_TIMEOUT_MINUTES', 15);
defined('WORKER_IDLE_SLEEP_SECONDS') || define('WORKER_IDLE_SLEEP_SECONDS', 5);
defined('WORKER_BUSY_SLEEP_SECONDS') || define('WORKER_BUSY_SLEEP_SECONDS', 1);
defined('WORKER_ERROR_SLEEP_SECONDS') || define('WORKER_ERROR_SLEEP_SECONDS', 10);
defined('WORKER_ERROR_MAX_SLEEP_SECONDS') || define('WORKER_ERROR_MAX_SLEEP_SECONDS', 60);
defined('WORKER_LOCK_BUSY_SLEEP_SECONDS') || define('WORKER_LOCK_BUSY_SLEEP_SECONDS', 10);
defined('WORKER_MAX_RUNTIME_SECONDS') || define('WORKER_MAX_RUNTIME_SECONDS', 3600);
defined('WORKER_MAX_MEMORY_MB') || define('WORKER_MAX_MEMORY_MB', 256);
defined('WORKER_MAX_CYCLES') || define('WORKER_MAX_CYCLES', 2);
defined('WORKER_HEARTBEAT_SECONDS') || define('WORKER_HEARTBEAT_SECONDS', 1);

require_once __DIR__ . '/../app/Services/WorkerDaemonRunner.php';

use Services\WorkerDaemonRunner;

$assert = function($condition, $message){
    if(!$condition){
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$summary = function(array $overrides = []){
    return array_replace_recursive([
        'worker_id' => 'worker-teste',
        'lotes_manuais' => [
            'processados' => 0,
            'reservados' => 0,
            'enviados' => 0,
            'erros' => 0,
            'bloqueados' => 0,
        ],
        'campanhas' => [
            'reservados' => 0,
            'enviados' => 0,
            'erros_temporarios' => 0,
            'erros_definitivos' => 0,
            'bloqueados' => 0,
        ],
        'recuperados' => [
            'total' => 0,
        ],
        'excecoes' => [],
        'lock_compartilhado' => 'adquirido',
    ], $overrides);
};

class FakeDaemonWorkerService
{
    private $summaries;
    private $index = 0;

    public function __construct(array $summaries)
    {
        $this->summaries = $summaries;
    }

    public function executarCiclo(): array
    {
        $summary = $this->summaries[$this->index] ?? end($this->summaries);
        $this->index++;
        return $summary;
    }

    public function getWorkerId(): string
    {
        return 'worker-teste';
    }
}

class FakeFatalWorkerService
{
    public function executarCiclo(): array
    {
        throw new RuntimeException('falha fatal token=segredo');
    }
}

$logs = [];
$sleeps = [];
$time = 1000;
$fake = new FakeDaemonWorkerService([
    $summary(['campanhas' => ['reservados' => 1, 'enviados' => 1]]),
    $summary(),
]);

$runner = new WorkerDaemonRunner([
    'worker_factory' => function() use ($fake){ return $fake; },
    'sleep_callback' => function($seconds) use (&$sleeps){ $sleeps[] = $seconds; },
    'time_provider' => function() use (&$time){ $time += 2; return $time; },
    'memory_provider' => function(){ return 32; },
    'logger' => function($level, $event, $data) use (&$logs){ $logs[] = compact('level', 'event', 'data'); },
]);

$exitCode = $runner->executar();
$assert($exitCode === 0, 'loop encerra com sucesso ao atingir max cycles');
$assert($sleeps === [1], 'loop com trabalho usa WORKER_BUSY_SLEEP_SECONDS antes do ciclo final');
$assert(count(array_filter($logs, function($log){ return $log['event'] === 'worker.heartbeat'; })) >= 1, 'heartbeat é emitido');
$assert(count(array_filter($logs, function($log){ return $log['event'] === 'worker.cycle.finished'; })) === 2, 'dois ciclos executados');

$runnerSleep = new WorkerDaemonRunner([
    'worker_factory' => function() use ($fake){ return $fake; },
    'logger' => function(){},
]);
$assert($runnerSleep->calcularSleep($summary()) === WORKER_IDLE_SLEEP_SECONDS, 'sem trabalho usa WORKER_IDLE_SLEEP_SECONDS');
$assert($runnerSleep->calcularSleep($summary(['lock_compartilhado' => 'nao_adquirido'])) === WORKER_LOCK_BUSY_SLEEP_SECONDS, 'lock ocupado usa WORKER_LOCK_BUSY_SLEEP_SECONDS');
$assert($runnerSleep->calcularSleep($summary(['excecoes' => [['etapa' => 'teste']]])) === WORKER_ERROR_SLEEP_SECONDS, 'exceção usa WORKER_ERROR_SLEEP_SECONDS');


$runtimeLogs = [];
$runtimeFake = new FakeDaemonWorkerService([$summary()]);
$runtimeNow = 5000;
$runtimeRunner = new WorkerDaemonRunner([
    'worker_factory' => function() use ($runtimeFake){ return $runtimeFake; },
    'sleep_callback' => function(){},
    'time_provider' => function() use (&$runtimeNow){ $runtimeNow += (WORKER_MAX_RUNTIME_SECONDS + 1); return $runtimeNow; },
    'memory_provider' => function(){ return 32; },
    'logger' => function($level, $event, $data) use (&$runtimeLogs){ $runtimeLogs[] = compact('level', 'event', 'data'); },
]);
$assert($runtimeRunner->executar() === 0, 'runtime máximo encerra sem erro fatal');
$runtimeStop = end($runtimeLogs);
$assert(($runtimeStop['event'] ?? '') === 'worker.daemon.stopped', 'runtime máximo registra parada');
$assert(($runtimeStop['data']['reason'] ?? '') === 'max_runtime', 'runtime máximo usa motivo max_runtime');

$memoryLogs = [];
$memoryFake = new FakeDaemonWorkerService([$summary()]);
$memoryRunner = new WorkerDaemonRunner([
    'worker_factory' => function() use ($memoryFake){ return $memoryFake; },
    'sleep_callback' => function(){},
    'time_provider' => function(){ return 9000; },
    'memory_provider' => function(){ return WORKER_MAX_MEMORY_MB + 1; },
    'logger' => function($level, $event, $data) use (&$memoryLogs){ $memoryLogs[] = compact('level', 'event', 'data'); },
]);
$assert($memoryRunner->executar() === 0, 'memória máxima encerra sem erro fatal');
$memoryStop = end($memoryLogs);
$assert(($memoryStop['event'] ?? '') === 'worker.daemon.stopped', 'memória máxima registra parada');
$assert(($memoryStop['data']['reason'] ?? '') === 'max_memory', 'memória máxima usa motivo max_memory');

$fatalLogs = [];
$fatalRunner = new WorkerDaemonRunner([
    'worker_factory' => function(){ return new FakeFatalWorkerService(); },
    'sleep_callback' => function(){},
    'time_provider' => function(){ return 2000; },
    'memory_provider' => function(){ return 32; },
    'logger' => function($level, $event, $data) use (&$fatalLogs){ $fatalLogs[] = compact('level', 'event', 'data'); },
]);
$assert($fatalRunner->executar() === 1, 'exceção fatal encerra com erro');
$assert(count(array_filter($fatalLogs, function($log){ return $log['level'] === 'critical' && $log['event'] === 'worker.daemon.fatal_exception'; })) === 1, 'exceção fatal gera log crítico');

$shutdownExecutou = false;
$shutdownRunner = new WorkerDaemonRunner([
    'worker_factory' => function() use (&$shutdownExecutou){ $shutdownExecutou = true; return new FakeDaemonWorkerService([]); },
    'sleep_callback' => function(){},
    'time_provider' => function(){ return 3000; },
    'memory_provider' => function(){ return 32; },
    'logger' => function(){},
]);
$shutdownRunner->solicitarShutdown('teste');
$assert($shutdownRunner->executar() === 0, 'shutdown solicitado encerra sem erro');
$assert($shutdownExecutou === false, 'shutdown solicitado antes do loop não inicia novo ciclo');

echo "WorkerDaemonRunner tests passed\n";
