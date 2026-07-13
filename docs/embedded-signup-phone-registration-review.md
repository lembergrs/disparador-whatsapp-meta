# Revisão técnica: registro operacional do Phone Number após Embedded Signup

Data da análise: 2026-07-13.

## Conclusão objetiva

O estado observado em produção (`code_verification_status=VERIFIED`, `name_status=AVAILABLE_WITHOUT_REVIEW`, `quality_rating=UNKNOWN`, `status=PENDING`) é compatível com número verificado/cadastrado no WABA, mas ainda não registrado operacionalmente na WhatsApp Cloud API. A documentação oficial da Meta indica que um business phone number precisa ser registrado para uso com Cloud API e que esse registro é feito pelo endpoint `POST /{PHONE_NUMBER_ID}/register`.

O fluxo atual do Disparador valida token, WABA e telefone, assina o app na WABA e persiste a conta, mas não chama `/register`. Portanto, a próxima sprint deve adicionar uma etapa explícita de registro do Phone Number antes de considerar a conta `conectado`.

## Fontes oficiais consultadas

1. Meta for Developers — Register a business phone number: https://developers.facebook.com/documentation/business-messaging/whatsapp/business-phone-numbers/registration
   - O resultado de busca da própria documentação informa que, para usar o número com Cloud API, é necessário registrá-lo e que o registro é feito via API.
2. Meta for Developers — WhatsApp Business Phone Number / Register API: https://developers.facebook.com/documentation/business-messaging/whatsapp/reference/whatsapp-business-phone-number/register-api
   - Referência oficial do endpoint de registro do business phone number.
3. Meta for Developers — Solution Providers / Registering business phone numbers: https://developers.facebook.com/documentation/business-messaging/whatsapp/solution-providers/registering-phone-numbers
   - O resultado oficial mostra o corpo `{ "messaging_product": "whatsapp", "pin": "<PIN>" }` e descreve `<PIN>` como obrigatório.
4. Meta for Developers — Onboarding customers as a Tech Provider/Tech Partner: https://developers.facebook.com/documentation/business-messaging/whatsapp/embedded-signup/onboarding-customers-as-a-tech-provider
   - O resultado oficial indica que o valor de PIN deve ser um número de 6 dígitos e será o PIN de verificação em duas etapas do business phone number.
5. Meta for Developers — Error codes: https://developers.facebook.com/documentation/business-messaging/whatsapp/support/error-codes
   - O resultado oficial lista, entre outros, o erro `133008` para excesso de tentativas de PIN de verificação em duas etapas.

Observação: o acesso direto às páginas de developers.facebook.com via ambiente local retornou bloqueio de túnel HTTP 403, então a análise usou resultados indexados e snippets do mecanismo de busca, priorizando URLs oficiais da Meta. Blogs, Stack Overflow e Reddit não foram usados como fonte normativa.

## Endpoint confirmado

Endpoint oficial atual a considerar para Graph API v25.0:

```http
POST https://graph.facebook.com/v25.0/{PHONE_NUMBER_ID}/register
Authorization: Bearer {ACCESS_TOKEN}
Content-Type: application/json
```

## Payload confirmado

Payload base documentado:

```json
{
  "messaging_product": "whatsapp",
  "pin": "123456"
}
```

Campos:

- `messaging_product`: obrigatório; valor esperado `whatsapp`.
- `pin`: documentado como obrigatório nas referências atuais consultadas; deve ser um PIN numérico de 6 dígitos.

Não encontrei, nas fontes oficiais consultadas, outro campo obrigatório para o caso Cloud API comum. Algumas referências antigas/comunitárias citam `certificate`, mas isso não aparece como requisito principal nas páginas oficiais atuais usadas nesta análise.

## PIN: obrigatoriedade e origem

### O PIN é obrigatório?

Pelos resultados oficiais da Meta para Register API e Solution Providers, sim: o payload inclui `pin` e o campo é descrito como obrigatório. Para o planejamento do Disparador, devemos tratar o PIN como obrigatório até prova oficial em contrário para o caso específico de embedded signup usado pelo app.

### De onde vem o PIN?

A documentação oficial de Embedded Signup para Tech Provider/Partner indica que o PIN é um número de 6 dígitos e será o PIN de verificação em duas etapas do business phone number. Portanto:

- Não é o OAuth `code`.
- Não é o SMS/voz OTP de verificação do número.
- Não deve ser inventado pelo backend sem consentimento do cliente.
- Deve ser informado/definido pelo cliente ou parceiro como PIN de verificação em duas etapas do número.

