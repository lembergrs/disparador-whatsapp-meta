<?php

namespace Services;

use Core\Database;

class WorkerService
{
    private $modoTeste;
    private $limiteCampanhas;
    private $limiteDisparoManual;
    private $timeoutProcessandoMinutos;
    private $workerId;
    private $db;
    private $lockCompartilhado = false;
    private $campanhaQueue;
    private $disparoManualQueue;
    private $historyQueue;

    public function __construct(array $opcoes = [])
    {
        $this->modoTeste = (bool) ($opcoes['modo_teste'] ?? false);
        $this->limiteCampanhas = (int) ($opcoes['limite_campanhas'] ?? 50);
        $this->limiteDisparoManual = (int) ($opcoes['limite_disparo_manual'] ?? 20);
        $this->timeoutProcessandoMinutos = (int) ($opcoes['timeout_processando_minutos'] ?? (defined('WORKER_PROCESSING_TIMEOUT_MINUTES') ? WORKER_PROCESSING_TIMEOUT_MINUTES : 15));
        $this->workerId = $opcoes['worker_id'] ?? self::gerarWorkerId();
        $this->db = Database::getInstance();

        $validator = new WorkerOperationalValidatorService();
        $this->campanhaQueue = new CampanhaQueueService($this->modoTeste, $validator);
        $this->disparoManualQueue = new DisparoManualQueueService($this->modoTeste);
        $this->historyQueue = $opcoes['history_queue'] ?? new MetaCoexistenceHistoryQueueService();
    }

    public function __destruct()
    {
        $this->liberarLockCompartilhado();
    }

    public static function gerarWorkerId(): string
    {
        $hostname = gethostname() ?: 'worker';
        $hostname = preg_replace('/[^a-zA-Z0-9_.-]/', '', $hostname) ?: 'worker';
        $pid = function_exists('getmypid') ? (string) getmypid() : '0';
        $token = bin2hex(random_bytes(3));

        return $hostname . '-' . $pid . '-' . $token;
    }

