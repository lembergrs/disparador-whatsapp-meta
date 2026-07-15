<?php

require __DIR__ . '/../app/Services/WorkerDaemonRunner.php';

use Services\WorkerDaemonRunner;

class FakeWorker
{
    private $summary;
    private $throw;
    public function __construct(array $summary = [], ?Throwable $throw = null){ $this->summary = $summary; $this->throw = $throw; }
    public function executarCiclo(): array
    {
        if($this->throw){ throw $this->throw; }
        return $this->summary;
    }
}

function assertTrue($condition, $message){ if(!$condition){ throw new Exception($message); } }
function baseConfig(array $extra = []): array
{
    $file = tempnam(sys_get_temp_dir(), 'daemon-lock-');
    @unlink($file);
    return array_merge([
        'daemon_id' => 'daemon-test',
        'idle_sleep_seconds' => 1,
        'busy_sleep_seconds' => 1,
        'max_sleep_seconds' => 4,
        'max_cycles' => 1,
        'heartbeat_enabled' => true,
        'lock_file' => $file,
        'simulate_sapi' => 'cli',
        'simulate_pcntl' => true,
    ], $extra);
}
function idleSummary(): array { return ['lock_compartilhado' => 'adquirido', 'lotes_manuais' => ['processados' => 0], 'campanhas' => ['processadas' => 0], 'recuperados' => ['total' => 0], 'excecoes' => []]; }
function busySummary(): array { return ['lock_compartilhado' => 'adquirido', 'lotes_manuais' => ['processados' => 1], 'campanhas' => ['processadas' => 0], 'recuperados' => ['total' => 0], 'excecoes' => []]; }
function runDaemon(array $config, $worker, ?callable $sleeper = null, ?callable $memory = null): array
{
    $logs = [];
    $runner = new WorkerDaemonRunner($config, function() use ($worker){ return $worker; }, function($entry) use (&$logs){ $logs[] = $entry; }, $sleeper ?: function(){}, $memory);
    $code = $runner->run();
    return [$code, $logs];
}

[$code, $logs] = runDaemon(baseConfig(), new FakeWorker(idleSummary()));
assertTrue($code === WorkerDaemonRunner::EXIT_OK, 'idle exit');
assertTrue(end($logs)['event'] === 'shutdown', 'idle shutdown');

[$code, $logs] = runDaemon(baseConfig(), new FakeWorker(busySummary()));
$finish = array_values(array_filter($logs, fn($l) => $l['event'] === 'cycle_finish'))[0];
assertTrue($finish['data']['busy'] === true, 'busy detection');

$lock = tempnam(sys_get_temp_dir(), 'daemon-lock-');
$h = fopen($lock, 'c+'); flock($h, LOCK_EX | LOCK_NB);
[$code, $logs] = runDaemon(baseConfig(['lock_file' => $lock]), new FakeWorker(idleSummary()));
assertTrue($code === WorkerDaemonRunner::EXIT_LOCKED, 'lock ocupado');
flock($h, LOCK_UN); fclose($h); @unlink($lock);

$operational = idleSummary(); $operational['excecoes'][] = ['mensagem' => 'erro operacional'];
[$code, $logs] = runDaemon(baseConfig(), new FakeWorker($operational));
assertTrue($code === WorkerDaemonRunner::EXIT_OK, 'erro operacional nao fatal');

[$code, $logs] = runDaemon(baseConfig(), new FakeWorker([], new RuntimeException("token=abc\nsecret=123")));
assertTrue($code === WorkerDaemonRunner::EXIT_FATAL, 'excecao fatal');
assertTrue(strpos(json_encode($logs), 'abc') === false, 'sanitizacao fatal');

$logs = []; $sleeps = [];
$runner = new WorkerDaemonRunner(baseConfig(['max_cycles' => 2]), function(){ static $i = 0; $i++; return new FakeWorker($i === 1 ? busySummary() : idleSummary()); }, function($e) use (&$logs){ $logs[] = $e; }, function($s) use (&$sleeps) { $sleeps[] = $s; });
assertTrue($runner->run() === WorkerDaemonRunner::EXIT_OK, 'reset backoff run');
$finishes = array_values(array_filter($logs, fn($l) => $l['event'] === 'cycle_finish'));
assertTrue($finishes[0]['data']['next_sleep_seconds'] === 1 && $finishes[1]['data']['next_sleep_seconds'] === 2, 'reset backoff');

[$code, $logs] = runDaemon(baseConfig(), new FakeWorker(idleSummary()));
foreach($logs as $line){ assertTrue(json_decode(json_encode($line), true) !== null, 'logs JSON parseaveis'); }
assertTrue(count($logs) > 0, 'heartbeat');

[$code, $logs] = runDaemon(baseConfig(['heartbeat_enabled' => false]), new FakeWorker(idleSummary()));
assertTrue($logs === [], 'heartbeat desabilitado');

[$code, $logs] = runDaemon(baseConfig(['max_cycles' => 2]), new FakeWorker(idleSummary()));
assertTrue(count(array_filter($logs, fn($l) => $l['event'] === 'cycle_start')) === 2, 'limite ciclos');

[$code, $logs] = runDaemon(baseConfig(['max_runtime_seconds' => 1, 'max_cycles' => 0]), new FakeWorker(idleSummary()), function(){ usleep(1100000); });
assertTrue((bool) array_filter($logs, fn($l) => ($l['data']['limit'] ?? '') === 'runtime'), 'limite runtime');

[$code, $logs] = runDaemon(baseConfig(['memory_limit_bytes' => 1, 'max_cycles' => 0]), new FakeWorker(idleSummary()), null, function(){ return 2; });
assertTrue((bool) array_filter($logs, fn($l) => ($l['data']['limit'] ?? '') === 'memory'), 'limite memoria');

$logs = []; $runner = null;
$runner = new WorkerDaemonRunner(baseConfig(['max_cycles' => 3]), function(){ return new FakeWorker(idleSummary()); }, function($e) use (&$logs){ $logs[] = $e; }, function() use (&$runner){ $runner->handleSignal(SIGTERM); });
assertTrue($runner->run() === WorkerDaemonRunner::EXIT_OK, 'shutdown antes novo ciclo');
assertTrue(count(array_filter($logs, fn($l) => $l['event'] === 'cycle_start')) === 1, 'sem novo ciclo apos shutdown');

[$code, $logs] = runDaemon(baseConfig(['simulate_pcntl' => false]), new FakeWorker(idleSummary()));
assertTrue($code === WorkerDaemonRunner::EXIT_CONFIG, 'ausencia pcntl');

[$code, $logs] = runDaemon(baseConfig(['simulate_sapi' => 'apache2handler']), new FakeWorker(idleSummary()));
assertTrue($code === WorkerDaemonRunner::EXIT_CONFIG, 'apenas CLI');

echo "WorkerDaemonRunnerTest OK\n";
