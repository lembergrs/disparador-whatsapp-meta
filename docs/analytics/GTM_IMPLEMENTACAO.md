# Implementação do Google Tag Manager e Google Analytics 4

## Escopo e fonte da auditoria

Este documento registra **somente a instrumentação encontrada no código**. Ele não representa uma alteração no container do Google Tag Manager (GTM), na propriedade do Google Analytics 4 (GA4) ou no Google Ads.

A auditoria encontrou 12 eventos efetivamente enviados ao `dataLayer`:

`view_home`, `view_pricing`, `view_blog_post`, `select_trial`, `click_whatsapp`, `sign_up_start`, `sign_up`, `login`, `begin_meta_connection`, `connect_meta`, `start_trial` e `first_campaign_created`.

O método `AnalyticsService::prepararPurchase()` monta um objeto chamado `purchase`, mas nenhum ponto do sistema registra ou envia esse objeto. Portanto, **`purchase` não é um evento implementado**, não integra a regex, o trigger, a tag ou as recomendações de conversão deste documento. Ele é apenas uma preparação para integração futura.

Também foi constatado que:

- o único `window.dataLayer.push()` da aplicação está na biblioteca central `app/Views/partials/google_tag_manager.php`;
- não há chamada direta a `gtag()` na aplicação;
- eventos de frontend usam `window.Disparador.analytics.push()` diretamente ou atributos HTML `data-analytics-*` tratados pelo listener delegado central;
- eventos de backend são enfileirados na sessão por `Services\AnalyticsService`, consumidos uma vez no `<head>` seguinte e então entregues à mesma biblioteca JavaScript;
- a lista permitida do serviço remove parâmetros não autorizados e a chave hash da fila evita a repetição de payloads backend idênticos enquanto estiverem pendentes;
- `GOOGLE_TAG_MANAGER_ID` controla a carga do container. A constante `GOOGLE_ANALYTICS_MEASUREMENT_ID` existe na configuração, mas não é lida pela instrumentação auditada: o GA4 deve continuar sendo associado pela Google Tag já existente no GTM;
- a biblioteca e o `dataLayer` continuam disponíveis mesmo quando o ID do GTM está vazio; nesse caso, os eventos entram no array local, mas nenhum container é carregado.

---

## 1. Arquitetura

### Fluxo frontend

```text
View / interação do usuário
        ↓
window.Disparador.analytics.push(nome, dados)
        ↓
payload = { event: nome, ...dados }
        ↓
window.dataLayer.push(payload)
        ↓
Google Tag Manager
        ↓
Google Tag existente + Tag GA4 de eventos
        ↓
Google Analytics 4
        ↓
Google Ads (somente conversões importadas do GA4)
```

Os elementos com `data-analytics-event` seguem o mesmo fluxo. Um único listener de clique, definido no partial central, lê os atributos, monta os dados e chama `window.Disparador.analytics.push()`.

### Fluxo backend

```text
Controller ou Model confirma a operação
        ↓
Services\AnalyticsService::registrar(nome, dados)
        ↓
filtro pela lista de eventos/parâmetros permitidos
        ↓
fila deduplicada na sessão PHP
        ↓
partial GTM consome a fila no próximo <head>
        ↓
window.Disparador.analytics.push(nome, dados)
        ↓
dataLayer → GTM → GA4 → Google Ads
```

O consumo remove a fila da sessão. Assim, o evento backend é entregue na próxima renderização que inclua o partial e não é reenviado em renderizações posteriores.

### Responsabilidades

| Componente | Responsabilidade auditada |
|---|---|
| `app/Views/partials/google_tag_manager.php` | Inicializar `dataLayer`, expor a biblioteca global, transformar nome/dados em payload, tratar cliques instrumentados, consumir a fila backend e carregar o snippet do GTM. |
| `app/Services/AnalyticsService.php` | Permitir e filtrar eventos backend, armazená-los sem duplicação na sessão e consumi-los uma única vez. |
| Views instrumentadas | Disparar eventos comportamentais e fornecer os valores dos atributos de clique. |
| Controllers/Model instrumentados | Registrar eventos somente depois da confirmação da operação de negócio. |
| GTM | Escutar os nomes existentes, ler variáveis do Data Layer e encaminhá-las ao GA4. |
| GA4 | Receber, analisar e classificar eventos principais. |
| Google Ads | Importar apenas as conversões selecionadas no GA4. |