    public function executarCiclo(): array
    {
        $inicioTimestamp = microtime(true);
        $inicio = date('Y-m-d H:i:s');

        $resumo = [
            'worker_id' => $this->workerId,
            'inicio' => $inicio,
            'fim' => null,
            'duracao_segundos' => null,
            'lotes_manuais' => [
                'processados' => 0,
                'reservados' => 0,
                'enviados' => 0,
                'erros' => 0,
                'bloqueados' => 0
            ],
            'campanhas' => [
                'campanhas_encontradas' => 0,
                'processadas' => 0,
                'reservados' => 0,
                'enviados' => 0,
                'erros_temporarios' => 0,
                'erros_definitivos' => 0,
                'bloqueados' => 0,
                'excecoes' => 0
            ],
            'recuperados' => [
                'manual' => 0,
                'campanhas' => 0,
                'total' => 0
            ],
            'coexistence_history' => ['recuperados'=>0,'reservados'=>0,'processados'=>0,'erros'=>0],
            'excecoes' => [],
            'lock_compartilhado' => 'nao_adquirido'
        ];

        $this->registrarLog('inicio_ciclo', ['worker_id' => $this->workerId]);

        if(!$this->adquirirLockCompartilhado()){
            $resumo['fim'] = date('Y-m-d H:i:s');
            $resumo['duracao_segundos'] = round(microtime(true) - $inicioTimestamp, 3);
            $this->registrarLog('lock_compartilhado_ocupado', $resumo);
            return $resumo;
        }

        $resumo['lock_compartilhado'] = 'adquirido';

        try{
            $resumo['recuperados']['manual'] = $this->disparoManualQueue->recuperarTravados($this->timeoutProcessandoMinutos);
        }catch(\Throwable $e){
            $this->registrarExcecao($resumo, 'recuperar_manual', $e);
        }

        try{
            $resumo['recuperados']['campanhas'] = $this->campanhaQueue->recuperarTravados($this->timeoutProcessandoMinutos);
        }catch(\Throwable $e){
            $this->registrarExcecao($resumo, 'recuperar_campanhas', $e);
        }

        $resumo['recuperados']['total'] = $resumo['recuperados']['manual'] + $resumo['recuperados']['campanhas'];

        try{
            $manual = $this->disparoManualQueue->processarPendentes($this->limiteDisparoManual, 'worker', $this->workerId);
            $resumo['lotes_manuais'] = [
                'processados' => (int) ($manual['processados'] ?? 0),
                'reservados' => (int) ($manual['reservados'] ?? 0),
                'enviados' => (int) ($manual['aceitos'] ?? 0),
                'erros' => (int) ($manual['erros'] ?? 0),
                'bloqueados' => (int) ($manual['bloqueados'] ?? 0)
            ];
        }catch(\Throwable $e){
            $this->registrarExcecao($resumo, 'disparo_manual', $e);
        }

        try{
            $campanhas = $this->campanhaQueue->processar($this->limiteCampanhas, $this->workerId);
            $resumo['campanhas'] = [
                'campanhas_encontradas' => (int) ($campanhas['campanhas_encontradas'] ?? 0),
                'processadas' => (int) ($campanhas['processadas'] ?? 0),
                'reservados' => (int) ($campanhas['reservados'] ?? 0),
                'enviados' => (int) ($campanhas['enviados'] ?? 0),
                'erros_temporarios' => (int) ($campanhas['erros_temporarios'] ?? 0),
                'erros_definitivos' => (int) ($campanhas['erros_definitivos'] ?? 0),
                'bloqueados' => (int) ($campanhas['bloqueados'] ?? 0),
                'excecoes' => (int) ($campanhas['excecoes'] ?? 0)
            ];
        }catch(\Throwable $e){
            $this->registrarExcecao($resumo, 'campanhas', $e);
        }

        try{
            $resumo['coexistence_history'] = $this->historyQueue->processarPendentes(5, $this->workerId);
        }catch(\Throwable $e){
            $this->registrarExcecao($resumo, 'coexistence_history', $e);
        }

        $this->liberarLockCompartilhado();

        $resumo['fim'] = date('Y-m-d H:i:s');
        $resumo['duracao_segundos'] = round(microtime(true) - $inicioTimestamp, 3);

        $this->registrarLog('fim_ciclo', $resumo);

        return $resumo;
    }


    private function adquirirLockCompartilhado(): bool
    {
        $nome = $this->nomeLockCompartilhado();
        $stmt = $this->db->prepare('SELECT GET_LOCK(?, 0)');
        $stmt->execute([$nome]);
        $adquirido = (int) $stmt->fetchColumn() === 1;
        $this->lockCompartilhado = $adquirido;

        return $adquirido;
    }

    private function liberarLockCompartilhado(): void
    {
        if(!$this->lockCompartilhado){
            return;
        }

        $stmt = $this->db->prepare('SELECT RELEASE_LOCK(?)');
        $stmt->execute([$this->nomeLockCompartilhado()]);
        $this->lockCompartilhado = false;
    }

    private function nomeLockCompartilhado(): string
    {
        $dbName = defined('DB_NAME') ? DB_NAME : 'disparador';
        $ambiente = defined('APP_ENV') ? APP_ENV : 'production';

        return 'disparador_worker_' . md5($ambiente . '_' . $dbName);
    }

    public function getWorkerId(): string
    {
        return $this->workerId;
    }

    private function registrarExcecao(array &$resumo, string $etapa, \Throwable $e): void
    {
        $mensagem = $this->sanitizarMensagem($e->getMessage());
        $resumo['excecoes'][] = [
            'etapa' => $etapa,
            'mensagem' => $mensagem
        ];

        $this->registrarLog('excecao_' . $etapa, [
            'worker_id' => $this->workerId,
            'mensagem' => $mensagem
        ]);
    }

    private function registrarLog(string $acao, array $dados = []): void
    {
        $diretorio = __DIR__ . '/../../storage/logs';

        if(!is_dir($diretorio)){
            mkdir($diretorio, 0770, true);
        }

        $linha = [
            'data' => date('Y-m-d H:i:s'),
            'acao' => $acao,
            'worker_id' => $this->workerId,
            'dados' => $this->dadosSeguros($dados)
        ];

        file_put_contents(
            $diretorio . '/worker.log',
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
