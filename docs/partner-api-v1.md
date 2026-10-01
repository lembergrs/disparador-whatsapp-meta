# Partner API v1 — Guia de integração

A Partner API é a interface do Disparador.net para sistemas de terceiros enviarem e receberem eventos do WhatsApp sem acesso às credenciais da Meta.

> Este arquivo é a documentação de integração da API. Toda alteração pública da Partner API deve atualizar este documento no mesmo PR.

## Ambientes

### Produção

Base da aplicação: `https://disparador.net`

Enquanto a API utiliza o roteador MVC atual, os endpoints são publicados sob:

`/index.php?url=api/v1/{endpoint}`

### Homologação / sandbox

Será disponibilizado antes da primeira integração externa. A URL e as credenciais de teste serão documentadas aqui. O ambiente de homologação deverá usar API key própria e não compartilhar credenciais com produção.

## Autenticação

Todos os endpoints protegidos utilizam:

`Authorization: Bearer <API_KEY>`

A chave em texto puro não é armazenada pelo Disparador; somente seu hash SHA-256. Uma chave revogada, inativa ou expirada retorna HTTP `401`.

Credenciais da Meta, App Secret e tokens de contas WhatsApp nunca são expostos ao integrador.

## Modelo de contas e vínculos

Existem três classificações de cadastro em `clientes.CLI_TipoConta`:

- `cliente`: cliente direto do Disparador;
- `cliente_partner`: empresa parceira/integrador que utiliza a Partner API;
- `cliente_partner_vinculado`: cliente final atendido por um parceiro.

O cadastro em `parceiros_api` pertence a um único `CLI_ID` classificado como `cliente_partner`.

O acesso do parceiro a um cliente final **não é inferido pela classificação**. A autorização é determinada pela tabela `parceiro_clientes`, que vincula explicitamente:

`PAR_ID + CLI_ID + MTA_ID`

Isso permite que o Disparador revogue um único cliente/número sem afetar os demais clientes do parceiro.


## Provisionamento de acesso

As credenciais são provisionadas pela administração do Disparador.net.

1. o parceiro possui um cadastro classificado como `cliente_partner`;
2. cada cliente final utilizado na integração é classificado como `cliente_partner_vinculado`;
3. o administrador vincula explicitamente o cliente e o canal WhatsApp (`MTA_ID`) ao parceiro;
4. o administrador gera uma API key para a integração;
5. o segredo completo é exibido **uma única vez** e deve ser armazenado com segurança pelo integrador;
6. no banco do Disparador permanece somente o hash SHA-256 e um prefixo identificador;
7. uma chave pode ser revogada individualmente sem desconectar o número da Meta.

Para testar no Postman, configure o header:

```http
Authorization: Bearer dsp_live_SUA_CHAVE
Accept: application/json
```

e execute primeiro o endpoint `GET /status`. Uma resposta HTTP 200 confirma a autenticação e mostra apenas os canais autorizados para aquela credencial.

> As credenciais de homologação terão prefixo/ambiente próprios quando o sandbox for disponibilizado. Não reutilize uma chave de produção em homologação.

## Endpoint disponível

### GET /status

`GET /index.php?url=api/v1/status`

Retorna a identificação do parceiro autenticado e os canais explicitamente autorizados.

Exemplo de requisição:

```http
GET /index.php?url=api/v1/status HTTP/1.1
Host: disparador.net
Authorization: Bearer SUA_API_KEY
Accept: application/json
```

Exemplo de resposta:

```json
{
  "data": {
    "api": "disparador-partner",
    "version": "v1",
    "partner": {
      "id": 1,
      "identifier": "zain",
      "name": "Parceiro de exemplo"
    },
    "channels": [
      {
        "client_id": 123,
        "channel_id": 45,
        "external_id": "cliente-no-sistema-do-parceiro",
        "name": "WhatsApp principal",
        "phone": "5541999999999",
        "status": "conectado",
        "onboarding_type": "coexistence"
      }
    ]
  }
}
```

### Erros

`401 Unauthorized`

```json
{
  "error": {
    "code": "unauthorized",
    "message": "API key inválida ou ausente."
  }
}
```

`405 Method Not Allowed`

O endpoint foi chamado com um método HTTP não suportado.

## Convenções para os próximos endpoints

As próximas implementações devem manter:

- versionamento em `/api/v1`;
- JSON como formato de request/response;
- autenticação Bearer;
- isolamento por parceiro, cliente e canal;
- códigos HTTP coerentes;
- erros com `error.code` estável e `error.message` legível;
- exemplos de request e response neste documento;
- nenhuma exposição de credenciais Meta.


## Cadastro self-service do parceiro

O administrador pode enviar ao integrador o endereço público:

