# Worker dedicado de webhooks Partner

O arquivo `partner-webhook-worker.php` é o único consumidor operacional da fila `parceiro_webhook_eventos`.

- enquanto houver trabalho, processa lotes de até 50 eventos sem pausa;
- quando a fila está vazia, aguarda 500 ms;
- retries continuam respeitando `PWE_ProximaTentativaEm` e o backoff do `PartnerWebhookDeliveryService`;
- usa lock de arquivo próprio para impedir duas instâncias acidentais;
- trata SIGTERM/SIGINT quando a extensão pcntl está disponível;
- erros ficam em `storage/logs/partner-webhook-worker-error.log`;
- ciclos com trabalho ficam em `storage/logs/partner-webhook-worker.log`.

O worker Partner foi removido do `WorkerService` geral para evitar dois consumidores da mesma fila.

## systemd

Há um exemplo em `deploy/systemd/disparador-partner-webhook.service`. Confirme os caminhos e o usuário do PHP da VPS antes de instalar. Depois da instalação, habilite o serviço para iniciar junto com o servidor.
