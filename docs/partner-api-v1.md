# Partner API v1

A Partner API é a camada pública de integração de terceiros do Disparador.net.

## Princípios

- autenticação externa por `Authorization: Bearer <API_KEY>`;
- a API key em texto puro não é persistida; somente SHA-256;
- cada parceiro acessa somente combinações `CLI_ID + MTA_ID` explicitamente vinculadas;
- tokens e credenciais da Meta nunca fazem parte do contrato público;
- a API é versionada em `/api/v1`;
- respostas são JSON e não dependem da sessão do painel.

## Primeiro endpoint

`GET /index.php?url=api/v1/status`

Header:

`Authorization: Bearer <API_KEY>`

Retorna a identificação do parceiro e somente os canais autorizados para aquela chave.

Este endpoint serve como smoke test da autenticação e do isolamento antes da implementação de envio de mensagens e webhooks.

## Próximas etapas

1. administração de parceiros, vínculos e geração/revogação de API keys;
2. `POST /api/v1/messages` para texto/template e depois mídia;
3. fila de webhooks de saída;
4. assinatura HMAC, retries, idempotência e logs de entrega.
