<?php

require __DIR__ . '/../app/Services/WorkerDaemonRunner.php';

use Services\WorkerDaemonRunner;

class FakeWorker
{
    private $summary;
    private $throw;
    public $calls = 0;

    public function __construct(array $summary = [], ?Throwable $throw = null)
    {
        $this->summary = $summary;
        $this->throw = $throw;
    }

    public function executarCiclo(): array
    {
        $this->calls++;
        if($this->throw){
            throw $this->throw;
        }

        return $this->summary;
    }
}

function assertTrue($condition, $message)
{
    if(!$condition){
        throw new Exception($message);
    }
}

function tempLock(): string
{
    $file = tempnam(sys_get_temp_dir(), 'daemon-lock-');
    @unlink($file);
    return $file;
}

function baseConfig(array $extra = []): array
{
    return array_merge([
        'daemon_id' => 'daemon-test',
        'idle_sleep_seconds' => 1,
        'busy_sleep_seconds' => 1,
        'max_sleep_seconds' => 4,
        'sleep_granularity_seconds' => 0.25,
        'max_cycles' => 1,
        'max_runtime_seconds' => 0,
        'max_memory_mb' => 0,
        'heartbeat_seconds' => 0.001,
        'lock_file' => tempLock(),
        'simulate_sapi' => 'cli',
        'simulate_pcntl' => true,
    ], $extra);
}

function idleSummary(array $override = []): array
{
    return array_replace_recursive([
        'worker_id' => 'worker-cycle',
        'lock_compartilhado' => 'adquirido',
        'lotes_manuais' => ['processados' => 0, 'reservados' => 0, 'enviados' => 0, 'erros' => 0, 'bloqueados' => 0],
        'campanhas' => ['campanhas_encontradas' => 0, 'processadas' => 0, 'reservados' => 0, 'enviados' => 0, 'erros_temporarios' => 0, 'erros_definitivos' => 0, 'bloqueados' => 0, 'excecoes' => 0],
        'recuperados' => ['manual' => 0, 'campanhas' => 0, 'total' => 0],
        'excecoes' => []
    ], $override);
}

function runDaemon(array $config, $workerOrFactory, ?callable $sleeper = null, ?callable $memory = null): array
{
    $logs = [];
    $factory = is_callable($workerOrFactory) ? $workerOrFactory : function() use ($workerOrFactory){ return $workerOrFactory; };
    $runner = new WorkerDaemonRunner($config, $factory, function($entry) use (&$logs){ $logs[] = $entry; }, $sleeper ?: function(){}, $memory);
    $code = $runner->run();
    return [$code, $logs, $runner];
}

function events(array $logs, string $event): array
{
    return array_values(array_filter($logs, function($line) use ($event){ return ($line['event'] ?? '') === $event; }));
}

// CLI válido, lock adquirido, cleanup do lock, idle inicial e exit code 0.
$lock = tempLock();
$worker = new FakeWorker(idleSummary());
[$code, $logs] = runDaemon(baseConfig(['lock_file' => $lock]), $worker);
assertTrue($code === WorkerDaemonRunner::EXIT_OK, 'CLI valido deve sair 0');
assertTrue($worker->calls === 1, 'lock adquirido executa um ciclo');
assertTrue(!is_file($lock), 'cleanup do lock');
assertTrue(events($logs, 'cycle_finish')[0]['data']['next_sleep_seconds'] === 2.0, 'idle inicial dobra para 2');

// Não CLI e pcntl ausente.
[$code] = runDaemon(baseConfig(['simulate_sapi' => 'apache2handler']), new FakeWorker(idleSummary()));
assertTrue($code === WorkerDaemonRunner::EXIT_CONFIG, 'nao CLI deve sair 2');
[$code] = runDaemon(baseConfig(['simulate_pcntl' => false]), new FakeWorker(idleSummary()));
assertTrue($code === WorkerDaemonRunner::EXIT_CONFIG, 'pcntl ausente deve sair 2');

// Lock ocupado não inicia Worker e não remove lock legítimo.
$lock = tempnam(sys_get_temp_dir(), 'daemon-lock-');
$handle = fopen($lock, 'c+');
flock($handle, LOCK_EX | LOCK_NB);
$worker = new FakeWorker(idleSummary());
[$code] = runDaemon(baseConfig(['lock_file' => $lock]), $worker);
assertTrue($code === WorkerDaemonRunner::EXIT_LOCKED, 'lock ocupado deve sair 3');
assertTrue($worker->calls === 0, 'lock ocupado nao inicia worker');
assertTrue(is_file($lock), 'lock ocupado nao remove arquivo legitimo');
flock($handle, LOCK_UN); fclose($handle); @unlink($lock);

