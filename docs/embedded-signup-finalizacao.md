# Finalização do Embedded Signup Meta

## Arquitetura do fluxo

1. A tela `configuracao/meta` chama `configuracao/iniciarEmbeddedSignup` por POST com CSRF.
2. O backend valida cliente autenticado, variáveis Meta, HTTPS do redirect URI e limite do plano. Em seguida cria `state` aleatório de uso único, `requestId` e uma tentativa temporária na tabela `meta_embedded_signup_attempts` com expiração de 30 minutos.
3. O frontend usa o Facebook JavaScript SDK já inicializado com `META_APP_ID`/`META_GRAPH_VERSION` e chama `FB.login()` com `config_id`, `response_type: code`, `override_default_response_type: true` e `extras.sessionInfoVersion`. O modo padrão é `traditional` e seu payload não contém `featureType`.
4. Quando a Meta envia `WA_EMBEDDED_SIGNUP` com `FINISH`, a própria página original do Disparador recebe o `window.message` e mantém os IDs em memória para finalizar o fluxo no backend. O backend valida CSRF, cliente autenticado, `state`, expiração e grava somente os IDs úteis (`waba_id`, `phone_number_id`, `business_id`) na tentativa temporária, desde que ela ainda não tenha sido consumida definitivamente pelo callback.
5. O callback de `FB.login()` recebe `authResponse.code`; o frontend coordena `code` e `FINISH` em qualquer ordem e chama `configuracao/finalizarEmbeddedSignup` com `state`, `code`, CSRF e `sessionInfo`.
6. Quando o `FINISH` trouxe IDs, o backend consulta exatamente a WABA e o Phone Number selecionados e confirma que o telefone pertence à WABA e que a WABA está nos `target_ids` do token. Sem IDs, o fallback só é aceito quando há exatamente uma WABA e um telefone possível.
7. O backend chama `/{waba_id}/subscribed_apps` via POST, de forma idempotente, e exige resposta `success=true` para confirmar a assinatura do app na WABA.
8. A conta é salva de forma idempotente por cliente + WABA + Phone Number, reativando a conta se já existir. O status final gravado segue a regra operacional documentada abaixo.
9. O trial é iniciado somente quando o status final é `conectado`. Falhas de templates devem ser tratadas separadamente; a tela de templates pode ser usada imediatamente para sincronização.

## Modalidades de onboarding

O contrato interno aceita somente `traditional` e `coexistence`. O modo fica persistido na tentativa antes da abertura da Meta e depois em `meta_contas`; valores nulos de contas antigas são tratados como `traditional`.

### Traditional

`FINISH` → OAuth/code → WABA + Phone Number → `subscribed_apps` → `pendente_registro` → PIN de 6 dígitos → `POST /{phone_number_id}/register` → sincronização → `conectado`.

O objeto tradicional do `FB.login()` mantém `sessionInfoVersion: 3`, `version: v4` e `state`, sem `featureType`.

### Coexistence

`FINISH_WHATSAPP_BUSINESS_APP_ONBOARDING` → OAuth/code → WABA + Phone Number → `subscribed_apps` → persistência sem PIN e sem `/{phone_number_id}/register`.

Somente esta modalidade acrescenta `extras.featureType = whatsapp_business_app_onboarding`. O status fica `conectado` apenas quando a Meta retorna `operational_status=CONNECTED` sem outro metadado impeditivo; na ausência dessa evidência, fica `requer_acao`, nunca `pendente_registro`, e o trial não é iniciado.

Coexistence é protegido por `META_COEXISTENCE_ENABLED`, cujo padrão é `false`. Não existe opção na UI de clientes. A flag **não pode ser habilitada em produção** antes da fase de webhook/conversas e homologação com um número Coexistence real.

## Variáveis obrigatórias

- `META_APP_ID`
- `META_APP_SECRET`
- `META_CONFIGURATION_ID`
- `META_GRAPH_VERSION`
- `META_EMBEDDED_SIGNUP_REDIRECT_URI`
- `META_VERIFY_TOKEN`
- `META_COEXISTENCE_ENABLED=false` para manter a infraestrutura desativada
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
5. `database/migrations/20260812_add_meta_coexistence_onboarding_infra.sql`
6. `database/migrations/20260812_add_conversa_message_origin.sql`
7. `database/migrations/20260812_add_meta_coexistence_sync_operations.sql`

A migration de 20260713 adiciona metadados operacionais do número. Ela também cria índice auxiliar não único para compatibilidade com bases que possam ter duplicidades históricas. Após auditoria/deduplicação, recomenda-se promover `CLI_ID + MTA_WabaId + MTA_PhoneNumberId` para índice único.

