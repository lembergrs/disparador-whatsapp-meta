<?php

namespace Services\Tasks;

class TaskDispatcher
{
    private $registry;

    public function __construct(TaskRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function executar(array $tarefa): void
    {
        $payload = json_decode((string)($tarefa['TAG_Payload'] ?? ''), true);
        if(!is_array($payload) || json_last_error() !== JSON_ERROR_NONE){
            throw new TaskPermanentFailureException('Payload persistido inválido.');
        }
        $this->registry->handler($tarefa['TAG_Tipo'] ?? '')->executar($payload);
    }
}