`GET /index.php?url=parceiroCadastro`

O formulário cria, em uma única transação, o cadastro empresarial `clientes` como `cliente_partner`, o usuário administrador e o registro em `parceiros_api`. O Partner nasce com cadastro `pendente`, implantação `aguardando_validacao` e API `bloqueada`.

O cadastro coleta CNPJ, razão social e endereço fiscal completo, incluindo código IBGE, usando os mesmos campos `CLI_NFSe_*` do financeiro existente. Isso prepara o parceiro pagador para a validação fiscal antes de uma eventual NFS-e.

A aprovação é administrativa. Enquanto `PAR_StatusCadastro` não for `aprovado`, não é permitido gerar API key e a autenticação da Partner API rejeita credenciais eventualmente existentes.

A aprovação do cadastro não cria cobrança automaticamente. A validação inicial (inclusive Coexistence, quando aplicável) permanece anterior à cobrança de implantação.

### Número próprio do parceiro

Depois da aprovação administrativa, o `cliente_partner` também pode operar como conta do Disparador para conectar **o número WhatsApp da própria empresa** em `Configuração > Números WhatsApp`. O Embedded Signup usa o mesmo fluxo seguro já existente para clientes, incluindo Coexistence quando elegível.

A autorização especial vale apenas para o primeiro número próprio do Partner enquanto ele ainda não possui um plano Disparador comum. O número é persistido em `meta_contas` com o `CLI_ID` do próprio `cliente_partner`.

Isso não mistura os clientes do integrador com a conta dele: números de clientes finais continuam pertencendo aos respectivos `cliente_partner_vinculado` e são autorizados separadamente em `parceiro_clientes`.

### Operação financeira pelo admin

A tela de detalhe do Partner concentra a operação comercial: configuração da assinatura, valor de implantação, dia de vencimento, faixas mensais por quantidade de clientes faturáveis, geração/recuperação da cobrança de implantação, geração da mensalidade por competência e acompanhamento das cobranças Asaas.

As faixas são globais para o programa Partner e não podem se sobrepor. A competência mensal mantém o snapshot da quantidade de clientes faturáveis e da faixa aplicada.

A cobrança de implantação é idempotente por Partner/assinatura: uma tentativa de recuperação reutiliza a cobrança aberta em vez de criar outra. O primeiro vencimento não suspende a API imediatamente; uma política Partner de tolerância deve decidir a suspensão posteriormente.

Cobranças `partner_api` não alteram o estado financeiro do plano Disparador comum do mesmo `CLI_ID`. Isso permite que a empresa Partner também tenha uma assinatura operacional normal sem que os dois contratos se contaminem.

## Ciclo comercial e financeiro

O cadastro Partner utiliza o mesmo cadastro empresarial/fiscal de `clientes` usado pelo financeiro do Disparador. Isso permite que cobrança, confirmação de pagamento e NFS-e permaneçam no fluxo financeiro já existente.

### Estados

O parceiro possui estados independentes de cadastro, implantação e API. Um cliente vinculado somente entra na base faturável quando o vínculo está em `ativo` e possui `PAC_FaturavelDesde`.

Cadastro ou convite, isoladamente, **não gera mensalidade**.

### Implantação

A implantação é uma cobrança de origem `partner_api` e tipo `implantacao_partner`. Ela pertence ao `CLI_ID` do próprio parceiro e pode ser sincronizada com o mesmo provider financeiro usado pelo Disparador.

A liberação para homologação/ativação da API ocorre somente após a confirmação idempotente do pagamento. A cobrança é sincronizada com o Asaas pelo mesmo workflow financeiro do Disparador. Quando a implantação é confirmada, `PAR_StatusImplantacao` passa para `paga` e uma API ainda bloqueada passa para `homologacao`.

Cobranças Partner são identificadas por `COB_Origem=partner_api`. O webhook financeiro distingue essas cobranças das assinaturas comuns: Partner atualiza `PAS_ID`/`parceiro_*`; cobranças normais continuam atualizando `ASS_ID`/`assinaturas`.

### Mensalidade variável

No fechamento de cada competência:

1. contam-se clientes distintos faturáveis do parceiro;
2. localiza-se a faixa ativa em `parceiro_planos`;
3. grava-se um snapshot em `parceiro_faturamento_competencias`;
4. cria-se uma cobrança `mensalidade_partner` vinculada ao parceiro;
5. alterações posteriores na quantidade de clientes não modificam retroativamente a competência já fechada.

O snapshot registra quantidade, faixa e valor utilizados no cálculo, permitindo auditoria.

### Pagamento e NFS-e

Cobranças Partner são registros da tabela `cobrancas`. Portanto, devem seguir o mesmo ciclo financeiro:

`cobrança local → provider/Asaas → confirmação por webhook ou admin → COB_Status=pago → NFS-e por COB_ID`

A NFS-e continua sendo emitida pela RL2 Net para o tomador identificado pelo `CLI_ID` da cobrança. No caso Partner, o tomador é a empresa parceira pagadora, e não cada cliente final vinculado.

Os dados fiscais obrigatórios permanecem nos campos `CLI_NFSe_*` do cadastro do parceiro. A Partner API não cria uma segunda estrutura fiscal.

## Roadmap da integração

1. administração de parceiros, clientes vinculados e API keys;
2. `POST /messages` para texto e templates;
3. mídia;
4. webhooks de saída;
5. HMAC, retries, idempotência e logs;
6. ambiente de homologação/sandbox e credenciais de teste.


## Onboarding de clientes do Partner

O Partner aprovado gera convites no painel em **Clientes Partner**. O link contém um token aleatório de uso único; somente o SHA-256 do token é persistido.

O cliente final conclui o próprio cadastro como `cliente_partner_vinculado`, cria seu login e então conecta o próprio WhatsApp pelo Embedded Signup. O cliente vinculado possui painel restrito a configuração do WhatsApp, templates e dados da conta; disparos, campanhas, listas, conversas e financeiro do Disparador não fazem parte desse perfil.

Ao concluir o Embedded Signup, o Disparador cria automaticamente a autorização `PAR_ID + CLI_ID + MTA_ID`. O vínculo só recebe `PAC_Status=ativo` e `PAC_FaturavelDesde` quando o número chega ao estado conectado. Convite ou cadastro sem WhatsApp ativo não entra na contagem faturável.


## Envio de mensagens

`
### Idempotência de envio

Todo `POST /index.php?url=api/v1/messages` deve enviar o header `Idempotency-Key` (8 a 120 caracteres). Gere uma chave nova para cada intenção de envio e reutilize exatamente a mesma chave ao repetir uma requisição após timeout ou perda de resposta.

- mesma chave + mesmo corpo: retorna a resposta original sem reenviar à Meta e inclui `Idempotency-Replayed: true`;
- mesma chave + corpo diferente: HTTP 409 `idempotency_conflict`;
- mesma chave enquanto a primeira requisição ainda está em processamento: HTTP 409 `request_in_progress`.

POST /index.php?url=api/v1/messages`

Headers:

`Authorization: Bearer <API_KEY>`

`Content-Type: application/json`

### Texto

Texto livre só é aceito quando existe mensagem recebida do destinatário nas últimas 24 horas naquele mesmo cliente/canal.

```json
{
  "client_id": 123,
  "channel_id": 456,
  "to": "5541999999999",
  "type": "text",
  "text": {
    "body": "Olá! Como posso ajudar?"
  }
}
```

### Template

O template deve existir no Disparador, pertencer ao mesmo `client_id + channel_id` e estar com status `APPROVED`.

```json
{
  "client_id": 123,
  "channel_id": 456,
  "to": "5541999999999",
  "type": "template",
  "template": {
    "id": 789,
    "variables": {
      "nome": "Maria"
    }
  }
}
```

Templates com header de mídia podem informar `template.header_media` usando os mesmos dados aceitos pelo serviço interno de templates (`media_id`, `link` e, para documento, `filename`).

Resposta aceita:

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

Erros usam o formato `{"error":{"code":"...","message":"..."}}`. Códigos iniciais: `unauthorized`, `invalid_json`, `validation_error`, `unsupported_message_type`, `channel_not_authorized`, `channel_not_ready`, `customer_care_window_closed`, `template_not_available`, `meta_send_failed` e `internal_error`.

O endpoint nunca recebe nem retorna token da Meta. A autorização é sempre resolvida pela API key do Partner e pelo vínculo ativo `PAR_ID + CLI_ID + MTA_ID`.


## Webhooks do Partner

Cada Partner configura um único endpoint HTTPS no próprio painel. O Disparador roteará para esse endpoint os eventos dos clientes/canais explicitamente autorizados ao Partner, identificando `client_id` e `channel_id` no payload.

Eventos previstos na v1: `message.received`, `message.sent`, `message.delivered`, `message.read`, `message.failed` e `message.reaction`.

O segredo de assinatura é individual por Partner e deve ser copiado quando gerado/regenerado. Ele não é persistido em texto puro: o Disparador o deriva de `PARTNER_WEBHOOK_SIGNING_KEY` e de um salt aleatório por Partner, mantendo apenas o salt e o hash de conferência no banco.

A configuração do endpoint é independente das API keys. Desativar o webhook não revoga o acesso REST do Partner.

