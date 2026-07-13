# Finalização do Embedded Signup Meta

## Arquitetura do fluxo

1. A tela `configuracao/meta` chama `configuracao/iniciarEmbeddedSignup` por POST com CSRF.
2. O backend valida cliente autenticado, variáveis Meta, HTTPS do redirect URI e limite do plano. Em seguida cria `state` aleatório de uso único, `requestId` e uma tentativa temporária em sessão PHP com expiração de 30 minutos.
3. O frontend abre `https://business.facebook.com/messaging/whatsapp/onboard/` usando `app_id`, `config_id`, `redirect_uri`, `scope` e o `state` retornado pelo backend.
4. Quando a Meta envia `WA_EMBEDDED_SIGNUP` com `FINISH`, o navegador envia o `sessionInfo` para `configuracao/registrarEmbeddedSignupFinish`. O backend valida CSRF, cliente autenticado, `state`, expiração e grava somente os IDs úteis (`waba_id`, `phone_number_id`, `business_id`) na tentativa temporária.
5. O callback OAuth recebe `code` e `state`, consome a tentativa uma única vez, troca o `code` por token no backend, valida `debug_token`, permissões, app_id e expiração.
6. Quando o `FINISH` trouxe IDs, o backend consulta exatamente a WABA e o Phone Number selecionados e confirma que o telefone pertence à WABA e que a WABA está nos `target_ids` do token. Sem IDs, o fallback só é aceito quando há exatamente uma WABA e um telefone possível.
7. O backend chama `/{waba_id}/subscribed_apps` de forma idempotente para confirmar a assinatura do app na WABA.
8. A conta é salva de forma idempotente por cliente + WABA + Phone Number, reativando a conta se já existir. O status final gravado é `conectada` somente após validações e assinatura.
9. O trial é iniciado somente após a conta estar conectada. Falhas de templates devem ser tratadas separadamente; a tela de templates pode ser usada imediatamente para sincronização.

## Variáveis obrigatórias

- `META_APP_ID`
- `META_APP_SECRET`
- `META_CONFIGURATION_ID`
- `META_GRAPH_VERSION`
- `META_EMBEDDED_SIGNUP_REDIRECT_URI`
- `META_VERIFY_TOKEN`
- `BASE_URL` calculado pela aplicação

O `META_EMBEDDED_SIGNUP_REDIRECT_URI` deve ser HTTPS, público, exatamente igual ao cadastrado no painel da Meta e apontar para a hospedagem compartilhada atual enquanto ela for a aplicação pública.

## Configuração no painel Meta

- Habilitar Facebook Login for Business / Embedded Signup.
- Cadastrar o redirect URI público exato do callback: `https://SEU_DOMINIO/index.php?url=configuracao/metaCallback`.
- Garantir permissões `whatsapp_business_management` e `whatsapp_business_messaging`.
- Configurar o webhook existente com o `META_VERIFY_TOKEN` do ambiente.
- Confirmar que o app pode assinar a WABA por `subscribed_apps`.

## Migrations

Aplicar, nesta ordem, na hospedagem compartilhada:

1. `database/migrations/20260708_add_embedded_signup_meta_fields.sql`
2. `database/migrations/20260713_finalize_embedded_signup_meta_fields.sql`

A migration de 20260713 adiciona metadados operacionais do número. Ela também cria índice auxiliar não único para compatibilidade com bases que possam ter duplicidades históricas. Após auditoria/deduplicação, recomenda-se promover `CLI_ID + MTA_WabaId + MTA_PhoneNumberId` para índice único.

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
- Confirmar que `meta_contas` contém WABA, Phone Number, Business ID, display phone, nome e status `conectada`.
- Repetir o callback/fluxo com a mesma WABA e telefone e confirmar que não duplica conta.
- Confirmar que o trial foi iniciado apenas uma vez.
- Abrir templates e executar a sincronização imediata.
- Confirmar no painel Meta que o webhook do app está assinado na WABA.

## Riscos remanescentes

- `MTA_Token` continua armazenado como texto compatível com o sistema atual. A criptografia em repouso deve ser priorizada na próxima sprint com rotação/compatibilidade de tokens existentes.
- O índice de unicidade ainda não é `UNIQUE` para evitar quebrar bases com dados históricos duplicados. Deduplicar e promover o índice deve ser feito antes de escala comercial maior.
