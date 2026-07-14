# Worker contínuo — Etapa 2 — retry, backoff e reserva persistente

## 1. Alterações de banco

A migration `database/migrations/20260714_add_worker_retry_fields.sql` adiciona campos operacionais em `fila_envio` e `disparo_manual_itens` para registrar reserva, Worker responsável, próxima tentativa, classificação do último erro e dados mínimos de reconciliação.

### `fila_envio`

Novas colunas:

- `FIL_WorkerId VARCHAR(100) NULL`;
- `FIL_DataReserva DATETIME NULL`;
- `FIL_DataAtualizacao DATETIME NULL`;
- `FIL_ProximaTentativa DATETIME NULL`;
- `FIL_UltimoErroTipo VARCHAR(30) NULL`;
- `FIL_UltimoErroCodigo VARCHAR(100) NULL`.

`FIL_Tentativas` e `FIL_MessageId` já existiam nos fluxos atuais e não foram recriados.

Índices adicionados:

- `idx_fila_envio_status_proxima (FIL_Status, FIL_ProximaTentativa)`;
- `idx_fila_envio_status_reserva (FIL_Status, FIL_DataReserva)`.

### `disparo_manual_itens`

Novas colunas:

- `DMI_WorkerId VARCHAR(100) NULL`;
- `DMI_DataReserva DATETIME NULL`;
- `DMI_ProximaTentativa DATETIME NULL`;
- `DMI_UltimoErroTipo VARCHAR(30) NULL`;
- `DMI_UltimoErroCodigo VARCHAR(100) NULL`;
- `DMI_Tentativas INT NOT NULL DEFAULT 0`.

Índices adicionados:

- `idx_dmi_status_proxima (DMI_Status, DMI_ProximaTentativa)`;
- `idx_dmi_status_reserva (DMI_Status, DMI_DataReserva)`.

## 2. Estados e transições

### Campanhas (`fila_envio`)

- `pendente` elegível: `FIL_Status = 'pendente'` e `FIL_ProximaTentativa IS NULL OR FIL_ProximaTentativa <= NOW()`.
- Reserva: `pendente -> processando`, grava `FIL_WorkerId`, `FIL_DataReserva`, `FIL_DataAtualizacao`, limpa `FIL_ProximaTentativa` e incrementa `FIL_Tentativas`.
- Sucesso Meta: `processando -> aguardando_confirmacao`, grava `FIL_MessageId`, limpa Worker/reserva/próxima tentativa/último erro e incrementa consumo.
- Erro temporário: `processando -> pendente`, grava `FIL_ProximaTentativa` com backoff, limpa Worker/reserva e preserva tentativas.
- Erro definitivo: `processando -> erro`, limpa Worker/reserva/próxima tentativa e registra tipo/código/mensagem.
- Bloqueio temporário antes da chamada Meta: `processando -> pendente`, compensa `FIL_Tentativas = GREATEST(FIL_Tentativas - 1, 0)` e agenda próxima tentativa.
- Bloqueio definitivo: `processando -> erro`.

### Disparo manual (`disparo_manual_itens`)

O Worker/cron segue a mesma estratégia de reserva/retry de campanhas. O fluxo AJAX preserva comportamento funcional e não aplica validação operacional, embora grave campos de reserva compatíveis quando a migration existir.

## 3. Cálculo do backoff

O cálculo fica centralizado em `Services\WorkerRetryPolicyService`:

```php
delay = min(
    WORKER_RETRY_DELAY_SECONDS * (2 ** max(0, tentativas - 1)),
    WORKER_RETRY_MAX_DELAY_SECONDS
) + jitter
```

Configurações iniciais:

- `WORKER_MAX_ATTEMPTS = 5`;
- `WORKER_RETRY_DELAY_SECONDS = 30`;
- `WORKER_RETRY_MAX_DELAY_SECONDS = 1800`;
- `WORKER_RETRY_JITTER_SECONDS = 15`;
- `WORKER_PROCESSING_TIMEOUT_MINUTES = 15`.

