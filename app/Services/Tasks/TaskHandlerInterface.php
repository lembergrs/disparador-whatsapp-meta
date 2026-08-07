<?php

namespace Services\Tasks;

interface TaskHandlerInterface
{
    public function executar(array $payload): void;
}