---

## 2. Eventos implementados

| Nome exato | Descrição / momento do disparo | Origem responsável | Payload | Observações |
|---|---|---|---|---|
| `view_home` | Quando o `DOMContentLoaded` da home é executado. | View `app/Views/site/home.php` | `page_type`, `source_area` | Uma vez por carregamento da View. Não substitui nem duplica `page_view`; é um evento contextual. |
| `view_pricing` | Na primeira visualização da seção de planos. | View `app/Views/site/home.php` | `page_type`, `section` | Usa `IntersectionObserver` com limiar de 35% e trava local contra repetição. |
| `view_blog_post` | Ao renderizar um artigo público fora do modo preview. | View `app/Views/blog/artigo.php` | `article_slug`, `article_title`, `article_category`, `article_author`, `article_reading_time` | Não é enviado quando `$preview` está preenchido. |
| `select_trial` | Ao clicar em qualquer CTA instrumentado de cadastro/teste. | Views `app/Views/site/home.php`, `app/Views/blog/layout.php` e `app/Views/blog/artigo.php`; listener no partial GTM | `cta_location`, `destination_type`, opcionalmente `plan_name` | `plan_name` só existe no CTA de um plano, pois depende de `data-analytics-plan`. |
| `click_whatsapp` | Ao clicar no botão flutuante de WhatsApp, quando ele é renderizado. | View `app/Views/site/partials/whatsapp_button.php`; listener no partial GTM | `location` | O botão só existe se telefone, mensagem e estado ativo estiverem configurados. |
| `sign_up_start` | Na primeira interação (`input` ou `change`) com o formulário público de cadastro. | View `app/Views/site/cadastro.php` | `form_name`, `source_area` | Uma trava local e a remoção dos dois listeners impedem repetição no mesmo carregamento. |
| `sign_up` | Após a transação de criação do cliente e usuário ser confirmada por `commit`. | `app/Controllers/SiteController.php`, via `AnalyticsService` | `method`, `account_type` | Evento backend enfileirado na sessão. |
| `login` | Depois de senha válida, regeneração da sessão e criação da sessão autenticada, antes do redirect. | `app/Controllers/LoginController.php`, via `AnalyticsService` | `method` | Não ocorre no ramo de credenciais inválidas. |
| `begin_meta_connection` | Depois que o endpoint de inicialização do Embedded Signup responde com sucesso e imediatamente antes de abrir o login da Meta. | View `app/Views/configuracao/meta.php` | Sem parâmetros próprios | Trava local permite um disparo por carregamento da View. Falha anterior do backend não dispara o evento. |
| `connect_meta` | Depois da persistência operacional de uma primeira transição válida para o status conectado. | `app/Controllers/ConfiguracaoController.php`, via `AnalyticsService` | `connection_type`, `first_connection`, `source_area` | Exige atualização no banco, estado anterior diferente de `conectado`, WABA ID e Phone Number ID presentes. |
| `start_trial` | Quando `CLI_DataLiberacao` é gravada pela primeira vez para cliente ativo e com pagamento pendente. | Model `app/Models/Cliente.php`, via `AnalyticsService` | `trial_duration_days`, `trial_message_limit`, `trigger` | O `UPDATE` restringe a data anterior a nula/vazia; o evento depende de `rowCount() > 0`. Atualmente é consequência da conexão Meta. |
| `first_campaign_created` | Depois de salvar campanha, variáveis, fila e total de contatos, se a contagem de campanhas do cliente for exatamente 1. | `app/Controllers/CampanhaController.php`, via `AnalyticsService` | `campaign_type`, `first_campaign` | Evento backend; identifica somente a primeira campanha do cliente. |

---

## 3. Payloads completos

Os exemplos abaixo reproduzem os valores fixos ou um exemplo representativo do valor dinâmico. Em todos os casos, a função central acrescenta `event` antes de executar o `dataLayer.push()`.

### `view_home`

**Disparado em:** carregamento completo do DOM da home.
**Origem:** View `app/Views/site/home.php`.

```javascript
{
  event: "view_home",
  page_type: "home",
  source_area: "public_site"
}
```

| Parâmetro | Tipo | Obrigatório no disparo atual | Origem do valor |
|---|---|---:|---|
| `page_type` | string | Sim | Fixo: `home`. |
| `source_area` | string | Sim | Fixo: `public_site`. |

