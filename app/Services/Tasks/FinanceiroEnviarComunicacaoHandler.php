<?php
namespace Services\Tasks;
use Services\FinanceiroNotificacaoService;
class FinanceiroEnviarComunicacaoHandler implements TaskHandlerInterface
{
    private $service;
    public function __construct(FinanceiroNotificacaoService $service = null){ $this->service=$service ?: new FinanceiroNotificacaoService(); }
    public function executar(array $payload): void { $id=(int)($payload['notificacao_id']??0); if($id<=0||count($payload)!==1){throw new TaskPermanentFailureException('Identificador de notificação inválido.');} $this->service->enviar($id); }
}
