<?php

namespace Services;

class FinanceiroSchedulerBootstrapService
{
    public const TIPO = 'financeiro_gerar_cobrancas_recorrentes';

    private $scheduler;
    private $agora;

    public function __construct(TaskSchedulerService $scheduler = null, callable $agora = null)
    {
        $this->scheduler = $scheduler ?: new TaskSchedulerService();
        $this->agora = $agora ?: function(){ return new \DateTimeImmutable('now'); };
    }

    public function garantirExecucaoDiaria(): array
    {
        $agora = call_user_func($this->agora);
        if(!$agora instanceof \DateTimeInterface){
            throw new \LogicException('Relógio financeiro inválido.');
        }
        $data = \DateTimeImmutable::createFromInterface($agora)->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        return $this->scheduler->agendar(
            self::TIPO,
            [],
            $data,
            'financeiro:gerar_recorrentes:' . $data->format('Y-m-d'),
            20,
            5
        );
    }
}