A migration de 20260812 adiciona os metadados internos das modalidades Traditional/Coexistence: `meta_contas.MTA_OnboardingType`, `meta_contas.MTA_PlatformType` e `meta_embedded_signup_attempts.onboarding_type`.

A migration de origem de mensagens adiciona `conversa_mensagens.MSG_Origem`, com os valores `api`, `business_app` e `history`. Ela não cria índice `UNIQUE`, pois bases existentes podem conter `MSG_MetaMessageId` históricos duplicados.

A migration operacional Coexistence registra os `request_id`, estado/progresso do sync, lifecycle sanitizado e a fila transitória de chunks de histórico. O payload é necessário apenas enquanto o job está pendente e é apagado depois do processamento.



## Coordenação FINISH x callback OAuth

No fluxo principal, `code` e `FINISH` são coordenados na página original do Disparador: `FINISH` pode chegar antes ou depois de `authResponse.code`, e o frontend só chama `configuracao/finalizarEmbeddedSignup` quando há dados suficientes ou quando a janela curta de espera expira. O endpoint recebe `state`, `code`, CSRF e `sessionInfo`, grava os IDs recebidos na tabela compartilhada e consome o `state` de forma atômica. O redirect OAuth em nova aba permanece apenas como fallback documentado, não como caminho principal.

## Estados de conexão

- `conectado`: validações obrigatórias concluídas, assinatura da WABA confirmada com `success=true` e os campos operacionais retornados pela Meta não indicam pendência, bloqueio ou rejeição.
- `requer_acao`: validações obrigatórias e assinatura foram concluídas, mas algum campo retornado pela Meta indica ação pendente ou bloqueio, por exemplo `PENDING`, `PENDING_REVIEW`, `FLAGGED`, `REJECTED`, `DISCONNECTED`, `UNVERIFIED`, `NOT_VERIFIED` ou `EXPIRED` em `status`, `code_verification_status` ou `name_status`.
- `erro`: falha impeditiva antes da persistência final, como token inválido, WABA fora dos `target_ids`, telefone não pertencente à WABA, falha na assinatura do app ou resposta inesperada da Graph API.

Campos opcionais ausentes na resposta da Graph API não bloqueiam a conexão por si só; eles só mudam o status quando presentes e indicam pendência/bloqueio.

## Registro/ativação do Phone Number

No fluxo tradicional, o Disparador solicita ao cliente um PIN de seis dígitos depois do Embedded Signup e chama `POST /{phone_number_id}/register`. O PIN é usado apenas nessa requisição, não é persistido e não deve aparecer em logs. A conta só passa de `pendente_registro` para `conectado` depois da confirmação desse registro.

No fluxo Coexistence, o endpoint de PIN é proibido no frontend e no backend e `/{phone_number_id}/register` não é chamado.

## Phase 2A — ecos do WhatsApp Business App

O webhook roteia explicitamente `messages` e `smb_message_echoes`. Em ecos, `message_echoes[].from` é validado como o número da empresa e `message_echoes[].to` é o participante da conversa. Variantes ambíguas são apenas registradas com contexto seguro e não criam conversa.

Ecos válidos são persistidos como `MSG_Direcao=enviada`, `MSG_Status=sent` e `MSG_Origem=business_app`. Esse caminho não cria contato inbound, não incrementa não lidas e não pode chamar a auto resposta.

Mensagens normais e ecos usam deduplicação por `MTA_ID + MSG_MetaMessageId`. A aplicação bloqueia a linha da conta Meta em transação antes de consultar e inserir, evitando retries concorrentes. Se uma mensagem enviada pela API já existir com o mesmo wamid, ela é preservada sem duplicação nem troca de origem. Um índice único fica adiado até auditoria/deduplicação dos dados históricos.

## Phase 2B — histórico e estado do aplicativo

O campo `history` é uma sincronização passiva. Somente mensagens com wamid, timestamp válido, thread telefônica, direção comprovada e tipo suportado são importadas. Os tipos aceitos são texto, botão/interativo, imagem, vídeo, documento, áudio, sticker, localização, contatos e `media_placeholder`; tipos desconhecidos, edit/revoke e variantes ambíguas são adiados. Uma mensagem é inbound quando `from` corresponde ao participante da thread; é outbound quando `from` corresponde ao número da empresa. Para outbound, `to` é opcional no histórico normal e, quando presente, deve corresponder à thread.