> A entrega assíncrona, assinatura dos requests, retries e histórico de tentativas serão implementados na etapa de entrega de eventos.


## Entrega de eventos webhook

Os eventos configurados pelo Partner são persistidos antes da entrega e processados de forma assíncrona pelo worker do Disparador. Falhas temporárias usam retry com backoff exponencial, até 6 tentativas. O painel Partner exibe as últimas entregas, HTTP retornado, quantidade de tentativas e erro resumido.

Cada POST usa `Content-Type: application/json` e os headers:

- `X-Disparador-Event-Id`: identificador idempotente do evento.
- `X-Disparador-Timestamp`: Unix timestamp usado na assinatura.
- `X-Disparador-Signature: sha256=<hex>`: HMAC-SHA256 de `<timestamp>.<corpo JSON bruto>` usando o segredo individual do webhook.

O receptor deve calcular o HMAC sobre o corpo bruto recebido e comparar em tempo constante. O `event_id` também deve ser tratado como idempotente pelo integrador.

Exemplo normalizado de mensagem recebida:

```json
{
  "event_id": "evt_...",
  "event": "message.received",
  "created_at": "2026-10-01T10:30:00-03:00",
  "data": {
    "client_id": 123,
    "channel_id": 45,
    "message_id": "wamid...",
    "local_message_id": 678,
    "from": "5541999999999",
    "type": "text",
    "text": "Olá",
    "timestamp": "2026-10-01T10:29:59-03:00"
  }
}
```

Status de saída são publicados somente para mensagens originadas pela Partner API, evitando que o integrador receba como próprios os envios manuais da Central ou do WhatsApp Business App.

Por segurança, o destino deve ser HTTPS público na porta 443. Endereços privados/reservados, localhost, credenciais embutidas na URL e redirects HTTP não são aceitos.


### Intervenção humana em Coexistence

Quando uma mensagem ou reação é enviada manualmente pelo WhatsApp Business App/Web associado ao canal em Coexistence, o Disparador recebe um `message_echoes`. Se o canal estiver autorizado ao Partner, o echo novo também é publicado:

- mensagem manual: `message.sent`
- reação manual: `message.reaction`

Esses payloads incluem `"source":"business_app"` e `"human":true`, permitindo que o integrador pause automações, sincronize a conversa ou trate a intervenção humana sem confundi-la com um envio originado pela Partner API.

Echoes duplicados não geram novos eventos Partner.

## Mídia recebida

Para mensagens recebidas dos tipos `audio`, `image` e `document`, o webhook inclui `data.media.download_url`, além de `mime_type` e `filename` quando disponíveis. A URL aponta para o Disparador e não expõe URL temporária nem token da Meta.

O Partner deve fazer `GET` nessa URL enviando a mesma autenticação `Authorization: Bearer <API_KEY>`. O endpoint valida se a mensagem pertence a um cliente/canal autorizado ao Partner antes de entregar o arquivo.

Exemplo:

```json
{
  "event": "message.received",
  "data": {
    "client_id": 10,
    "channel_id": 9,
    "type": "audio",
    "media": {
      "mime_type": "audio/ogg",
      "filename": null,
      "download_url": "https://disparador.net/index.php?url=api/v1/media&id=12345"
    }
  }
}
```

O download retorna `404 media_not_found` quando a mídia não existe ou não pertence ao escopo autorizado e `502 media_unavailable` quando a mídia não pode ser obtida da Meta/cache.

## Envio de mídia

A primeira fase de mídia de saída suporta **imagem** e **documento PDF** em duas etapas.

1. Faça `POST /index.php?url=api/v1/media` como `multipart/form-data`, com `client_id`, `channel_id`, `type` (`image` ou `document`) e `file`. A autenticação é `Authorization: Bearer <API_KEY>`. O retorno `201` contém um `media_id` temporário para o envio.
2. Faça o `POST /index.php?url=api/v1/messages` normal, com `Idempotency-Key`, usando `type: image` ou `type: document` e o `media_id` retornado. O envio de mídia livre exige janela de atendimento de 24 horas aberta.

Exemplo imagem:

```json
{"client_id":10,"channel_id":9,"to":"5541999999999","type":"image","image":{"media_id":"123","caption":"Foto"}}
```

Exemplo documento:

```json
{"client_id":10,"channel_id":9,"to":"5541999999999","type":"document","document":{"media_id":"123","caption":"Arquivo","filename":"arquivo.pdf"}}
```

Limites atuais do upload: imagem JPG/JPEG/PNG/WEBP até 5 MB e documento PDF até 10 MB. Áudio de saída será adicionado em etapa própria porque o serviço atual de upload da Central ainda não contempla áudio.
