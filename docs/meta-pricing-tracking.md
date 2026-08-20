# Rastreamento de pricing da Meta

O registro canônico continua sendo `conversa_mensagens`, relacionado aos eventos de status pelo `MSG_MetaMessageId` (`wamid`) em conjunto com `conversas.MTA_ID`. A migration `20260820_add_meta_pricing_conversa_mensagens.sql` adiciona os campos opcionais `MSG_MetaCategoria`, `MSG_PricingBillable`, `MSG_PricingModel`, `MSG_PricingType`, `MSG_PricingMarket` e `MSG_PricingCurrency`. A data de entrega e o status continuam em `MSG_EntregueEm` e `MSG_Status`.

Os valores são fatos recebidos em `statuses[].pricing` pelo webhook da Meta. Campos ausentes permanecem `NULL`; um status posterior sem `pricing` não apaga valores já registrados. Payloads sem `pricing` continuam atualizando `sent`, `delivered`, `read` e `failed` normalmente.

Os textos recebidos são truncados de forma determinística nos mesmos limites do banco: categoria 50, modelo 100, tipo 100, mercado 100 e moeda 20 caracteres. O status Meta é persistido independentemente do pricing. Quando houver pricing, sua atualização é tentada em seguida; uma falha exclusiva de pricing não desfaz o status e é registrada no log do webhook apenas com `wamid`, `MTA_ID`, status, classe e mensagem sanitizada da exceção.

O pricing da Meta é independente do consumo comercial do plano, mantido em `consumo_mensal`. Esta etapa não contém tarifa fixa, cálculo financeiro, mudança de planos, cobrança ou alteração do endpoint de campanhas.

## Mapeamento dos fluxos existentes

- Mensagens recebidas são ingeridas por `public/webhook/meta.php` e `MetaWebhookMessageIngestionService`, que as grava em `conversa_mensagens` por meio do model `Conversa`.
- `sent`, `delivered`, `read` e `failed` são normalizados em `MetaStatusWebhookService` e persistidos por `Conversa::atualizarStatusPorMetaMessageId`. É nesse ponto que `statuses[].pricing` também passa a ser capturado.
- A Central de Conversas grava mensagens livres e o `wamid` retornado pela Meta diretamente em `conversa_mensagens`.
- Templates enviados pela Central usam `ConversaTemplateService` e gravam o mesmo identificador.
- Campanhas usam `CampanhaQueueService`; o `wamid` fica em `fila_envio.FIL_MessageId` e no registro correspondente de `conversa_mensagens`.
- O Disparo Manual usa `DisparoManualQueueService`; o `wamid` fica em `disparo_manual_itens.DMI_MessageId`, `disparos.DSP_MessageId` e em `conversa_mensagens`.
- O consumo mensal é incrementado separadamente por `ConsumoMensal::registrarMensagem` nos fluxos comerciais existentes e não foi alterado.

As migrations incrementais ficam em `database/migrations`, usam nomes prefixados por domínio e são aplicadas manualmente. A nova migration usa `ADD COLUMN IF NOT EXISTS` para ser segura em bases já existentes.

Em produção, a migration deve ser aplicada em janela controlada, especialmente quando `conversa_mensagens` estiver grande, devido ao possível metadata lock do `ALTER TABLE`.

Persistências pós-envio que falhem depois de a Meta retornar o `wamid` permanecem como segunda etapa nos fluxos Central, Templates, Campanhas e Disparo Manual. Notificações WhatsApp institucionais armazenadas somente em `notificacoes` também permanecem fora deste rastreamento de pricing.

Enquanto houver possíveis duplicidades históricas, relatórios futuros de pricing devem deduplicar mensagens por `MTA_ID + wamid` antes de contabilizar mensagens faturáveis. Esta etapa mantém todas as cópias internas consistentes e não cria índice único nem altera dados históricos.