### O PIN pode ser criado no momento do register?

A leitura oficial sugere que o PIN enviado no register é o PIN de verificação em duas etapas do número. A documentação de onboarding indica que esse PIN pode ser definido no contexto de onboarding do Tech Provider/Partner. Para o Disparador, o comportamento seguro é:

1. Se o Embedded Signup já coletar/definir o PIN no fluxo da Meta, capturar apenas o estado de que o número está pronto para registro, sem expor PIN no frontend além do formulário seguro necessário.
2. Se o PIN não vier do Embedded Signup para o Disparador, criar uma UI explícita para o cliente informar um PIN de 6 dígitos escolhido por ele.
3. Nunca usar PIN fixo ou gerado silenciosamente pelo backend.
4. Armazenar o mínimo possível. Idealmente, usar o PIN apenas na chamada `/register` e não persistir em texto claro.

## Ordem correta recomendada das chamadas

Ordem recomendada para o Disparador, mantendo as validações já implementadas:

1. Receber `FINISH` e `code` pelo fluxo via Facebook JS SDK.
2. Trocar `code` por access token.
3. Validar `debug_token`: validade, app_id, expiração e permissões.
4. Consultar a WABA e o Phone Number selecionados.
5. Validar que a WABA está nos `target_ids` do token e que o Phone Number pertence à WABA.
6. Se `status` do número indicar `PENDING`/não operacional, chamar `POST /{PHONE_NUMBER_ID}/register` com `messaging_product=whatsapp` e PIN de 6 dígitos fornecido/definido pelo cliente.
7. Reconsultar o Phone Number para confirmar estado operacional após o register.
8. Chamar `POST /{WABA_ID}/subscribed_apps` para garantir recebimento de webhooks.
9. Persistir/atualizar a conta Meta com status final.
10. Iniciar trial somente quando o status final for `conectado`.

## subscribed_apps antes ou depois do register?

A assinatura `/{WABA_ID}/subscribed_apps` é sobre a WABA e não substitui o registro operacional do número. A ordem mais segura para o Disparador é registrar primeiro o Phone Number, confirmar que ele ficou operacional e então assinar a WABA para webhooks antes de persistir como `conectado`.

Motivos:

- O erro real indica que a ausência de register impede operação do número, mesmo com assinatura/persistência.
- A assinatura de webhook pode ser idempotente, mas não torna o número apto a enviar/receber se ele continua `PENDING`.
- Manter register antes de persistência evita marcar como conectado um número que ainda exige ação.

## Respostas esperadas e tratamento recomendado

A Meta pode retornar respostas Graph API em formatos padronizados (`success=true` ou objeto `error`). Tratar como segue:

| Cenário | Tratamento recomendado |
|---|---|
| Sucesso (`success: true`) | Reconsultar o Phone Number; se não houver pendência, seguir para `subscribed_apps` e persistir `conectado`. |
| Número já registrado | Tratar como idempotente somente se reconsulta confirmar estado operacional; caso contrário manter `requer_acao`. |
| PIN inválido | Não repetir automaticamente; retornar `requer_acao`, registrar código Meta sanitizado e orientar cliente a revisar PIN. |
| Muitas tentativas de PIN | Bloquear retry automático; erro oficial relacionado inclui excesso de tentativas de PIN (`133008`). |
| Permissão insuficiente | Falha impeditiva; validar se token tem `whatsapp_business_management` e escopos/target_ids corretos. |
| Número pendente | Solicitar PIN e chamar register; se continuar pendente após register, manter `requer_acao`. |
| Erro de verificação | Não marcar conectado; registrar diagnóstico sanitizado e orientar suporte/cliente. |

## Idempotência e repetição segura

A chamada `/register` deve ser tratada como etapa idempotente do ponto de vista do Disparador, mas não deve ser repetida às cegas quando envolver PIN:

- Se houver sucesso e reconsulta confirmar número operacional, não chamar novamente.
- Se a Meta indicar já registrado, reconsultar e aceitar somente se operacional.
- Se a Meta indicar PIN inválido ou muitas tentativas, não fazer retry automático.
- Guardar `request_id`, `phone_number_id`, HTTP status e código de erro Meta sanitizado.

## Permissões necessárias

As permissões já validadas pelo fluxo atual continuam necessárias:

- `whatsapp_business_management`
- `whatsapp_business_messaging`

A validação por `debug_token` deve continuar confirmando `app_id`, validade, expiração e `target_ids` da WABA no granular scope de `whatsapp_business_management`.

## O token do Embedded Signup pode chamar register?

