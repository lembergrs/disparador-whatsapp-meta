<?php

namespace Services\Tasks;

use Services\FinanceiroRecorrenciaService;

class FinanceiroGerarCobrancasRecorrentesHandler implements TaskHandlerInterface
{
    private $recorrencia;

    public function __construct(FinanceiroRecorrenciaService $recorrencia = null)
    {
        $this->recorrencia = $recorrencia ?: new FinanceiroRecorrenciaService();
    }

    public function executar(array $payload): void
    {
        if($payload !== []){
            throw new TaskPermanentFailureException('A tarefa financeira diária não aceita payload.');
        }

        $resultado = $this->recorrencia->gerarCobrancasRecorrentes();
        if((int) ($resultado['erros'] ?? 0) > 0){
            throw new TaskRetryException('A geração recorrente terminou com falhas transitórias.');
        }
    }
}
