<?php

namespace Services;

class WorkerDaemonRunner
{
    private $workerFactory;
    private $sleepCallback;
    private $timeProvider;
    private $memoryProvider;
    private $logger;
    private $shutdownSolicitado = false;
    private $shutdownMotivo = null;
    private $cycle = 0;
    private $inicioTimestamp;
    private $ultimoHeartbeat = 0;
    private $ultimoResumo = null;
    private $ultimoCicloDuracao = 0.0;

    public function __construct(array $opcoes = [])
    {
        $this->workerFactory = $opcoes['worker_factory'] ?? function(){
            return new WorkerService();
        };
        $this->sleepCallback = $opcoes['sleep_callback'] ?? 'sleep';
        $this->timeProvider = $opcoes['time_provider'] ?? 'time';
        $this->memoryProvider = $opcoes['memory_provider'] ?? function(){
            return round(memory_get_usage(true) / 1048576, 2);
        };
        $this->logger = $opcoes['logger'] ?? [$this, 'registrarLogPadrao'];
    }

    public function executar(): int
    {
        $this->inicioTimestamp = $this->agora();
        $this->ultimoHeartbeat = $this->inicioTimestamp;
        $this->registrarHandlersSinais();
        $this->log('info', 'worker.daemon.started', [
            'pid' => function_exists('getmypid') ? getmypid() : null,
            'memory_mb' => $this->memoriaAtualMb()
        ]);

        try{
            while(!$this->shutdownSolicitado){
                $this->despacharSinais();

                if($this->shutdownSolicitado){
                    break;
                }

                $this->cycle++;
                $cicloInicio = microtime(true);
                $worker = $this->criarWorkerService();
                $resumo = $worker->executarCiclo();
                $this->ultimoCicloDuracao = round((microtime(true) - $cicloInicio) * 1000, 3);
                $this->ultimoResumo = $resumo;

                $this->log('info', 'worker.cycle.finished', [
                    'worker_id' => $resumo['worker_id'] ?? (method_exists($worker, 'getWorkerId') ? $worker->getWorkerId() : null),
                    'cycle' => $this->cycle,
                    'duration_ms' => $this->ultimoCicloDuracao,
                    'memory_mb' => $this->memoriaAtualMb(),
                    'summary' => $resumo
                ]);

                $this->emitirHeartbeatSeNecessario();

                $motivoLimite = $this->motivoLimiteAtingido();
                if($motivoLimite !== null){
                    $this->shutdownSolicitado = true;
                    $this->shutdownMotivo = $motivoLimite;
                    break;
                }

                $sleep = $this->calcularSleep($resumo);
                $this->dormirInterrompivel($sleep);
            }
        }catch(\Throwable $e){
            $this->log('critical', 'worker.daemon.fatal_exception', [
                'cycle' => $this->cycle,
                'message' => $this->sanitizarMensagem($e->getMessage()),
                'memory_mb' => $this->memoriaAtualMb()
            ]);
            $this->shutdownSolicitado = true;
            $this->shutdownMotivo = 'fatal_exception';
            $this->liberarRecursos();
            return 1;
        }

        $this->liberarRecursos();
        $this->log('info', 'worker.daemon.stopped', [
            'cycle' => $this->cycle,
            'reason' => $this->shutdownMotivo ?: 'shutdown',
            'uptime_seconds' => $this->uptime(),
            'memory_mb' => $this->memoriaAtualMb()
        ]);

        return 0;
    }

    public function solicitarShutdown(string $motivo = 'signal'): void
    {
        $this->shutdownSolicitado = true;
        $this->shutdownMotivo = $motivo;
    }

    public function calcularSleep(array $resumo): int
    {
        if(!empty($resumo['excecoes'])){
            return min((int) WORKER_ERROR_SLEEP_SECONDS, (int) WORKER_ERROR_MAX_SLEEP_SECONDS);
        }

        if(($resumo['lock_compartilhado'] ?? null) === 'nao_adquirido'){
            return (int) WORKER_LOCK_BUSY_SLEEP_SECONDS;
        }

        if($this->houveTrabalho($resumo)){
            return (int) WORKER_BUSY_SLEEP_SECONDS;
        }

        return (int) WORKER_IDLE_SLEEP_SECONDS;
    }

    private function criarWorkerService()
    {
        $factory = $this->workerFactory;
        return $factory();
    }

    private function houveTrabalho(array $resumo): bool
    {
        $manual = $resumo['lotes_manuais'] ?? [];
        $campanhas = $resumo['campanhas'] ?? [];
        $recuperados = $resumo['recuperados'] ?? [];

        $campos = [
            $manual['processados'] ?? 0,
            $manual['reservados'] ?? 0,
            $manual['enviados'] ?? 0,
            $manual['erros'] ?? 0,
            $manual['bloqueados'] ?? 0,
            $campanhas['reservados'] ?? 0,
            $campanhas['enviados'] ?? 0,
            $campanhas['erros_temporarios'] ?? 0,
            $campanhas['erros_definitivos'] ?? 0,
            $campanhas['bloqueados'] ?? 0,
            $recuperados['total'] ?? 0,
        ];

        foreach($campos as $valor){
            if((int) $valor > 0){
                return true;
            }
        }

        return false;
    }

