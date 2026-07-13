# Finalização do Embedded Signup Meta

## Arquitetura do fluxo

1. A tela `configuracao/meta` chama `configuracao/iniciarEmbeddedSignup` por POST com CSRF.
2. O backend valida cliente autenticado, variáveis Meta, HTTPS do redirect URI e limite do plano. Em seguida cria `state` aleatório de uso único, `requestId` e uma tentativa temporária na tabela `meta_embedded_signup_attempts` com expiração de 30 minutos.
3. O frontend usa o Facebook JavaScript SDK já inicializado com `META_APP_ID`/`META_GRAPH_VERSION` e chama `FB.login()` com `config_id`, `response_type: code`, `override_default_response_type: true` e `extras.sessionInfoVersion`.
4. Quando a Meta envia `WA_EMBEDDED_SIGNUP` com `FINISH`, a própria página original do Disparador recebe o `window.message` e mantém os IDs em memória para finalizar o fluxo no backend. O backend valida CSRF, cliente autenticado, `state`, expiração e grava somente os IDs úteis (`waba_id`, `phone_number_id`, `business_id`) na tentativa temporária, desde que ela ainda não tenha sido consumida definitivamente pelo callback.
5. O callback de `FB.login()` recebe `authResponse.code`; o frontend coordena `code` e `FINISH` em qualquer ordem e chama `configuracao/finalizarEmbeddedSignup` com `state`, `code`, CSRF e `sessionInfo`.
6. Quando o `FINISH` trouxe IDs, o backend consulta exatamente a WABA e o Phone Number selecionados e confirma que o telefone pertence à WABA e que a WABA está nos `target_ids` do token. Sem IDs, o fallback só é aceito quando há exatamente uma WABA e um telefone possível.
7. O backend chama `/{waba_id}/subscribed_apps` via POST, de forma idempotente, e exige resposta `success=true` para confirmar a assinatura do app na WABA.
8. A conta é salva de forma idempotente por cliente + WABA + Phone Number, reativando a conta se já existir. O status final gravado segue a regra operacional documentada abaixo.
9. O trial é iniciado somente quando o status final é `conectado`. Falhas de templates devem ser tratadas separadamente; a tela de templates pode ser usada imediatamente para sincronização.

## Variáveis obrigatórias

- `META_APP_ID`
- `META_APP_SECRET`
- `META_CONFIGURATION_ID`
- `META_GRAPH_VERSION`
- `META_EMBEDDED_SIGNUP_REDIRECT_URI`
- `META_VERIFY_TOKEN`
- `BASE_URL` calculado pela aplicação

O `META_EMBEDDED_SIGNUP_REDIRECT_URI` deve permanecer HTTPS e cadastrado na Meta como fallback/compatibilidade, mas o caminho principal usa o `code` retornado por `FB.login()` na página original.

## Configuração no painel Meta

- Habilitar Facebook Login for Business / Embedded Signup.
- Cadastrar o redirect URI público exato do callback: `https://SEU_DOMINIO/index.php?url=configuracao/metaCallback`.
- Garantir permissões `whatsapp_business_management` e `whatsapp_business_messaging`.
- Configurar o webhook existente com o `META_VERIFY_TOKEN` do ambiente.
- Confirmar que o app pode assinar a WABA por `subscribed_apps`.

## Migrations

Aplicar, nesta ordem, na hospedagem compartilhada:

1. `database/migrations/20260708_add_embedded_signup_meta_fields.sql`
2. `database/migrations/20260713_create_meta_embedded_signup_attempts.sql`
3. `database/migrations/20260713_finalize_embedded_signup_meta_fields.sql`
4. `database/migrations/20260713_expand_meta_contas_status_enum.sql`

A migration de 20260713 adiciona metadados operacionais do número. Ela também cria índice auxiliar não único para compatibilidade com bases que possam ter duplicidades históricas. Após auditoria/deduplicação, recomenda-se promover `CLI_ID + MTA_WabaId + MTA_PhoneNumberId` para índice único.



## Coordenação FINISH x callback OAuth

No fluxo principal, `code` e `FINISH` são coordenados na página original do Disparador: `FINISH` pode chegar antes ou depois de `authResponse.code`, e o frontend só chama `configuracao/finalizarEmbeddedSignup` quando há dados suficientes ou quando a janela curta de espera expira. O endpoint recebe `state`, `code`, CSRF e `sessionInfo`, grava os IDs recebidos na tabela compartilhada e consome o `state` de forma atômica. O redirect OAuth em nova aba permanece apenas como fallback documentado, não como caminho principal.

## Estados de conexão

- `conectado`: validações obrigatórias concluídas, assinatura da WABA confirmada com `success=true` e os campos operacionais retornados pela Meta não indicam pendência, bloqueio ou rejeição.
- `requer_acao`: validações obrigatórias e assinatura foram concluídas, mas algum campo retornado pela Meta indica ação pendente ou bloqueio, por exemplo `PENDING`, `PENDING_REVIEW`, `FLAGGED`, `REJECTED`, `DISCONNECTED`, `UNVERIFIED`, `NOT_VERIFIED` ou `EXPIRED` em `status`, `code_verification_status` ou `name_status`.
- `erro`: falha impeditiva antes da persistência final, como token inválido, WABA fora dos `target_ids`, telefone não pertencente à WABA, falha na assinatura do app ou resposta inesperada da Graph API.

Campos opcionais ausentes na resposta da Graph API não bloqueiam a conexão por si só; eles só mudam o status quando presentes e indicam pendência/bloqueio.

## Registro/ativação do Phone Number

Este fluxo não inventa PIN e não chama registro manual do número. No Embedded Signup atual, o cadastro guiado da Meta é responsável por registrar/verificar o número quando aplicável. Se a Meta retornar um telefone com estado que exija ação adicional, o Disparador registra os metadados retornados (`code_verification_status`, `name_status`, `status`) para diagnóstico. Uma sprint futura pode adicionar uma UI explícita para PIN caso a Meta passe a exigir registro manual para este fluxo.

## Diagnóstico

O log fica em `storage/logs/meta-embedded-signup-callback.log` com `request_id`, cliente, etapa, WABA, telefone e resultado. Nunca registrar access token, app secret, Authorization ou code completo. Em falhas, a página de callback mostra uma mensagem segura e o código de diagnóstico.

## Checklist de teste real

- Confirmar `.env` com todas as variáveis obrigatórias e redirect HTTPS correto.
- Aplicar as migrations na ordem documentada.
- Entrar com um cliente que tenha plano com número disponível.
- Clicar em **Conectar novo número** e confirmar que só uma tentativa fica ativa.
- Concluir o Embedded Signup escolhendo WABA e Phone Number conhecidos.
- Confirmar no log que as etapas `inicio`, `finish`, callback, validação e persistência têm o mesmo `request_id`.
- Confirmar que `meta_contas` contém WABA, Phone Number, Business ID, display phone, nome e status `conectado`.
- Repetir o callback/fluxo com a mesma WABA e telefone e confirmar que não duplica conta.
- Confirmar que o trial foi iniciado apenas uma vez.
- Abrir templates e executar a sincronização imediata.
- Confirmar no painel Meta que o webhook do app está assinado na WABA.

## Riscos remanescentes

- `MTA_Token` continua armazenado como texto compatível com o sistema atual. A criptografia em repouso deve ser priorizada na próxima sprint com rotação/compatibilidade de tokens existentes.
- O índice de unicidade ainda não é `UNIQUE` para evitar quebrar bases com dados históricos duplicados. Deduplicar e promover o índice deve ser feito antes de escala comercial maior.
