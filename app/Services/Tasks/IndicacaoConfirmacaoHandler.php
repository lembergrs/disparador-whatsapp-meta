<?php

namespace Services\Tasks;

use Services\Indicacao\IndicacaoElegibilidadeService;

class IndicacaoConfirmacaoHandler implements TaskHandlerInterface
{
    private $elegibilidade;

    public function __construct(IndicacaoElegibilidadeService $elegibilidade = null)
    {
        $this->elegibilidade = $elegibilidade ?: IndicacaoElegibilidadeService::padrao();
    }

    public function executar(array $payload): void
    {
        if(count($payload) !== 1 || !isset($payload['indicacao_id']) || filter_var($payload['indicacao_id'], FILTER_VALIDATE_INT) === false || (int)$payload['indicacao_id'] <= 0){
            throw new TaskPermanentFailureException('Identificador de indicação inválido.');
        }
        $this->elegibilidade->processarConfirmacao((int)$payload['indicacao_id']);
    }
}
