<?php

namespace Services\Tasks;

class TaskSchedulerLogger
{
    private $arquivo;

    public function __construct($arquivo)
    {
        $this->arquivo = (string)$arquivo;
    }

    public function __invoke(array $linha): void
    {
        $nivel = $this->nivel($linha['status'] ?? '', $linha['erro_codigo'] ?? null);
        $registro = [
            'data' => $linha['data'] ?? date('c'),
            'nivel' => $nivel,
            'tarefa_id' => isset($linha['tarefa_id']) ? (int)$linha['tarefa_id'] : null,
            'tipo' => isset($linha['tipo']) ? (string)$linha['tipo'] : null,
            'status' => isset($linha['status']) ? (string)$linha['status'] : null,
            'tentativa' => isset($linha['tentativa']) ? (int)$linha['tentativa'] : null,
            'duracao_ms' => isset($linha['duracao_ms']) ? (int)$linha['duracao_ms'] : null,
            'erro_codigo' => isset($linha['erro_codigo']) ? $this->sanitizarCodigo($linha['erro_codigo']) : null,
        ];
        $registro = array_filter($registro, static fn($v) => $v !== null);
        $this->gravar($registro);
    }

    public function operacional($codigo): void
    {
        $this->gravar([
            'data' => date('c'),
            'nivel' => 'ERROR',
            'status' => 'erro_operacional',
            'erro_codigo' => $this->sanitizarCodigo($codigo),
        ]);
    }

    private function nivel($status, $codigo): string
    {
        if($status === 'falha') return 'ERROR';
        if($status === 'retry' || $codigo === 'lease_expirado_recuperado') return 'WARNING';
        return 'INFO';
    }

    private function sanitizarCodigo($codigo): string
    {
        $codigo = preg_replace('/[^A-Za-z0-9_\-.]/', '_', (string)$codigo);
        return substr($codigo, 0, 80);
    }

    private function gravar(array $registro): void
    {
        $dir = dirname($this->arquivo);
        if(!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)){
            throw new TaskSchedulerLoggingException('Não foi possível preparar diretório de log.');
        }
        $json = json_encode($registro, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if($json === false || file_put_contents($this->arquivo, $json . PHP_EOL, FILE_APPEND | LOCK_EX) === false){
            throw new TaskSchedulerLoggingException('Não foi possível gravar log do scheduler.');
        }
    }
}
