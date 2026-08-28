<?php
namespace Services\Tasks;
use Services\FinanceiroNotificacaoService;
class FinanceiroPlanejarComunicacoesHandler implements TaskHandlerInterface
{
    private $service;
    public function __construct(FinanceiroNotificacaoService $service = null){ $this->service=$service ?: new FinanceiroNotificacaoService(); }
    public function executar(array $payload): void { if($payload!==[]){throw new TaskPermanentFailureException('O planejador financeiro não aceita payload.');} $this->service->planejar(); }
}
