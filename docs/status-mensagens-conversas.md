# Status das mensagens na Central de Conversas

## Estrutura reutilizada

A implementação mantém a tabela `conversa_mensagens`, o campo de correlação `MSG_MetaMessageId`, o webhook único `public/webhook/meta.php`, a consulta da conversa e o polling já existente. Nenhum segundo webhook ou tabela foi criado.

A migration incremental `database/migrations/20260730_add_status_conversa_mensagens.sql` acrescenta somente timestamps independentes e erro sanitizado:

- `MSG_EnviadaEm`;
- `MSG_EntregueEm`;
- `MSG_LidaEm`;
- `MSG_FalhouEm`;
- `MSG_CodigoErro`;
- `MSG_MensagemErro`;
- `MSG_AtualizadoEm`;
- índice de `MSG_MetaMessageId`, caso ainda não exista.

O SQL deve ser aplicado manualmente, depois de backup e antes do deploy do código. Ele não foi executado por esta branch.

## Mapeamento e progressão

`MensagemStatusService` centraliza aliases legados, prioridade e apresentação:

| Status normalizado | Aliases principais | Indicador |
|---|---|---|
| `pending` | `pendente`, `fila`, `aguardando_confirmacao` | relógio cinza — Aguardando envio |
| `processing` | `processando` | relógio cinza — Aguardando envio |
| `sent` | `enviado` | um check cinza — Enviada |
| `delivered` | `entregue` | dois checks cinza — Entregue |
| `read` | `lido` | dois checks azuis — Lida |
| `failed` | `erro`, `falha` | alerta vermelho — Falha no envio |

A progressão de status é atômica no `UPDATE`: eventos repetidos não alteram novamente a etapa e eventos atrasados não reduzem `read` para `delivered` ou `delivered` para `sent`. Um evento atrasado ainda pode preencher seu timestamp específico sem regredir o status principal. `failed` somente substitui estado anterior à confirmação `sent`.

Mensagens antigas com status ausente/desconhecido permanecem sem indicador. Mensagens recebidas nunca exibem checks.

## Webhook

`MetaStatusWebhookService` processa cada item de `value.statuses` isoladamente. Ele:

1. valida `id` e status;
2. normaliza `sent`, `delivered`, `read` ou `failed`;
3. converte timestamp Unix válido para o timezone da aplicação;
4. extrai somente código e mensagem de erro sanitizada;
5. atualiza `conversa_mensagens` pelo `MSG_MetaMessageId`;
6. mantém, com a mesma proteção contra regressão, os espelhos em `disparos`, `fila_envio` e `disparo_manual_itens`.

Não há chamadas externas adicionais. O payload bruto deixou de ser gravado em `public/webhook/webhook_debug.log`; o log operacional registra apenas metadados mínimos já existentes. Um item inválido ou desconhecido não interrompe o lote e a resposta HTTP continua imediata após o processamento local.

## Interface e polling

A bolha de saída renderiza horário e apresentação fornecida pelo service, com Font Awesome, `title` e `aria-label`. Para falhas, o tooltip pode incluir horário, código Meta e mensagem sanitizada.

A requisição já existente `conversa/verificarAtualizacao` também retorna, em lote, os status das até 100 mensagens de saída visíveis. O JavaScript localiza `[data-message-status-id]` e altera somente classe, ícone e tooltip. Ele não recria mensagens, não muda scroll, não interfere na digitação e não cria outro `setInterval`. O intervalo atual permanece em 120 segundos.

## Homologação manual

1. Aplicar a migration em ambiente controlado após backup.
2. Abrir uma conversa e enviar texto/template.
3. Confirmar relógio enquanto `aguardando_confirmacao`.
4. Confirmar um check após webhook `sent`.
5. Confirmar dois checks cinza após `delivered`.
6. Abrir a mensagem no aparelho destinatário e confirmar checks azuis somente após webhook `read`.
7. Usar um caso real/controlado de `failed` e validar código/mensagem sanitizados.
8. Manter a tela aberta e aguardar o polling, confirmando atualização sem recarga da conversa ou perda de scroll.
9. Repetir em viewport mobile.
10. Enviar webhooks repetidos e fora de ordem em homologação e confirmar status/timestamps.

Abrir uma conversa no Disparador marca apenas a conversa recebida como atendida; isso não cria `read` para mensagens de saída.