A expectativa é que sim, desde que o Embedded Signup tenha concedido as permissões corretas e o Phone Number pertença à WABA autorizada nos `target_ids`. A chamada deve usar o access token obtido pela troca do `code`, nunca token em JavaScript/localStorage.

## Verificação do código atual

Arquivos revisados:

- `app/Controllers/ConfiguracaoController.php`
- `app/Services/EmbeddedSignupFlowService.php`
- `app/Models/MetaConta.php`
- `app/Views/configuracao/meta.php`
- `docs/embedded-signup-finalizacao.md`

Situação atual:

- `ConfiguracaoController::processarEmbeddedSignupCode()` troca o code por token, consulta WABA/Phone Number, chama `assinarAppNaWaba()`, define status e persiste a conta.
- `buscarDadosWhatsApp()` já coleta `code_verification_status`, `name_status`, `quality_rating` e `operational_status`.
- `EmbeddedSignupFlowService::definirStatusConexao()` classifica `PENDING` como `requer_acao`.
- Não existe chamada a `POST /{PHONE_NUMBER_ID}/register`.
- Não existe UI para coletar PIN de 6 dígitos.
- `MetaConta` persiste status/metadados, mas não tem campo específico para trilha da etapa register.
- A documentação atual ainda afirma que o fluxo não chama registro manual; essa afirmação precisa mudar na sprint de implementação.

## Alterações necessárias no Disparador (sem implementação nesta etapa)

### Backend

- Criar método seguro para `POST /{PHONE_NUMBER_ID}/register` usando `graphRequest(..., 'POST')`.
- Payload: `messaging_product=whatsapp` e `pin` de 6 dígitos.
- Chamar register após validar token/WABA/Phone e antes de `subscribed_apps`/persistência como `conectado`.
- Reconsultar Phone Number após register.
- Manter fallback `requer_acao` quando PIN ausente ou register falhar.

### UI

- Adicionar etapa/modal/formulário para cliente informar PIN de 6 dígitos quando o número vier `PENDING` ou quando o register for obrigatório.
- Explicar que o PIN é o PIN de verificação em duas etapas do número, escolhido/definido pelo cliente/parceiro.
- Não pré-preencher, não sugerir PIN fixo e não armazenar em localStorage.

### Banco

- Avaliar colunas opcionais para diagnóstico:
  - `MTA_RegisterStatus`
  - `MTA_RegisterErroCodigo`
  - `MTA_RegisterErroMensagem`
  - `MTA_RegisterTentativas`
  - `MTA_RegisterUltimaTentativaEm`
- Não armazenar PIN em texto claro. Se for indispensável persistir temporariamente, usar criptografia e expiração curta; recomendado não persistir.

### Logs

- Registrar requestId, etapa `register_phone_number`, phone_number_id, HTTP status, código Meta e mensagem sanitizada.
- Nunca registrar PIN, token, app secret, authorization header ou payload completo.

### Status

- `conectado`: somente após register confirmado, WABA/Phone validado e subscribed_apps confirmado.
- `requer_acao`: PIN ausente, PIN inválido, excesso de tentativas, register pendente ou estado operacional ainda `PENDING`.
- `erro`: falha impeditiva inesperada ou permissão/configuração inválida.

### Retry

- Permitir retry manual controlado após o cliente corrigir PIN.
- Não repetir automaticamente em PIN inválido ou limite de tentativas.
- Repetir automaticamente somente para falhas transitórias claras de rede/5xx, com limite e logs.

### Segurança

- Validar CSRF/cliente autenticado na submissão do PIN.
- Validar formato `^[0-9]{6}$`.
- Não guardar PIN em sessão por tempo longo.
- Não logar PIN.
- Manter TLS strict.

## Plano de implementação recomendado

1. Adicionar UI de PIN no fluxo pós-FINISH quando status do Phone Number for `PENDING`.
2. Adicionar endpoint backend para finalizar registro com PIN ou incorporar PIN em `finalizarEmbeddedSignup` quando obrigatório.
3. Validar PIN em backend (`6` dígitos numéricos).
4. Chamar `POST /{PHONE_NUMBER_ID}/register`.
5. Reconsultar Phone Number.
6. Assinar WABA com `subscribed_apps`.
7. Persistir `conectado` somente após sucesso real.
8. Em falha de PIN/register, persistir/retornar `requer_acao` com requestId.
9. Atualizar docs operacionais e checklist real.
10. Criar testes offline com stub Graph para sucesso, already registered, PIN inválido, permissão insuficiente, PENDING persistente e retries.