    private function dormirInterrompivel(int $segundos): void
    {
        $segundos = max(0, $segundos);
        $sleep = $this->sleepCallback;

        $dormido = 0;
        $intervaloSinal = min((int) WORKER_BUSY_SLEEP_SECONDS, $segundos);

        if($intervaloSinal <= 0){
            return;
        }

        while($dormido < $segundos){
            if($this->shutdownSolicitado){
                return;
            }

            $fatia = min($intervaloSinal, $segundos - $dormido);
            $sleep($fatia);
            $dormido += $fatia;
            $this->despacharSinais();
        }
    }

    private function registrarHandlersSinais(): void
    {
        if(!function_exists('pcntl_signal')){
            return;
        }

        pcntl_signal(SIGTERM, function(){ $this->solicitarShutdown('SIGTERM'); });
        pcntl_signal(SIGINT, function(){ $this->solicitarShutdown('SIGINT'); });
        pcntl_signal(SIGQUIT, function(){ $this->solicitarShutdown('SIGQUIT'); });
        pcntl_signal(SIGHUP, function(){ $this->solicitarShutdown('SIGHUP'); });
    }

    private function despacharSinais(): void
    {
        if(function_exists('pcntl_signal_dispatch')){
            pcntl_signal_dispatch();
        }
    }

    private function emitirHeartbeatSeNecessario(): void
    {
        $agora = $this->agora();
        if(((int) WORKER_HEARTBEAT_SECONDS) <= 0){
            return;
        }

        if(($agora - $this->ultimoHeartbeat) < (int) WORKER_HEARTBEAT_SECONDS){
            return;
        }

        $this->ultimoHeartbeat = $agora;
        $this->log('info', 'worker.heartbeat', [
            'cycle' => $this->cycle,
            'uptime_seconds' => $this->uptime(),
            'memory_mb' => $this->memoriaAtualMb(),
            'last_cycle_duration_ms' => $this->ultimoCicloDuracao,
            'last_summary' => $this->ultimoResumo
        ]);
    }

    private function motivoLimiteAtingido(): ?string
    {
        if(((int) WORKER_MAX_CYCLES) > 0 && $this->cycle >= (int) WORKER_MAX_CYCLES){
            return 'max_cycles';
        }

        if(((int) WORKER_MAX_RUNTIME_SECONDS) > 0 && $this->uptime() >= (int) WORKER_MAX_RUNTIME_SECONDS){
            return 'max_runtime';
        }

        if(((int) WORKER_MAX_MEMORY_MB) > 0 && $this->memoriaAtualMb() >= (int) WORKER_MAX_MEMORY_MB){
            return 'max_memory';
        }

        return null;
    }

    private function liberarRecursos(): void
    {
        $this->despacharSinais();
    }

    private function uptime(): int
    {
        return max(0, $this->agora() - (int) $this->inicioTimestamp);
    }

    private function agora(): int
    {
        $provider = $this->timeProvider;
        return (int) $provider();
    }

    private function memoriaAtualMb(): float
    {
        $provider = $this->memoryProvider;
        return (float) $provider();
    }

    private function log(string $level, string $event, array $dados = []): void
    {
        $logger = $this->logger;
        $logger($level, $event, $this->dadosSeguros($dados));
    }

    public function registrarLogPadrao(string $level, string $event, array $dados = []): void
    {
        $diretorio = __DIR__ . '/../../storage/logs';

        if(!is_dir($diretorio)){
            mkdir($diretorio, 0770, true);
        }

        $linha = [
            'ts' => date('c'),
            'level' => $level,
            'event' => $event,
            'cycle' => $this->cycle,
            'dados' => $dados
        ];

        file_put_contents(
            $diretorio . '/worker-daemon.log',
            json_encode($linha, JSON_UNESCAPED_UNICODE) . PHP_EOL,
            FILE_APPEND
        );
    }

    private function dadosSeguros(array $dados): array
    {
        $json = json_encode($dados, JSON_UNESCAPED_UNICODE);
        $json = preg_replace('/(access_token|token|authorization|bearer|senha|password|secret)[^,;\s"]*/i', '$1=***', (string) $json);
        $decodificado = json_decode($json, true);

        return is_array($decodificado) ? $decodificado : [];
    }

    private function sanitizarMensagem(string $mensagem): string
    {
        $mensagem = preg_replace('/(access_token|token|authorization|bearer|senha|password|secret)[^,;\s]*/i', '$1=***', $mensagem);
        $mensagem = preg_replace('/[\r\n\t]+/', ' ', $mensagem);

        return trim(substr($mensagem, 0, 600));
    }
}
