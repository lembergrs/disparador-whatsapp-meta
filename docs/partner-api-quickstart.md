# Partner API v1 — Quickstart para integradores

Este guia resume o fluxo necessário para integrar um sistema externo à Partner API do Disparador.net. O contrato técnico detalhado permanece em `docs/partner-api-v1.md`.

## 1. Dados fornecidos pelo Disparador.net

Para iniciar a homologação, o integrador recebe por canal seguro:

- uma API key Partner;
- os `client_id` e `channel_id` autorizados;
- quando webhooks forem habilitados, o segredo individual de assinatura.

A API key e o segredo de webhook são credenciais distintas. Não os envie em tickets, documentação, repositórios ou logs.

Base URL:

`https://disparador.net/index.php?url=api/v1`

## 2. Validar acesso

```bash
curl -sS "https://disparador.net/index.php?url=api/v1/status" \
  -H "Authorization: Bearer $DISPARADOR_API_KEY" \
  -H "Accept: application/json"
```

HTTP 200 retorna o Partner e somente os canais autorizados àquela credencial. Guarde `client_id` e `channel_id`; eles são obrigatórios nas demais operações.

## 3. Consultar templates

```bash
curl -sS "https://disparador.net/index.php?url=api/v1/templates&client_id=123&channel_id=456" \
  -H "Authorization: Bearer $DISPARADOR_API_KEY" \
  -H "Accept: application/json"
```

Use o `id` retornado como `template.id` no envio. Somente templates ativos e aprovados do cliente/canal solicitado são retornados.

## 4. Enviar mensagem

Todo `POST /messages` exige `Idempotency-Key`, com 8 a 120 caracteres. A chave aceita letras, números, ponto (`.`), hífen (`-`), sublinhado (`_`) e dois-pontos (`:`). Gere uma chave nova para cada intenção de envio e reutilize a mesma chave apenas ao repetir a mesma requisição.

Texto livre exige janela de atendimento de 24 horas aberta:

```bash
curl -sS -X POST "https://disparador.net/index.php?url=api/v1/messages" \
  -H "Authorization: Bearer $DISPARADOR_API_KEY" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: msg-20261002-000001" \
  --data '{"client_id":123,"channel_id":456,"to":"5541999999999","type":"text","text":{"body":"Olá! Como posso ajudar?"}}'
```

Fora da janela de 24 horas, utilize template aprovado:

```json
{
  "client_id": 123,
  "channel_id": 456,
  "to": "5541999999999",
  "type": "template",
  "template": {
    "id": 789,
    "variables": {
      "1": "Maria"
    }
  }
}
```

Uma requisição aceita retorna HTTP 202:

```json
{
  "data": {
    "message_id": "wamid...",
    "local_message_id": 321,
    "status": "accepted",
    "type": "text"
  }
}
```

## 5. Enviar mídia

Imagem, PDF e áudio usam duas etapas. Primeiro envie o arquivo:

```bash
curl -sS -X POST "https://disparador.net/index.php?url=api/v1/media" \
  -H "Authorization: Bearer $DISPARADOR_API_KEY" \
  -F "client_id=123" \
  -F "channel_id=456" \
  -F "type=image" \
  -F "file=@foto.jpg"
```

O HTTP 201 retorna, por exemplo:

```json
{
  "data": {
    "media_id": "MEDIA_ID",
    "type": "image",
    "mime_type": "image/jpeg",
    "filename": "foto.jpg",
    "size": 123456
  }
}
```

Depois use esse identificador no `POST /messages`:

```json
{
  "client_id": 123,
  "channel_id": 456,
  "to": "5541999999999",
  "type": "image",
  "image": {
    "media_id": "MEDIA_ID",
    "caption": "Foto"
  }
}
```

Tipos atuais: imagem JPG/JPEG/PNG/WEBP até 5 MB; PDF até 10 MB; áudio AAC/AMR/MP3/M4A/OGG até 16 MB. Mídia livre também exige janela de 24 horas.

## 6. Receber eventos

O Partner pode receber:

- `message.received`
- `message.sent`
- `message.delivered`
- `message.read`
- `message.failed`
- `message.reaction`

O endpoint do integrador deve ser HTTPS público na porta 443. URLs com localhost, endereços privados/reservados ou credenciais embutidas não são aceitas, e o Disparador não segue redirects HTTP. Cada POST contém:

```text
X-Disparador-Event-Id: evt_...
X-Disparador-Timestamp: 1790956800
X-Disparador-Signature: sha256=<hex>
```

A assinatura é:

```text
HMAC-SHA256(
  key = WEBHOOK_SECRET,
  message = X-Disparador-Timestamp + "." + RAW_HTTP_BODY
)
```

Compare a assinatura em tempo constante. Valide também a idade de `X-Disparador-Timestamp` conforme a política do seu sistema e rejeite timestamps antigos. Persista `X-Disparador-Event-Id` e ignore eventos já processados. Responda com qualquer HTTP 2xx somente depois de aceitar/persistir o evento no seu sistema. Respostas fora de 2xx são consideradas falha e podem ser reenviadas. Há até 6 tentativas com backoff.

Em canais Coexistence, uma ação manual no WhatsApp Business App pode gerar `message.sent` ou `message.reaction` com:

```json
{"source":"business_app","human":true}
```

O integrador pode usar esses campos para pausar automações e sincronizar intervenção humana.

## 7. Mídia recebida

Eventos `message.received` de áudio, imagem ou documento podem incluir `data.media.download_url`. Essa URL pertence ao Disparador.net e deve ser acessada com a mesma API key:

```bash
curl -sS "$DOWNLOAD_URL" \
  -H "Authorization: Bearer $DISPARADOR_API_KEY" \
  -o arquivo
```

A URL não expõe token nem URL temporária da Meta.

## 8. Idempotência, limites e retries

No `POST /messages`:

- mesma `Idempotency-Key` + mesmo corpo: resposta original, com `Idempotency-Replayed: true`;
- mesma chave + corpo diferente: HTTP 409 `idempotency_conflict`;
- primeira requisição ainda processando: HTTP 409 `request_in_progress`.

Rate limits por Partner + API key, em janelas de 60 segundos:

- mensagens: 60/min;
- uploads: 20/min;
- leituras: 120/min.

Respeite `Retry-After` no HTTP 429. Respostas autenticadas também informam `X-RateLimit-Limit`, `X-RateLimit-Remaining` e `X-RateLimit-Reset`.

## 9. Erros que o integrador deve tratar

O formato padrão é:

```json
{"error":{"code":"validation_error","message":"..."}}
```

Entre os códigos relevantes estão `unauthorized`, `invalid_json`, `invalid_idempotency_key`, `validation_error`, `unsupported_message_type`, `channel_not_authorized`, `channel_not_ready`, `customer_care_window_closed`, `template_not_available`, `meta_send_failed`, `media_upload_failed`, `media_not_found`, `media_unavailable`, `rate_limit_exceeded`, `rate_limit_unavailable`, `payload_too_large`, `idempotency_conflict`, `request_in_progress`, `method_not_allowed` e `internal_error`.

Não faça retry cego em erros 4xx. Para 429, aguarde `Retry-After`. Para `request_in_progress`, aguarde antes de consultar/repetir a mesma intenção. Em timeout ou falha transitória 5xx de `POST /messages`, repita usando a **mesma** `Idempotency-Key` e o **mesmo corpo**. A idempotência reduz reenvios acidentais, mas o integrador não deve assumir garantia absoluta de exactly-once em falhas excepcionais de infraestrutura.

## 10. Checklist de homologação

A integração está pronta para o teste ponta a ponta quando:

1. `GET /status` retorna 200 e o canal esperado;
2. `GET /templates` retorna os templates aprovados;
3. template é enviado e os eventos de status chegam ao webhook;
4. após uma mensagem recebida abrir a janela, texto livre é enviado;
5. áudio, imagem e PDF são testados nos dois sentidos necessários ao projeto;
6. assinatura HMAC é validada sobre o corpo bruto;
7. duplicidade de `event_id` é ignorada;
8. retry de `POST /messages` reutiliza a mesma `Idempotency-Key`;
9. HTTP 429 respeita `Retry-After`;
10. `source=business_app` e `human=true` são tratados quando Coexistence estiver ativo.

## Fora do contrato atual

Vídeo e localização ainda não fazem parte da Partner API v1. Credenciais da Meta nunca são fornecidas ao integrador.
