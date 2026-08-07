<?php

namespace Services\Tasks;

class TesteSchedulerHandler implements TaskHandlerInterface
{
    public function executar(array $payload): void
    {
        $resultado = $payload['resultado'] ?? 'sucesso';
        if($resultado === 'retry') throw new TaskRetryException('Falha transitória controlada do handler de teste.');
        if($resultado === 'falha') throw new TaskPermanentFailureException('Falha permanente controlada do handler de teste.');
        if($resultado !== 'sucesso') throw new TaskPermanentFailureException('Resultado de teste inválido.');
    }
}