### `view_pricing`

**Disparado em:** primeira interseção visível de pelo menos 35% da seção `planos`.
**Origem:** View `app/Views/site/home.php`.

```javascript
{
  event: "view_pricing",
  page_type: "home",
  section: "pricing"
}
```

| Parâmetro | Tipo | Obrigatório no disparo atual | Origem do valor |
|---|---|---:|---|
| `page_type` | string | Sim | Fixo: `home`. |
| `section` | string | Sim | Fixo: `pricing`. |

### `view_blog_post`

**Disparado em:** renderização pública do artigo, desde que não seja preview.
**Origem:** View `app/Views/blog/artigo.php`.

```javascript
{
  event: "view_blog_post",
  article_slug: "como-usar-whatsapp-business",
  article_title: "Como usar o WhatsApp Business",
  article_category: "WhatsApp",
  article_author: "Equipe Disparador.net",
  article_reading_time: 5
}
```

| Parâmetro | Tipo | Obrigatório no disparo atual | Origem do valor |
|---|---|---:|---|
| `article_slug` | string | Sim | `ART_Slug` do artigo. |
| `article_title` | string | Sim | `ART_Titulo` do artigo. |
| `article_category` | string | Sim | `ACG_Nome` da categoria. |
| `article_author` | string | Sim | Campo calculado `autorExibicao`. |
| `article_reading_time` | integer | Sim | `ART_TempoLeitura`, convertido explicitamente para inteiro. |

### `select_trial`

**Disparado em:** clique em CTA com `data-analytics-event="select_trial"`.
**Origem:** Views da home, layout/artigo do blog e listener de `app/Views/partials/google_tag_manager.php`.

Payload sem plano:

```javascript
{
  event: "select_trial",
  cta_location: "hero",
  destination_type: "registration"
}
```

Payload de um CTA de plano:

```javascript
{
  event: "select_trial",
  cta_location: "pricing",
  destination_type: "registration",
  plan_name: "Plano Profissional"
}
```

| Parâmetro | Tipo | Obrigatório no disparo atual | Origem do valor |
|---|---|---:|---|
| `cta_location` | string | Sim | `data-analytics-location`; fallback `unknown`. Valores encontrados: `header`, `hero`, `pricing`, `final_cta`, `blog_header`, `blog_about`, `blog_final_cta`. |
| `destination_type` | string | Sim | `data-analytics-destination`; fallback `registration`. Todos os elementos encontrados usam `registration`. |
| `plan_name` | string | Não | `data-analytics-plan`; encontrado somente nos CTAs de planos da home, com o nome vindo de `PLA_Nome`. |

### `click_whatsapp`

**Disparado em:** clique no botão flutuante de WhatsApp.
**Origem:** partial `app/Views/site/partials/whatsapp_button.php` e listener do partial GTM.

```javascript
{
  event: "click_whatsapp",
  location: "landing"
}
```

| Parâmetro | Tipo | Obrigatório no disparo atual | Origem do valor |
|---|---|---:|---|
| `location` | string | Sim | `data-analytics-location`; fallback `unknown`. Contextos explicitamente encontrados: `landing` e `blog`; sem contexto explícito, o partial usa `floating_button`. |

### `sign_up_start`

**Disparado em:** primeiro `input` ou `change` no formulário público.
**Origem:** View `app/Views/site/cadastro.php`.

```javascript
{
  event: "sign_up_start",
  form_name: "public_registration",
  source_area: "public_site"
}
```

| Parâmetro | Tipo | Obrigatório no disparo atual | Origem do valor |
|---|---|---:|---|
| `form_name` | string | Sim | Fixo: `public_registration`. |
| `source_area` | string | Sim | Fixo: `public_site`. |

### `sign_up`

**Disparado em:** cadastro concluído e transação confirmada.
**Origem:** `SiteController`, por `AnalyticsService`.

```javascript
{
  event: "sign_up",
  method: "public_form",
  account_type: "client"
}
```

| Parâmetro | Tipo | Obrigatório no disparo atual | Origem do valor |
|---|---|---:|---|
| `method` | string | Sim | Fixo: `public_form`. |
| `account_type` | string | Sim | Fixo: `client`. |

### `login`

**Disparado em:** autenticação por senha concluída com sucesso.
**Origem:** `LoginController`, por `AnalyticsService`.