O jitter usa `mt_rand(0, WORKER_RETRY_JITTER_SECONDS)` e não tem finalidade criptográfica.

## 4. Classificação dos erros

Classificações internas:

- `erro_temporario`;
- `erro_definitivo`;
- `bloqueio_temporario`;
- `bloqueio_definitivo`;
- `erro_persistencia_pos_envio`.

São tratados como temporários quando há sinal estruturado de HTTP 429, HTTP 5xx, timeout, falha de conexão ou códigos Meta transitórios conhecidos (`4`, `17`, `32`, `613`). HTTP 400 e demais erros sem sinal transitório ficam como definitivos por padrão.

## 5. Bloqueios operacionais

Bloqueios temporários, como limite mensal/financeiro regularizável/conta temporariamente indisponível, voltam para `pendente` com próxima tentativa e compensação da tentativa incrementada na reserva. Bloqueios definitivos, como cliente inativo, conta sem configuração obrigatória ou número inválido, gravam `erro` sem próxima tentativa.

## 6. Recuperação de travados

Itens são recuperados apenas quando:

- status é `processando`;
- `MessageId` está ausente;
- `DataReserva` existe e é anterior ao timeout.

Ao recuperar:

- status volta para `pendente`;
- Worker/reserva são limpos;
- próxima tentativa recebe `NOW()`;
- último erro recebe `recuperado_timeout`/`processing_timeout`.

Itens `processando` com `MessageId` não são recuperados para reenvio automático; ficam para reconciliação.

## 7. Tratamento pós-envio

Se a Meta retornar `message_id` e a persistência principal falhar, o código tenta uma atualização mínima de emergência para gravar o `MessageId` e `erro_persistencia_pos_envio`. O item não volta para `pendente` no mesmo ciclo. Se até essa atualização falhar, o evento é registrado em log seguro e a reconciliação manual ainda pode ser necessária.

## 8. Critérios de finalização

Campanhas só finalizam quando não houver item `pendente`, `processando` ou com `FIL_ProximaTentativa` futura. O schema atual não possui estado `parcial`; por compatibilidade, itens em `erro` permitem finalização com os contadores existentes.

## 9. Limitações de idempotência

A Etapa 2 reduz risco operacional, mas ainda não fornece idempotência completa. Ainda é recomendada uma chave lógica por origem/item/tentativa ou tabela própria de envios antes de múltiplos Workers em produção.

## 10. Procedimento de aplicação da migration

1. Fazer backup do banco.
2. Validar se as colunas ainda não existem em produção.
3. Executar manualmente:

```bash
mysql "$DB_NAME" < database/migrations/20260714_add_worker_retry_fields.sql
```

4. Conferir `SHOW COLUMNS` e `SHOW INDEX` das tabelas alteradas.
5. Executar testes em ambiente controlado antes de ativar o Worker em produção.

## 11. Rollback manual

Se necessário, remover primeiro os índices:

```sql
DROP INDEX idx_fila_envio_status_proxima ON fila_envio;
DROP INDEX idx_fila_envio_status_reserva ON fila_envio;
DROP INDEX idx_dmi_status_proxima ON disparo_manual_itens;
DROP INDEX idx_dmi_status_reserva ON disparo_manual_itens;
```

Depois remover as colunas adicionadas. O rollback deve ser precedido de backup e revisão de dados operacionais já gravados.

## 12. Testes necessários antes da produção

- Testar reserva concorrente em banco de homologação.
- Testar erro temporário e cálculo de próxima tentativa.
- Testar max attempts virando erro definitivo.
- Testar bloqueio temporário sem consumir tentativa de envio.
- Testar sucesso limpando campos de reserva.
- Testar item travado com e sem `MessageId`.
- Testar falha pós-envio com atualização mínima de emergência.
- Testar campanha com retry futuro para garantir que não finaliza prematuramente.
