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

## Roadmap da integração

1. administração de parceiros, clientes vinculados e API keys;
2. `POST /messages` para texto e templates;
3. mídia;
4. webhooks de saída;
5. HMAC, retries, idempotência e logs;
6. ambiente de homologação/sandbox e credenciais de teste.