```javascript
{
  event: "login",
  method: "password"
}
```

| Parâmetro | Tipo | Obrigatório no disparo atual | Origem do valor |
|---|---|---:|---|
| `method` | string | Sim | Fixo: `password`. |

### `begin_meta_connection`

**Disparado em:** backend aceita o início do Embedded Signup e a View vai abrir o fluxo da Meta.
**Origem:** View `app/Views/configuracao/meta.php`.

```javascript
{
  event: "begin_meta_connection"
}
```

Não há parâmetros próprios além de `event`.

### `connect_meta`

**Disparado em:** primeira conexão Meta confirmada e persistida operacionalmente.
**Origem:** `ConfiguracaoController`, por `AnalyticsService`.

```javascript
{
  event: "connect_meta",
  connection_type: "embedded_signup",
  first_connection: true,
  source_area: "configuration"
}
```

| Parâmetro | Tipo | Obrigatório no disparo atual | Origem do valor |
|---|---|---:|---|
| `connection_type` | string | Sim | Fixo: `embedded_signup`. |
| `first_connection` | boolean | Sim | Fixo: `true`; as condições impedem conexão já marcada como conectada. |
| `source_area` | string | Sim | Fixo: `configuration`. |

### `start_trial`

**Disparado em:** primeira gravação de liberação do trial, atualmente após conexão Meta.
**Origem:** Model `Cliente`, por `AnalyticsService`.

```javascript
{
  event: "start_trial",
  trial_duration_days: 7,
  trial_message_limit: 200,
  trigger: "meta_connection"
}
```

| Parâmetro | Tipo | Obrigatório no disparo atual | Origem do valor |
|---|---|---:|---|
| `trial_duration_days` | integer | Sim | Fixo: `7`. |
| `trial_message_limit` | integer | Sim | Fixo: `200`. |
| `trigger` | string | Sim | Fixo: `meta_connection`. |

### `first_campaign_created`

**Disparado em:** conclusão da primeira campanha do cliente.
**Origem:** `CampanhaController`, por `AnalyticsService`.

```javascript
{
  event: "first_campaign_created",
  campaign_type: "scheduled",
  first_campaign: true
}
```

| Parâmetro | Tipo | Obrigatório no disparo atual | Origem do valor |
|---|---|---:|---|
| `campaign_type` | string | Sim | Fixo: `scheduled`. |
| `first_campaign` | boolean | Sim | Fixo: `true`; a condição exige contagem igual a 1. |

---

## 4. Variáveis Data Layer

`event` é uma chave nativa do GTM e deve ser usada por meio da variável incorporada **Event** (`{{Event}}`). As demais chaves precisam de Variáveis da Camada de Dados, versão 2.

| Nome da variável | Tipo | Descrição | Exemplo |
|---|---|---|---|
| `event` | string | Nome exato do evento que aciona o trigger. | `sign_up` |
| `page_type` | string | Tipo da página contextual. | `home` |
| `source_area` | string | Área do sistema/site onde ocorreu a ação. | `public_site` |
| `section` | string | Seção visualizada na página. | `pricing` |
| `article_slug` | string | Slug público do artigo. | `como-usar-whatsapp-business` |
| `article_title` | string | Título público do artigo. | `Como usar o WhatsApp Business` |
| `article_category` | string | Categoria pública do artigo. | `WhatsApp` |
| `article_author` | string | Nome público do autor. | `Equipe Disparador.net` |
| `article_reading_time` | integer | Tempo estimado de leitura em minutos. | `5` |
| `cta_location` | string | Posição do CTA de teste/cadastro. | `hero` |
| `destination_type` | string | Tipo de destino do CTA. | `registration` |
| `plan_name` | string | Nome do plano associado ao CTA; ausente fora dos CTAs de planos. | `Plano Profissional` |
| `location` | string | Contexto do botão de WhatsApp. | `landing` |
| `form_name` | string | Identificador funcional do formulário. | `public_registration` |
| `method` | string | Método de cadastro ou autenticação, conforme o evento. | `public_form` |
| `account_type` | string | Tipo de conta criada. | `client` |
| `connection_type` | string | Modalidade da conexão com a Meta. | `embedded_signup` |
| `first_connection` | boolean | Indica que a transição é a primeira conexão confirmada. | `true` |
| `trial_duration_days` | integer | Duração configurada do trial em dias. | `7` |
| `trial_message_limit` | integer | Limite de mensagens informado no início do trial. | `200` |
| `trigger` | string | Ação de negócio que iniciou o trial. | `meta_connection` |
| `campaign_type` | string | Tipo informado para a primeira campanha. | `scheduled` |
| `first_campaign` | boolean | Indica que a campanha é a primeira do cliente. | `true` |