Mensagens históricas novas usam `MSG_Origem=history`, preservam o timestamp Meta e não criam contatos, não incrementam não lidas, não chamam auto resposta, não geram notificações e não participam de consumo, campanhas ou faturamento. A deduplicação por conta/wamid preserva integralmente direção, origem e status de mensagens já existentes da API, inbound ou Business App.

O resumo da conversa só é atualizado quando o timestamp histórico é posterior ao resumo existente. A listagem das mensagens usa `MSG_DataMensagem` e `MSG_ID`, garantindo ordem cronológica mesmo quando chunks chegam fora de ordem.

O campo `smb_app_state_sync` não é tratado como mensagem. Nesta fase, somente estados `contact` com ações add/create/update são aplicados: o telefone é normalizado no cliente proprietário da conta Meta, contatos ausentes são criados e contatos existentes são preservados sem alterar nome ou dados locais. Remoções, tipos não-contact e qualquer estado de chat/leitura são deliberadamente adiados por não haver equivalência segura no domínio atual.

Nenhuma migration adicional é necessária na Phase 2B; `MSG_Origem=history` já foi criado na Phase 2A e contatos/conversas existentes cobrem os dados suportados.

## Phase 2C — alinhamento com a documentação oficial

Depois de um onboarding Coexistence conectado, a aplicação solicita `POST /{phone_number_id}/smb_app_data` primeiro com `sync_type=smb_app_state_sync` e depois com `sync_type=history`. A solicitação deve ocorrer dentro de 24 horas do onboarding. Cada operação é one-time por ciclo da conta: `request_id` e estado são persistidos, e uma resposta aceita significa apenas solicitação recebida, não sincronização concluída. O fluxo tradicional nunca chama esse endpoint.

History é assíncrono. O webhook valida assinatura e conta, persiste cada chunk numa fila transacional e responde sem importar milhares de mensagens inline. O worker existente reserva jobs individualmente; chunks fora de ordem são seguros porque a correção depende de timestamp e idempotência, não de sequência. `phase`, `chunk_order`, `progress` e timestamp do evento servem para monitoramento; `progress=100` marca conclusão geral.

No histórico, uma saída é comprovada por `thread.id` do cliente e `from` igual ao número empresarial; `to` pode faltar e, quando presente, deve coincidir com a thread. Status oficiais mapeiam assim: `PENDING→pending`, `SENT→sent`, `DELIVERED→delivered`, `READ→read`, `PLAYED→read` (sem criar UI nova) e `ERROR→failed`. Estados desconhecidos são adiados. Deduplicação não rebaixa status. A única atualização de duplicata permitida é `media_placeholder` de origem history para mídia real documentada com o mesmo wamid; preservam-se ID, conversa, direção e origem.

O erro `2593109` significa compartilhamento de histórico recusado/desabilitado: history fica `declined`, sem desconectar a conta nem repetir indefinidamente; state sync continua independente. O erro `131060` é reconhecido e registrado como limitação esperada, sem desconexão automática.

`PARTNER_REMOVED`, `ACCOUNT_OFFBOARDED` e `ACCOUNT_RECONNECTED` são tratados como lifecycle de conta, nunca como mensagens. Motivo e `initiated_by` de remoção são sanitizados; não há deregister, faturamento, referral ou outro efeito colateral.

Ao vincular uma conta WhatsApp Business App existente, dispositivos companheiros conectados são desconectados e dispositivos compatíveis podem precisar ser vinculados novamente. A experiência futura de onboarding deverá avisar isso antes da abertura da Meta.

O limite oficial de Coexistence é 20 mensagens por segundo. O Disparador já limita globalmente o worker a 5 envios por segundo, portanto esta fase documenta o limite e não altera throttling de contas tradicionais. Se o limite global for elevado no futuro, o controle deverá considerar `MTA_OnboardingType`.

## Bloqueio para produção

As três famílias de webhook de Coexistence possuem infraestrutura defensiva, mas `META_COEXISTENCE_ENABLED=false` permanece obrigatório até homologação com número real. Ainda precisam ser comprovados em ambiente Meta real: formatos e ações efetivamente emitidos, ordem/retry dos chunks, variantes de mídia, usernames/BSUID sem telefone, volumes e concorrência, entrega ao webhook correto e consistência de wamids entre onboarding e novas sincronizações.

Também permanecem bloqueadores reais: timing do `FINISH`, metadados Graph, aceitação e prazo das solicitações de sync, volume/ordem reais dos chunks, payload de enriquecimento de mídia, variantes de contatos, comportamento de dispositivos vinculados, disconnect/reconnect e uso simultâneo do Business App com a Cloud API.

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
