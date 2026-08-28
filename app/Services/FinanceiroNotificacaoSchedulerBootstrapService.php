<?php
namespace Services;
class FinanceiroNotificacaoSchedulerBootstrapService
{
    private $scheduler; private $agora;
    public function __construct(TaskSchedulerService $scheduler=null, callable $agora=null){$this->scheduler=$scheduler?:new TaskSchedulerService();$this->agora=$agora?:function(){return new \DateTimeImmutable('now');};}
    public function garantirExecucaoDiaria(): array { $agora=call_user_func($this->agora); if(!$agora instanceof \DateTimeInterface){throw new \LogicException('Relógio financeiro inválido.');} $data=\DateTimeImmutable::createFromInterface($agora); return $this->scheduler->agendar('financeiro_planejar_comunicacoes',[],$data,'financeiro:planejar_comunicacoes:'.$data->format('Y-m-d'),25,5); }
}