Nenhuma variável de `purchase` (`transaction_id`, `value`, `currency` ou `items`) deve ser criada nesta configuração enquanto não existir um disparo real no código.

---

## 5. Configuração recomendada do GTM

Esta seção é um roteiro; **não executar alterações como parte desta documentação**.

### Variáveis

Manter ativa a variável incorporada:

```text
Event
→ variável incorporada do GTM
```

Criar uma Variável da Camada de Dados, versão 2, para cada chave abaixo, sem valor padrão:

```text
DLV - page_type              → page_type
DLV - source_area            → source_area
DLV - section                → section
DLV - article_slug           → article_slug
DLV - article_title          → article_title
DLV - article_category       → article_category
DLV - article_author         → article_author
DLV - article_reading_time   → article_reading_time
DLV - cta_location           → cta_location
DLV - destination_type       → destination_type
DLV - plan_name              → plan_name
DLV - location               → location
DLV - form_name              → form_name
DLV - method                 → method
DLV - account_type           → account_type
DLV - connection_type        → connection_type
DLV - first_connection       → first_connection
DLV - trial_duration_days    → trial_duration_days
DLV - trial_message_limit    → trial_message_limit
DLV - trigger                → trigger
DLV - campaign_type          → campaign_type
DLV - first_campaign         → first_campaign
```

### Trigger

Criar um único trigger de **Evento Personalizado**, com correspondência por expressão regular, conforme a seção 6. Não criar triggers separados por evento.

### Tag

Criar uma única tag de evento GA4, conforme a seção 7, usando `{{Event}}` como nome dinâmico. Associar a Google Tag já existente; não fixar nem duplicar o ID da métrica.

### Parâmetros

Na tag única, cadastrar todos os 22 parâmetros próprios listados acima e apontar cada um para a DLV homônima. Quando um evento não possui determinada chave, a variável ficará `undefined`; o parâmetro sem valor não deve ser enviado pelo GTM. Em particular, `plan_name` é legitimamente opcional.

---

## 6. Trigger recomendado

| Campo | Valor |
|---|---|
| Nome sugerido | `CE - Eventos Disparador` |
| Tipo | Evento Personalizado |
| Nome do evento | Regex abaixo |
| Usar correspondência de regex | Sim |
| Acionamento | Todos os Eventos Personalizados correspondentes |

Regex construída exclusivamente com os 12 eventos efetivamente enviados encontrados no código:

```regex
^(view_home|view_pricing|view_blog_post|select_trial|click_whatsapp|sign_up_start|sign_up|login|begin_meta_connection|connect_meta|start_trial|first_campaign_created)$
```

As âncoras `^` e `$` evitam correspondências parciais. Eventos internos do GTM, eventos automáticos do GA4 e o `purchase` apenas preparado não correspondem à regex.

---

## 7. Tag recomendada

| Campo | Configuração recomendada |
|---|---|
| Nome | `GA4 - Eventos Disparador` |
| Tipo | Evento do Google Analytics: GA4 |
| Google Tag / tag de configuração | Selecionar a **Google Tag já existente** no container. |
| Nome do evento | `{{Event}}` |
| ID da métrica | **Não fixar nesta tag**; herdar da Google Tag existente. |
| Parâmetros do evento | Mapear as DLVs da seção 5, conforme a matriz da seção 8. |
| Trigger | `CE - Eventos Disparador` |

Não criar uma tag por evento, não inserir diretamente `GOOGLE_ANALYTICS_MEASUREMENT_ID` e não criar outra Google Tag. Antes de publicar, confirmar no Preview que parâmetros inexistentes no evento não são enviados como texto `undefined`, string vazia ou valores residuais.

---

## 8. Parâmetros por evento