// Busy por campos reais, lock compartilhado ocupado é idle, erro operacional é busy coerente.
[$code, $logs] = runDaemon(baseConfig(), new FakeWorker(idleSummary(['campanhas' => ['campanhas_encontradas' => 1]])));
assertTrue(events($logs, 'cycle_finish')[0]['data']['busy'] === true, 'campanha ativada conta como busy');
[$code, $logs] = runDaemon(baseConfig(), new FakeWorker(idleSummary(['lotes_manuais' => ['reservados' => 1]])));
assertTrue(events($logs, 'cycle_finish')[0]['data']['busy'] === true, 'item reservado conta como busy');
[$code, $logs] = runDaemon(baseConfig(), new FakeWorker(idleSummary(['recuperados' => ['total' => 1]])));
assertTrue(events($logs, 'cycle_finish')[0]['data']['busy'] === true, 'recuperado conta como busy');
[$code, $logs] = runDaemon(baseConfig(), new FakeWorker(idleSummary(['lotes_manuais' => ['bloqueados' => 1]])));
assertTrue(events($logs, 'cycle_finish')[0]['data']['busy'] === true, 'bloqueio conta como busy');
[$code, $logs] = runDaemon(baseConfig(), new FakeWorker(idleSummary(['excecoes' => [['etapa' => 'manual']]])));
assertTrue(events($logs, 'cycle_finish')[0]['data']['busy'] === true, 'erro operacional conta como busy');
[$code, $logs] = runDaemon(baseConfig(), new FakeWorker(idleSummary(['lock_compartilhado' => 'nao_adquirido', 'lotes_manuais' => ['processados' => 5]])));
assertTrue(events($logs, 'cycle_finish')[0]['data']['busy'] === false, 'lock compartilhado ocupado nao deve busy loop');
[$code, $logs] = runDaemon(baseConfig(), new FakeWorker(['inesperado' => true, 'lock_compartilhado' => 'adquirido']));
assertTrue(events($logs, 'cycle_finish')[0]['data']['busy'] === false, 'resumo inesperado nao busy loop');

// Crescimento do idle, limite máximo e reset após trabalho.
$i = 0;
[$code, $logs] = runDaemon(baseConfig(['max_cycles' => 3]), function() use (&$i){ $i++; return new FakeWorker(idleSummary()); });
$finishes = events($logs, 'cycle_finish');
assertTrue($finishes[0]['data']['next_sleep_seconds'] === 2.0, 'idle cresce para 2');
assertTrue($finishes[1]['data']['next_sleep_seconds'] === 4.0, 'idle cresce para maximo');
assertTrue($finishes[2]['data']['next_sleep_seconds'] === 4.0, 'idle respeita maximo');
$i = 0;
[$code, $logs] = runDaemon(baseConfig(['max_cycles' => 3]), function() use (&$i){ $i++; return new FakeWorker($i === 2 ? idleSummary(['lotes_manuais' => ['processados' => 1]]) : idleSummary()); });
$finishes = events($logs, 'cycle_finish');
assertTrue($finishes[0]['data']['next_sleep_seconds'] === 2.0 && $finishes[1]['data']['next_sleep_seconds'] === 1.0 && $finishes[2]['data']['next_sleep_seconds'] === 2.0, 'busy reseta backoff');

// Throwable fatal, sanitização aninhada, JSON parseável e exit code 1.
[$code, $logs] = runDaemon(baseConfig(), new FakeWorker([], new RuntimeException("token=abc\nsecret=123 Authorization: Bearer xyz")));
assertTrue($code === WorkerDaemonRunner::EXIT_FATAL, 'Throwable deve sair 1');
assertTrue(strpos(json_encode($logs), 'abc') === false && strpos(json_encode($logs), 'xyz') === false, 'fatal sanitizado');
foreach($logs as $line){
    $json = json_encode($line, JSON_UNESCAPED_UNICODE);
    assertTrue($json !== false && json_decode($json, true) !== null, 'heartbeat JSON parseavel');
    assertTrue(isset($line['timestamp'], $line['level'], $line['event'], $line['daemon_id'], $line['cycle'], $line['pid'], $line['uptime_seconds'], $line['memory_usage_bytes']), 'heartbeat campos obrigatorios');
}
[$code, $logs] = runDaemon(baseConfig(), new FakeWorker(idleSummary(['payload' => ['token' => 'abc', 'telefone' => '5511999999999'], 'normal' => 'ok', 'nested' => ['password' => 'x', 'texto' => 'comum']])));
$encoded = json_encode($logs, JSON_UNESCAPED_UNICODE);
assertTrue(strpos($encoded, 'abc') === false && strpos($encoded, '"normal":"ok"') !== false && strpos($encoded, 'comum') !== false, 'sanitizacao aninhada preserva campos comuns');

