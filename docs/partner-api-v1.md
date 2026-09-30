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
