<?php

namespace Services\Tasks;

class TaskSchedulerCliOutput
{
    public function formatar(array $resumo, $verbose = false): string
    {
        if(!$verbose) return '';
        return 'Processadas: ' . (int)($resumo['processadas'] ?? 0) . PHP_EOL
            . 'Concluídas: ' . (int)($resumo['concluidas'] ?? 0) . PHP_EOL
            . 'Retry: ' . (int)($resumo['retry'] ?? 0) . PHP_EOL
            . 'Falhas: ' . (int)($resumo['falhas'] ?? 0) . PHP_EOL;
    }
}