// Heartbeat zero/desabilitado.
[$code, $logs] = runDaemon(baseConfig(['heartbeat_seconds' => 0]), new FakeWorker(idleSummary()));
assertTrue($logs === [], 'heartbeat zero desabilita');

// Limites: ciclos 1, ciclos zero, runtime zero/atingido, memoria zero/atingida.
[$code, $logs] = runDaemon(baseConfig(['max_cycles' => 1]), new FakeWorker(idleSummary()));
assertTrue(count(events($logs, 'cycle_start')) === 1 && count(events($logs, 'limit_reached')) === 1, 'max cycles 1 executa exatamente um ciclo');
$logs = [];
$runner = null;
$starts = 0;
$runner = new WorkerDaemonRunner(baseConfig(['max_cycles' => 0]), function() use (&$starts, &$runner){ $starts++; if($starts >= 2){ $runner->stop(); } return new FakeWorker(idleSummary()); }, function($entry) use (&$logs){ $logs[] = $entry; }, function(){});
assertTrue($runner->run() === WorkerDaemonRunner::EXIT_OK && $starts === 2, 'max cycles zero desabilita limite');
[$code, $logs] = runDaemon(baseConfig(['max_runtime_seconds' => 0]), new FakeWorker(idleSummary()));
assertTrue($code === WorkerDaemonRunner::EXIT_OK, 'runtime zero desabilita');
[$code, $logs] = runDaemon(baseConfig(['max_runtime_seconds' => 0.001, 'max_cycles' => 0]), new FakeWorker(idleSummary()), function(){ usleep(2000); });
assertTrue(count(events($logs, 'limit_reached')) > 0 && events($logs, 'limit_reached')[0]['data']['limit'] === 'runtime', 'runtime atingido');
[$code, $logs] = runDaemon(baseConfig(['max_memory_mb' => 0]), new FakeWorker(idleSummary()), null, function(){ return 999999999; });
assertTrue($code === WorkerDaemonRunner::EXIT_OK, 'memoria zero desabilita');
[$code, $logs] = runDaemon(baseConfig(['max_memory_mb' => 0.000001, 'max_cycles' => 0]), new FakeWorker(idleSummary()), null, function(){ return 2; });
assertTrue(count(events($logs, 'limit_reached')) > 0 && events($logs, 'limit_reached')[0]['data']['limit'] === 'memory', 'memoria atingida');

// Shutdown antes do primeiro ciclo, durante sleep, múltiplos sinais e SIGHUP sem reload real.
$logs = [];
$worker = new FakeWorker(idleSummary());
$runner = new WorkerDaemonRunner(baseConfig(['max_cycles' => 0]), function() use ($worker){ return $worker; }, function($entry) use (&$logs){ $logs[] = $entry; }, function(){});
$runner->handleSignal(SIGTERM);
assertTrue($runner->run() === WorkerDaemonRunner::EXIT_OK && $worker->calls === 0, 'shutdown antes primeiro ciclo');
$logs = [];
$runner = null;
$runner = new WorkerDaemonRunner(baseConfig(['max_cycles' => 3]), function(){ return new FakeWorker(idleSummary()); }, function($entry) use (&$logs){ $logs[] = $entry; }, function() use (&$runner){ $runner->handleSignal(SIGINT); $runner->handleSignal(SIGQUIT); });
assertTrue($runner->run() === WorkerDaemonRunner::EXIT_OK, 'shutdown durante sleep');
assertTrue(count(events($logs, 'cycle_start')) === 1, 'shutdown impede novo ciclo');
$logs = [];
$runner = null;
$cycles = 0;
$runner = new WorkerDaemonRunner(baseConfig(['max_cycles' => 2]), function() use (&$cycles){ $cycles++; return new FakeWorker(idleSummary()); }, function($entry) use (&$logs){ $logs[] = $entry; }, function() use (&$runner){ $runner->handleSignal(SIGHUP); });
assertTrue($runner->run() === WorkerDaemonRunner::EXIT_OK, 'SIGHUP nao fatal');
assertTrue(count(events($logs, 'reload_unsupported')) >= 1 && $cycles === 2, 'SIGHUP registra reload nao suportado e mantem processo');

// Exit codes constantes.
assertTrue(WorkerDaemonRunner::EXIT_OK === 0 && WorkerDaemonRunner::EXIT_FATAL === 1 && WorkerDaemonRunner::EXIT_CONFIG === 2 && WorkerDaemonRunner::EXIT_LOCKED === 3, 'exit codes');

echo "WorkerDaemonRunnerTest OK\n";
