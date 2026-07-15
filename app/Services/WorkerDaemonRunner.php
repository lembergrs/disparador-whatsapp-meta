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
    private $daemonId;
    private $cycle = 0;
    private $lockHandle = null;
    private $lockAcquired = false;
    private $startedAt = 0.0;
    private $lastHeartbeatAt = null;
    private $lastCycleDuration = null;

    public function __construct(array $config = [], ?callable $workerFactory = null, ?callable $logger = null, ?callable $sleeper = null, ?callable $memoryProvider = null)
    {
        $this->config = $this->normalizeConfig(array_merge($this->defaultConfig(), $config));
        $this->workerFactory = $workerFactory ?: function(array $options){ return new WorkerService($options); };
        $this->logger = $logger ?: function(array $entry): void { $this->writeJsonLine($entry); };
        $this->sleeper = $sleeper ?: function(float $seconds): void { usleep((int) round($seconds * 1000000)); };
        $this->memoryProvider = $memoryProvider ?: function(): int { return memory_get_usage(true); };
        $this->daemonId = $this->config['daemon_id'] !== '' ? $this->config['daemon_id'] : $this->generateDaemonId();
    }

    public function run(): int
    {
        $this->startedAt = $this->now();

        $validation = $this->validateRuntime();
        if($validation !== null){
            return $validation;
        }

        if(!$this->acquireLock()){
            $this->emitHeartbeat('warning', 'lock_ocupado', ['motivo' => 'daemon_ja_em_execucao'], true);
            return self::EXIT_LOCKED;
        }

        $exitCode = self::EXIT_OK;
        $sleepSeconds = $this->config['idle_sleep_seconds'];

        try{
            $this->registerSignals();

            while($this->running){
                $this->dispatchSignals();

                if(!$this->running || $this->limitReached()){
                    break;
                }

                $this->cycle++;
                $cycleStart = $this->now();
                $this->emitHeartbeat('info', 'cycle_start');

                try{
                    $summary = $this->executeCycle();
                    $this->lastCycleDuration = round($this->now() - $cycleStart, 6);
                    $busy = $this->isBusy($summary);
                    $sleepSeconds = $busy ? $this->config['busy_sleep_seconds'] : $this->nextIdleSleep($sleepSeconds);
                    $this->emitHeartbeat('info', 'cycle_finish', [
                        'busy' => $busy,
                        'next_sleep_seconds' => $sleepSeconds,
                        'cycle_duration_seconds' => $this->lastCycleDuration,
                        'summary' => $summary
                    ], true);
                }catch(\Throwable $e){
                    $exitCode = self::EXIT_FATAL;
                    $this->emitHeartbeat('critical', 'fatal_exception', [
                        'error' => $this->sanitizeScalar($e->getMessage()),
                        'exception' => get_class($e)
                    ], true);
                    break;
                }

                $this->dispatchSignals();
                if(!$this->running || $this->limitReached()){
                    break;
                }

                $this->interruptibleSleep($sleepSeconds);
            }
        }catch(\Throwable $e){
            $exitCode = self::EXIT_FATAL;
            $this->emitHeartbeat('critical', 'daemon_exception', [
                'error' => $this->sanitizeScalar($e->getMessage()),
                'exception' => get_class($e)
            ], true);
        }finally{
            $this->emitHeartbeat($exitCode === self::EXIT_OK ? 'info' : 'critical', 'shutdown', ['exit_code' => $exitCode], true);
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
            $this->emitHeartbeat('info', 'signal_shutdown', ['signal' => $signal], true);
            return;
        }

        if($signal === SIGHUP){
            $this->emitHeartbeat('warning', 'reload_unsupported', [
                'signal' => $signal,
                'acao' => 'nenhuma_configuracao_recarregada'
            ], true);
        }
    }

    private function defaultConfig(): array
    {
        return [
            'daemon_id' => defined('WORKER_DAEMON_ID') ? WORKER_DAEMON_ID : '',
            'idle_sleep_seconds' => defined('WORKER_DAEMON_IDLE_SLEEP_SECONDS') ? WORKER_DAEMON_IDLE_SLEEP_SECONDS : 5,
            'busy_sleep_seconds' => defined('WORKER_DAEMON_BUSY_SLEEP_SECONDS') ? WORKER_DAEMON_BUSY_SLEEP_SECONDS : 1,
            'max_sleep_seconds' => defined('WORKER_DAEMON_MAX_SLEEP_SECONDS') ? WORKER_DAEMON_MAX_SLEEP_SECONDS : 60,
            'sleep_granularity_seconds' => defined('WORKER_DAEMON_SLEEP_GRANULARITY_SECONDS') ? WORKER_DAEMON_SLEEP_GRANULARITY_SECONDS : 1,
            'max_runtime_seconds' => defined('WORKER_DAEMON_MAX_RUNTIME_SECONDS') ? WORKER_DAEMON_MAX_RUNTIME_SECONDS : 0,
            'max_cycles' => defined('WORKER_DAEMON_MAX_CYCLES') ? WORKER_DAEMON_MAX_CYCLES : 0,
            'max_memory_mb' => defined('WORKER_DAEMON_MAX_MEMORY_MB') ? WORKER_DAEMON_MAX_MEMORY_MB : 0,
            'heartbeat_seconds' => defined('WORKER_DAEMON_HEARTBEAT_SECONDS') ? WORKER_DAEMON_HEARTBEAT_SECONDS : 30,
            'heartbeat_file' => defined('WORKER_DAEMON_HEARTBEAT_FILE') ? WORKER_DAEMON_HEARTBEAT_FILE : __DIR__ . '/../../storage/logs/worker-daemon.jsonl',
            'lock_file' => defined('WORKER_DAEMON_LOCK_FILE') ? WORKER_DAEMON_LOCK_FILE : __DIR__ . '/../../storage/worker-daemon.lock',
            'require_cli' => true,
            'require_pcntl' => true,
            'simulate_sapi' => PHP_SAPI,
            'simulate_pcntl' => extension_loaded('pcntl'),
            'worker_options' => [
                'modo_teste' => false,
                'limite_campanhas' => defined('WORKER_DAEMON_LIMITE_CAMPANHAS') ? WORKER_DAEMON_LIMITE_CAMPANHAS : 50,
                'limite_disparo_manual' => defined('WORKER_DAEMON_LIMITE_DISPARO_MANUAL') ? WORKER_DAEMON_LIMITE_DISPARO_MANUAL : 20,
                'timeout_processando_minutos' => defined('WORKER_PROCESSING_TIMEOUT_MINUTES') ? WORKER_PROCESSING_TIMEOUT_MINUTES : 15
            ]
        ];
    }

    private function normalizeConfig(array $config): array
    {
        $config['daemon_id'] = trim((string) ($config['daemon_id'] ?? ''));
        $config['idle_sleep_seconds'] = max(1.0, (float) ($config['idle_sleep_seconds'] ?? 5));
        $config['busy_sleep_seconds'] = max(1.0, (float) ($config['busy_sleep_seconds'] ?? 1));
        $config['max_sleep_seconds'] = max($config['idle_sleep_seconds'], (float) ($config['max_sleep_seconds'] ?? 60));
        $config['sleep_granularity_seconds'] = max(0.1, (float) ($config['sleep_granularity_seconds'] ?? 1));
        $config['max_runtime_seconds'] = max(0.0, (float) ($config['max_runtime_seconds'] ?? 0));
        $config['max_cycles'] = max(0, (int) ($config['max_cycles'] ?? 0));
        $config['max_memory_mb'] = max(0.0, (float) ($config['max_memory_mb'] ?? 0));
        $config['heartbeat_seconds'] = max(0.0, (float) ($config['heartbeat_seconds'] ?? 30));
        $config['heartbeat_file'] = (string) ($config['heartbeat_file'] ?? '');
        $config['lock_file'] = (string) ($config['lock_file'] ?? '');
        $config['worker_options']['limite_campanhas'] = max(1, (int) ($config['worker_options']['limite_campanhas'] ?? 50));
        $config['worker_options']['limite_disparo_manual'] = max(1, (int) ($config['worker_options']['limite_disparo_manual'] ?? 20));
        $config['worker_options']['timeout_processando_minutos'] = max(1, (int) ($config['worker_options']['timeout_processando_minutos'] ?? 15));

        return $config;
    }

    private function validateRuntime(): ?int
    {
        if($this->config['require_cli'] && $this->config['simulate_sapi'] !== 'cli'){
            $this->emitHeartbeat('critical', 'config_error', ['error' => 'execucao_apenas_cli'], true);
            return self::EXIT_CONFIG;
        }

        if($this->config['require_pcntl'] && !$this->config['simulate_pcntl']){
            $this->emitHeartbeat('critical', 'config_error', ['error' => 'pcntl_indisponivel'], true);
            return self::EXIT_CONFIG;
        }

        if($this->config['lock_file'] === ''){
            $this->emitHeartbeat('critical', 'config_error', ['error' => 'lock_file_vazio'], true);
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
        if(!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)){
            return false;
        }

        if(!is_writable($dir)){
            return false;
        }

        $this->lockHandle = fopen($this->config['lock_file'], 'c+');
        if(!$this->lockHandle){
            return false;
        }

        if(!flock($this->lockHandle, LOCK_EX | LOCK_NB)){
            fclose($this->lockHandle);
            $this->lockHandle = null;
            return false;
        }

        $this->lockAcquired = true;
        ftruncate($this->lockHandle, 0);
        fwrite($this->lockHandle, $this->daemonId . PHP_EOL . (string) getmypid());
        fflush($this->lockHandle);

        return true;
    }

    private function releaseLock(): void
    {
        if(is_resource($this->lockHandle)){
            if($this->lockAcquired){
                flock($this->lockHandle, LOCK_UN);
            }
            fclose($this->lockHandle);
        }

        if($this->lockAcquired && is_file($this->config['lock_file'])){
            @unlink($this->config['lock_file']);
        }

        $this->lockHandle = null;
        $this->lockAcquired = false;
    }

    private function executeCycle(): array
    {
        $factory = $this->workerFactory;
        $worker = $factory(array_merge($this->config['worker_options'], ['worker_id' => $this->daemonId . '-c' . $this->cycle]));
        $summary = $worker->executarCiclo();

        return is_array($summary) ? $summary : [];
    }

    private function isBusy(array $summary): bool
    {
        if(($summary['lock_compartilhado'] ?? '') !== 'adquirido'){
            return false;
        }

        $manual = $summary['lotes_manuais'] ?? [];
        $campanhas = $summary['campanhas'] ?? [];
        $recuperados = $summary['recuperados'] ?? [];

        $fields = [
            [$manual, ['processados', 'reservados', 'enviados', 'erros', 'bloqueados']],
            [$campanhas, ['campanhas_encontradas', 'processadas', 'reservados', 'enviados', 'erros_temporarios', 'erros_definitivos', 'bloqueados', 'excecoes']],
            [$recuperados, ['manual', 'campanhas', 'total']]
        ];

        foreach($fields as $group){
            [$data, $keys] = $group;
            if(!is_array($data)){
                continue;
            }
            foreach($keys as $key){
                if((int) ($data[$key] ?? 0) > 0){
                    return true;
                }
            }
        }

        return !empty($summary['excecoes']) && is_array($summary['excecoes']);
    }

    private function nextIdleSleep(float $current): float
    {
        $base = $this->config['idle_sleep_seconds'];
        $max = $this->config['max_sleep_seconds'];

        return min($max, max($base, $current * 2));
    }

    private function interruptibleSleep(float $seconds): void
    {
        $remaining = max(0.0, $seconds);
        $step = $this->config['sleep_granularity_seconds'];

        while($remaining > 0 && $this->running){
            $this->dispatchSignals();
            if(!$this->running){
                break;
            }

            $slice = min($step, $remaining);
            ($this->sleeper)($slice);
            $remaining -= $slice;
        }
    }

    private function limitReached(): bool
    {
        if($this->config['max_cycles'] > 0 && $this->cycle >= $this->config['max_cycles']){
            $this->emitHeartbeat('info', 'limit_reached', ['limit' => 'cycles', 'cycles' => $this->cycle], true);
            return true;
        }

        $uptime = $this->uptime();
        if($this->config['max_runtime_seconds'] > 0 && $uptime >= $this->config['max_runtime_seconds']){
            $this->emitHeartbeat('info', 'limit_reached', ['limit' => 'runtime', 'uptime_seconds' => $uptime], true);
            return true;
        }

        $memory = ($this->memoryProvider)();
        $limitBytes = $this->config['max_memory_mb'] > 0 ? (int) round($this->config['max_memory_mb'] * 1024 * 1024) : 0;
        if($limitBytes > 0 && $memory >= $limitBytes){
            $this->emitHeartbeat('info', 'limit_reached', [
                'limit' => 'memory',
                'memory_usage_bytes' => $memory,
                'memory_limit_bytes' => $limitBytes
            ], true);
            return true;
        }

        return false;
    }

    private function emitHeartbeat(string $level, string $event, array $data = [], bool $force = false): void
    {
        if($this->config['heartbeat_seconds'] <= 0){
            return;
        }

        $now = $this->now();
        if(!$force && $this->lastHeartbeatAt !== null && ($now - $this->lastHeartbeatAt) < $this->config['heartbeat_seconds']){
            return;
        }

        $this->lastHeartbeatAt = $now;
        $entry = [
            'timestamp' => date('c'),
            'level' => $level,
            'event' => $event,
            'daemon_id' => $this->daemonId,
            'cycle' => $this->cycle,
            'pid' => getmypid(),
            'uptime_seconds' => round($this->uptime(), 6),
            'memory_usage_bytes' => ($this->memoryProvider)(),
            'last_cycle_duration_seconds' => $this->lastCycleDuration,
            'data' => $this->safeData($data)
        ];

        try{
            ($this->logger)($entry);
        }catch(\Throwable $e){
            throw new \RuntimeException('Falha ao emitir heartbeat: ' . $this->sanitizeScalar($e->getMessage()), 0, $e);
        }
    }

    private function writeJsonLine(array $entry): void
    {
        $file = $this->config['heartbeat_file'];
        if($file === 'php://stdout' || $file === 'php://stderr'){
            $target = $file;
        }else{
            $dir = dirname($file);
            if(!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)){
                throw new \RuntimeException('Diretório de heartbeat indisponível.');
            }
            $target = $file;
        }

        $json = json_encode($entry, JSON_UNESCAPED_UNICODE);
        if($json === false){
            $fallback = [
                'timestamp' => date('c'),
                'level' => 'critical',
                'event' => 'json_encode_failed',
                'daemon_id' => $this->daemonId,
                'cycle' => $this->cycle,
                'pid' => getmypid(),
                'data' => ['error' => json_last_error_msg()]
            ];
            $json = json_encode($fallback, JSON_UNESCAPED_UNICODE);
        }

        if($json === false || file_put_contents($target, $json . PHP_EOL, FILE_APPEND) === false){
            throw new \RuntimeException('Não foi possível gravar heartbeat.');
        }
    }

    private function safeData($data)
    {
        if(is_array($data)){
            $safe = [];
            foreach($data as $key => $value){
                $safeKey = $this->sanitizeKey((string) $key);
                if($this->isSensitiveKey((string) $key)){
                    $safe[$safeKey] = '***';
                }elseif($this->isPayloadKey((string) $key)){
                    $safe[$safeKey] = '[payload omitido]';
                }else{
                    $safe[$safeKey] = $this->safeData($value);
                }
            }
            return $safe;
        }

        if(is_object($data)){
            return '[object ' . get_class($data) . ']';
        }

        if(is_string($data)){
            return $this->sanitizeScalar($data);
        }

        if(is_int($data) || is_float($data) || is_bool($data) || $data === null){
            return $data;
        }

        return '[valor omitido]';
    }

    private function sanitizeKey(string $key): string
    {
        $key = preg_replace('/[\r\n\t]+/', ' ', $key);
        return trim(substr((string) $key, 0, 120));
    }

    private function isSensitiveKey(string $key): bool
    {
        return preg_match('/(access_token|token|authorization|bearer|senha|password|secret)/i', $key) === 1;
    }

    private function isPayloadKey(string $key): bool
    {
        return preg_match('/payload/i', $key) === 1;
    }

    private function sanitizeScalar(string $message): string
    {
        $message = preg_replace('/Bearer\s+[A-Za-z0-9._~+\/-]+=*/i', 'Bearer ***', $message);
        $message = preg_replace('/(access_token|token|authorization|bearer|senha|password|secret)\s*[:=]?\s*[^,;\s]*/i', '$1=***', $message);
        $message = preg_replace('/[\r\n\t]+/', ' ', $message);

        return trim(substr($message, 0, 600));
    }

    private function uptime(): float
    {
        if($this->startedAt <= 0){
            return 0.0;
        }

        return max(0.0, $this->now() - $this->startedAt);
    }

    private function now(): float
    {
        if(function_exists('hrtime')){
            return hrtime(true) / 1000000000;
        }

        return microtime(true);
    }

    private function generateDaemonId(): string
    {
        $host = preg_replace('/[^a-zA-Z0-9_.-]/', '', gethostname() ?: 'worker') ?: 'worker';
        return $host . '-' . getmypid();
    }
}
