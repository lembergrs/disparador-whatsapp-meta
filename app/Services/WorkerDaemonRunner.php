<?php

namespace Services;

class WorkerDaemonRunner
{
    public const EXIT_OK = 0;
    public const EXIT_FATAL = 1;
    public const EXIT_CONFIG = 2;
    public const EXIT_LOCKED = 3;

    private $config;
    private $workerFactory;
    private $logger;
    private $sleeper;
    private $memoryProvider;
    private $running = true;
    private $reloadRequested = false;
    private $daemonId;
    private $cycle = 0;
    private $lockHandle = null;

    public function __construct(array $config = [], ?callable $workerFactory = null, ?callable $logger = null, ?callable $sleeper = null, ?callable $memoryProvider = null)
    {
        $this->config = array_merge($this->defaultConfig(), $config);
        $this->workerFactory = $workerFactory ?: function(array $options){ return new WorkerService($options); };
        $this->logger = $logger ?: function(array $entry){ file_put_contents($this->config['heartbeat_file'], json_encode($entry, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND); };
        $this->sleeper = $sleeper ?: function(int $seconds): void { sleep($seconds); };
        $this->memoryProvider = $memoryProvider ?: function(): int { return memory_get_usage(true); };
        $this->daemonId = $this->config['daemon_id'] ?: $this->generateDaemonId();
    }

    public function run(): int
    {
        $validation = $this->validateRuntime();
        if($validation !== null){
            return $validation;
        }

        if(!$this->acquireLock()){
            $this->writeHeartbeat('lock_ocupado', ['motivo' => 'daemon_ja_em_execucao']);
            return self::EXIT_LOCKED;
        }

        $this->registerSignals();
        $startedAt = microtime(true);
        $sleepSeconds = $this->config['idle_sleep_seconds'];
        $exitCode = self::EXIT_OK;

        try{
            while($this->running){
                $this->dispatchSignals();

                if(!$this->running || $this->limitReached($startedAt)){
                    break;
                }

                $this->cycle++;
                $this->writeHeartbeat('cycle_start');

                try{
                    $summary = $this->executeCycle();
                    $busy = $this->isBusy($summary);
                    $sleepSeconds = $busy ? $this->config['busy_sleep_seconds'] : $this->nextIdleSleep($sleepSeconds);
                    $this->writeHeartbeat('cycle_finish', [
                        'busy' => $busy,
                        'next_sleep_seconds' => $sleepSeconds,
                        'summary' => $summary
                    ]);
                }catch(\Throwable $e){
                    $this->writeHeartbeat('fatal_exception', [
                        'error' => $this->sanitize($e->getMessage()),
                        'exception' => get_class($e)
                    ]);
                    $exitCode = self::EXIT_FATAL;
                    break;
                }

                $this->dispatchSignals();
                if(!$this->running || $this->limitReached($startedAt)){
                    break;
                }

                $this->interruptibleSleep($sleepSeconds);
            }
        }finally{
            $this->writeHeartbeat('shutdown', ['exit_code' => $exitCode]);
            $this->releaseLock();
        }

        return $exitCode;
    }

    public function stop(): void
    {
        $this->running = false;
    }

    public function handleSignal(int $signal): void
    {
        if(in_array($signal, [SIGTERM, SIGINT, SIGQUIT], true)){
            $this->running = false;
            $this->writeHeartbeat('signal_shutdown', ['signal' => $signal]);
            return;
        }

        if($signal === SIGHUP){
            $this->reloadRequested = true;
            $this->writeHeartbeat('signal_reload', ['signal' => $signal]);
        }
    }

    private function defaultConfig(): array
    {
        return [
            'daemon_id' => defined('WORKER_DAEMON_ID') ? WORKER_DAEMON_ID : '',
            'idle_sleep_seconds' => defined('WORKER_DAEMON_IDLE_SLEEP_SECONDS') ? (int) WORKER_DAEMON_IDLE_SLEEP_SECONDS : 5,
            'busy_sleep_seconds' => defined('WORKER_DAEMON_BUSY_SLEEP_SECONDS') ? (int) WORKER_DAEMON_BUSY_SLEEP_SECONDS : 1,
            'max_sleep_seconds' => defined('WORKER_DAEMON_MAX_SLEEP_SECONDS') ? (int) WORKER_DAEMON_MAX_SLEEP_SECONDS : 60,
            'max_runtime_seconds' => defined('WORKER_DAEMON_MAX_RUNTIME_SECONDS') ? (int) WORKER_DAEMON_MAX_RUNTIME_SECONDS : 0,
            'max_cycles' => defined('WORKER_DAEMON_MAX_CYCLES') ? (int) WORKER_DAEMON_MAX_CYCLES : 0,
            'memory_limit_bytes' => defined('WORKER_DAEMON_MEMORY_LIMIT_BYTES') ? (int) WORKER_DAEMON_MEMORY_LIMIT_BYTES : 0,
            'heartbeat_enabled' => defined('WORKER_DAEMON_HEARTBEAT_ENABLED') ? (bool) WORKER_DAEMON_HEARTBEAT_ENABLED : true,
            'heartbeat_file' => defined('WORKER_DAEMON_HEARTBEAT_FILE') ? WORKER_DAEMON_HEARTBEAT_FILE : __DIR__ . '/../../storage/logs/worker-daemon.jsonl',
            'lock_file' => defined('WORKER_DAEMON_LOCK_FILE') ? WORKER_DAEMON_LOCK_FILE : __DIR__ . '/../../storage/worker-daemon.lock',
            'require_cli' => true,
            'require_pcntl' => true,
            'simulate_sapi' => PHP_SAPI,
            'simulate_pcntl' => extension_loaded('pcntl'),
            'worker_options' => [
                'modo_teste' => false,
                'limite_campanhas' => defined('WORKER_DAEMON_LIMITE_CAMPANHAS') ? (int) WORKER_DAEMON_LIMITE_CAMPANHAS : 50,
                'limite_disparo_manual' => defined('WORKER_DAEMON_LIMITE_DISPARO_MANUAL') ? (int) WORKER_DAEMON_LIMITE_DISPARO_MANUAL : 20,
                'timeout_processando_minutos' => defined('WORKER_PROCESSING_TIMEOUT_MINUTES') ? (int) WORKER_PROCESSING_TIMEOUT_MINUTES : 15
            ]
        ];
    }

    private function validateRuntime(): ?int
    {
        if($this->config['require_cli'] && $this->config['simulate_sapi'] !== 'cli'){
            $this->writeHeartbeat('config_error', ['error' => 'execucao_apenas_cli']);
            return self::EXIT_CONFIG;
        }

        if($this->config['require_pcntl'] && !$this->config['simulate_pcntl']){
            $this->writeHeartbeat('config_error', ['error' => 'pcntl_indisponivel']);
            return self::EXIT_CONFIG;
        }

        return null;
    }

    private function registerSignals(): void
    {
        pcntl_async_signals(false);
        foreach([SIGTERM, SIGINT, SIGQUIT, SIGHUP] as $signal){
            pcntl_signal($signal, [$this, 'handleSignal']);
        }
    }

    private function dispatchSignals(): void
    {
        if($this->config['simulate_pcntl']){
            pcntl_signal_dispatch();
        }
    }

    private function acquireLock(): bool
    {
        $dir = dirname($this->config['lock_file']);
        if(!is_dir($dir)){ mkdir($dir, 0770, true); }
        $this->lockHandle = fopen($this->config['lock_file'], 'c+');
        if(!$this->lockHandle){ return false; }
        if(!flock($this->lockHandle, LOCK_EX | LOCK_NB)){ return false; }
        ftruncate($this->lockHandle, 0);
        fwrite($this->lockHandle, $this->daemonId);
        fflush($this->lockHandle);
        return true;
    }

    private function releaseLock(): void
    {
        if(is_resource($this->lockHandle)){
            flock($this->lockHandle, LOCK_UN);
            fclose($this->lockHandle);
        }
        $this->lockHandle = null;
        if(is_file($this->config['lock_file'])){ @unlink($this->config['lock_file']); }
    }

    private function executeCycle(): array
    {
        $factory = $this->workerFactory;
        $worker = $factory(array_merge($this->config['worker_options'], ['worker_id' => $this->daemonId]));
        return $worker->executarCiclo();
    }

    private function isBusy(array $summary): bool
    {
        if(($summary['lock_compartilhado'] ?? '') !== 'adquirido'){
            return false;
        }
        $json = json_encode($summary);
        preg_match_all('/"(processados|reservados|enviados|aceitos|erros|erros_temporarios|erros_definitivos|bloqueados|recuperados|total)":(\d+)/', (string) $json, $matches);
        foreach($matches[2] ?? [] as $value){ if((int) $value > 0){ return true; } }
        return false;
    }

    private function nextIdleSleep(int $current): int
    {
        $base = max(1, (int) $this->config['idle_sleep_seconds']);
        $max = max($base, (int) $this->config['max_sleep_seconds']);
        return min($max, max($base, $current * 2));
    }

    private function interruptibleSleep(int $seconds): void
    {
        $remaining = max(0, $seconds);
        while($remaining > 0 && $this->running){
            $this->dispatchSignals();
            if(!$this->running){ break; }
            ($this->sleeper)(1);
            $remaining--;
        }
    }

    private function limitReached(float $startedAt): bool
    {
        if($this->config['max_cycles'] > 0 && $this->cycle >= $this->config['max_cycles']){
            $this->writeHeartbeat('limit_reached', ['limit' => 'cycles']);
            return true;
        }
        if($this->config['max_runtime_seconds'] > 0 && microtime(true) - $startedAt >= $this->config['max_runtime_seconds']){
            $this->writeHeartbeat('limit_reached', ['limit' => 'runtime']);
            return true;
        }
        if($this->config['memory_limit_bytes'] > 0 && ($this->memoryProvider)() >= $this->config['memory_limit_bytes']){
            $this->writeHeartbeat('limit_reached', ['limit' => 'memory']);
            return true;
        }
        return false;
    }

    private function writeHeartbeat(string $event, array $data = []): void
    {
        if(!$this->config['heartbeat_enabled']){ return; }
        ($this->logger)([
            'timestamp' => date('c'),
            'event' => $event,
            'daemon_id' => $this->daemonId,
            'cycle' => $this->cycle,
            'pid' => getmypid(),
            'reload_requested' => $this->reloadRequested,
            'data' => $this->safeData($data)
        ]);
    }

    private function safeData(array $data): array
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        $json = preg_replace('/(access_token|token|authorization|bearer|senha|password|secret)[^,;\s"]*/i', '$1=***', (string) $json);
        $json = preg_replace('/[\r\n\t]+/', ' ', $json);
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function sanitize(string $message): string
    {
        $message = preg_replace('/(access_token|token|authorization|bearer|senha|password|secret)[^,;\s]*/i', '$1=***', $message);
        $message = preg_replace('/[\r\n\t]+/', ' ', $message);
        return trim(substr($message, 0, 600));
    }

    private function generateDaemonId(): string
    {
        $host = preg_replace('/[^a-zA-Z0-9_.-]/', '', gethostname() ?: 'worker') ?: 'worker';
        return $host . '-' . getmypid();
    }
}
