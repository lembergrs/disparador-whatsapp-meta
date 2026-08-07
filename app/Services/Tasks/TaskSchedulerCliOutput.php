<?php

namespace Services\Tasks;

class TaskSchedulerCliOutput
{
    public static function resumo(array $resumo, $verbose): string
    {
        if(!$verbose) return '';
        return 'Processadas: ' . (int)$resumo['processadas'] . PHP_EOL
            . 'Concluídas: ' . (int)$resumo['concluidas'] . PHP_EOL
            . 'Retry: ' . (int)$resumo['retry'] . PHP_EOL
            . 'Falhas: ' . (int)$resumo['falhas'] . PHP_EOL;
    }
}
