<?php

namespace Services\Tasks;

class TaskRegistry
{
    private $tipos;

    public function __construct(array $tipos = null)
    {
        $this->tipos = $tipos ?? [
            'teste_scheduler'=>TesteSchedulerHandler::class,
            'indicacao_confirmacao'=>IndicacaoConfirmacaoHandler::class,
            'financeiro_gerar_cobrancas_recorrentes'=>FinanceiroGerarCobrancasRecorrentesHandler::class,
        ];
    }

    public function possui($tipo): bool
    {
        return is_string($tipo) && isset($this->tipos[$tipo]);
    }

    public function handler($tipo): TaskHandlerInterface
    {
        if(!$this->possui($tipo)) throw new \InvalidArgumentException('Tipo de tarefa não permitido.');
        $classe = $this->tipos[$tipo];
        $handler = is_object($classe) ? $classe : new $classe();
        if(!$handler instanceof TaskHandlerInterface) throw new \LogicException('Handler de tarefa inválido.');
        return $handler;
    }

    public function tipos(): array
    {
        return array_keys($this->tipos);
    }
}