| Evento | Parâmetros que devem ser enviados |
|---|---|
| `view_home` | `page_type`, `source_area` |
| `view_pricing` | `page_type`, `section` |
| `view_blog_post` | `article_slug`, `article_title`, `article_category`, `article_author`, `article_reading_time` |
| `select_trial` | `cta_location`, `destination_type`; `plan_name` somente quando presente |
| `click_whatsapp` | `location` |
| `sign_up_start` | `form_name`, `source_area` |
| `sign_up` | `method`, `account_type` |
| `login` | `method` |
| `begin_meta_connection` | Nenhum parâmetro próprio |
| `connect_meta` | `connection_type`, `first_connection`, `source_area` |
| `start_trial` | `trial_duration_days`, `trial_message_limit`, `trigger` |
| `first_campaign_created` | `campaign_type`, `first_campaign` |

Como uma única tag atende a todos os eventos, a validação deve confirmar que cada evento leva apenas seu subconjunto. Isso também evita que um valor remanescente de um push anterior seja atribuído a um evento incompatível.

---

## 9. GA4

A classificação abaixo é uma recomendação de mensuração baseada no significado dos eventos já existentes; ela não indica que a propriedade atual já esteja configurada dessa forma.

### Eventos principais

| Evento | Justificativa |
|---|---|
| `sign_up` | Confirma a criação efetiva de uma conta, objetivo central de aquisição. |
| `connect_meta` | Confirma a ativação técnica essencial da conta e ocorre apenas após persistência válida. |
| `start_trial` | Confirma o início efetivo do trial, não apenas intenção. |
| `first_campaign_created` | Representa ativação/adoção concreta do produto após configuração. |

### Eventos secundários

| Evento | Justificativa |
|---|---|
| `select_trial` | Forte sinal de intenção, mas ainda é apenas clique para o cadastro. |
| `sign_up_start` | Indica início do funil e permite medir abandono antes da conclusão. |
| `begin_meta_connection` | Indica tentativa de ativação, útil para comparar com `connect_meta`. |
| `click_whatsapp` | Contato comercial relevante, porém não confirma cadastro, venda ou ativação. |
| `login` | Mede retorno/uso, mas autenticações recorrentes inflariam conversões de aquisição. |

### Eventos apenas informativos

| Evento | Justificativa |
|---|---|
| `view_home` | Contexto de navegação e topo de funil. |
| `view_pricing` | Interesse em conteúdo de preço, sem ação transacional. |
| `view_blog_post` | Consumo de conteúdo e análise editorial. |

No GA4, marcar como Eventos Principais os quatro eventos da primeira tabela. Os demais devem permanecer disponíveis para explorações, públicos e análise do funil sem serem promovidos a evento principal por padrão.

---

## 10. Google Ads

Importar do GA4 como conversões:

| Evento | Recomendação / motivo |
|---|---|
| `sign_up` | Conversão primária de aquisição: representa conta efetivamente criada. |
| `start_trial` | Conversão primária de ativação: representa trial realmente liberado. |
| `connect_meta` | Conversão secundária de qualidade/ativação: comprova configuração técnica concluída. |
| `first_campaign_created` | Conversão secundária de adoção: sinal forte de usuário ativado no produto. |

Para lances de aquisição, evitar contar todas as etapas como objetivos primários ao mesmo tempo. Uma configuração coerente é otimizar por `sign_up` ou `start_trial` e manter `connect_meta` e `first_campaign_created` como ações secundárias de observação até haver volume e estratégia definidos.

Não importar como conversão, por padrão:

- `view_home`, `view_pricing` e `view_blog_post`, pois são visualizações informativas;
- `select_trial`, `sign_up_start` e `begin_meta_connection`, pois representam intenção/início, não conclusão;
- `login`, pois é recorrente e não representa nova aquisição;
- `click_whatsapp`, pois o clique não comprova atendimento ou resultado comercial;
- `purchase`, pois não existe disparo efetivo no sistema auditado.

Essa seleção deve ser importada pela integração GA4 → Google Ads. Não adicionar tags de conversão do Google Ads ao código ou ao escopo desta documentação.

---

## 11. Checklist de configuração e validação

### GTM

- [ ] Confirmar que a Google Tag existente aponta para a propriedade GA4 correta.
- [ ] Ativar a variável incorporada `Event`.
- [ ] Criar as 22 Variáveis da Camada de Dados descritas na seção 5.
- [ ] Configurar todas as DLVs como versão 2 e sem valores padrão artificiais.
- [ ] Criar o único trigger `CE - Eventos Disparador`.
- [ ] Copiar a regex exatamente, com âncoras de início e fim.
- [ ] Criar a única tag `GA4 - Eventos Disparador`.
- [ ] Definir o nome do evento como `{{Event}}`.
- [ ] Associar a Google Tag existente, sem fixar o ID da métrica.
- [ ] Mapear os parâmetros para as respectivas DLVs.
- [ ] Associar apenas o trigger único à tag única.

