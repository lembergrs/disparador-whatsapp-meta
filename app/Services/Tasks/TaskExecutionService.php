<?php

namespace Services\Tasks;

class TaskExecutionService
{
    private $processor;
    private $limiteMaximo;

    public function __construct(TaskProcessor $processor, $limiteMaximo = 50)
    {
        $this->processor = $processor;
        $this->limiteMaximo = max(1, min(500, (int)$limiteMaximo));
    }

    public function processarSobDemanda($limite = 1, $workerId = null): array
    {
        $limite = max(1, min($this->limiteMaximo, (int)$limite));
        return $this->processor->processarLote($limite, $workerId);
    }
}