### Preview e qualidade do Data Layer

- [ ] Abrir o Preview do GTM.
- [ ] Validar cada um dos 12 eventos no `dataLayer`.
- [ ] Confirmar a grafia exata de cada `event`.
- [ ] Confirmar tipos: strings, integers e booleans conforme a seção 4.
- [ ] Confirmar que `plan_name` só aparece quando o CTA possui plano.
- [ ] Confirmar que `begin_meta_connection` não envia parâmetros próprios.
- [ ] Confirmar que eventos backend aparecem somente após sucesso da operação e apenas uma vez.
- [ ] Confirmar que não há e-mail, telefone, ID interno de cliente ou outra PII nos payloads.
- [ ] Validar que a tag dispara uma vez para cada evento correspondente.
- [ ] Validar que nenhum parâmetro residual de outro evento é enviado.
- [ ] Validar que `purchase` não aparece nem aciona a tag.
- [ ] Validar os cliques de CTA nas sete localizações encontradas.
- [ ] Validar o botão WhatsApp nos contextos em que estiver habilitado.

### Publicação e GA4

- [ ] Publicar o container somente após aprovação do Preview.
- [ ] Confirmar os eventos no Tempo Real do GA4.
- [ ] Confirmar eventos e parâmetros no DebugView do GA4.
- [ ] Registrar dimensões/métricas personalizadas no GA4 somente quando os relatórios exigirem parâmetros customizados.
- [ ] Marcar `sign_up`, `connect_meta`, `start_trial` e `first_campaign_created` como Eventos Principais.
- [ ] Manter os demais eventos sem marcação de principal, salvo decisão formal de mensuração.

### Google Ads

- [ ] Confirmar que GA4 e Google Ads estão vinculados corretamente.
- [ ] Importar os eventos principais recomendados como ações de conversão.
- [ ] Definir quais ações serão primárias e quais serão secundárias para lances.
- [ ] Evitar dupla contagem de etapas do mesmo funil.
- [ ] Confirmar recebimento e diagnóstico das conversões no Google Ads.

---

## 12. Manutenção

Para adicionar um evento futuramente, preservar a arquitetura de **uma Tag, um Trigger e múltiplas Variáveis**:

1. Definir o nome e o momento de negócio do novo evento sem renomear eventos existentes.
2. Definir somente parâmetros necessários, sem PII, credenciais ou identificadores internos.
3. Para frontend, chamar `window.Disparador.analytics.push()` ou usar `data-analytics-*` quando o formato do listener central for adequado; nunca acessar `dataLayer` ou `gtag()` diretamente fora da biblioteca.
4. Para backend, incluir explicitamente o evento e sua lista de parâmetros permitidos no `AnalyticsService` e registrá-lo somente depois da confirmação da operação.
5. Adicionar testes que comprovem momento, unicidade, payload, ausência de PII e passagem exclusiva pela biblioteca central.
6. Atualizar este documento com origem, momento, payload completo, tipos, obrigatoriedade e exemplo.
7. Criar DLVs apenas para chaves novas; reutilizar as variáveis homônimas existentes.
8. Acrescentar o nome exato à regex do **mesmo trigger** `CE - Eventos Disparador`, mantendo `^` e `$`.
9. Acrescentar as novas DLVs à **mesma tag** `GA4 - Eventos Disparador`; não criar tag individual.
10. Validar no Preview, `dataLayer`, DebugView e Tempo Real antes de publicar nova versão do container.
11. Reavaliar separadamente se o evento é principal no GA4 e se deve ser importado como conversão no Google Ads.

### Regra operacional permanente

```text
1 Tag GA4 dinâmica: GA4 - Eventos Disparador
1 Trigger regex:     CE - Eventos Disparador
N Variáveis DLV:     uma por chave de payload efetivamente utilizada
```

Um evento só deve entrar na regex e nas plataformas quando houver um ponto real de envio ao `dataLayer`. Um preparador, constante, comentário, teste ou nome citado sem chamada de envio não caracteriza evento implementado.
