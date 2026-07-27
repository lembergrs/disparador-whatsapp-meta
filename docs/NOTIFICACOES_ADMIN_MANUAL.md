# Roteiro manual — gerenciamento administrativo de notificações

1. Aplicar as migrations `20260718_create_notificacoes.sql`, `20260718_create_notificacoes_configuracoes.sql` e `20260718_add_notificacoes_admin_indexes.sql` no ambiente seguro.
2. Entrar como administrador.
3. Abrir o menu **Notificações**.
4. Verificar o estado vazio, caso ainda não existam registros.
5. Cadastrar um cliente novo.
6. Confirmar a criação do registro `BOAS_VINDAS`.
7. Atualizar a listagem.
8. Filtrar pelo cliente.
9. Abrir os detalhes.
10. Confirmar assunto, destino, status e tentativa.
11. Realizar integração Meta do cliente.
12. Confirmar registro `META_CONECTADA`.
13. Simular falha SMTP em ambiente seguro, sem alterar SMTP de produção.
14. Verificar status de erro.
15. Clicar em **Reenviar**.
16. Confirmar atualização da tentativa.
17. Abrir **Configuração de canais**.
18. Desativar **E-mail** para `BOAS_VINDAS`.
19. Cadastrar outro cliente de teste.
20. Confirmar que o evento não chamou `EmailService`.
21. Reativar o canal.
22. Confirmar que WhatsApp é configurável somente nos três eventos suportados e que Interno, Push e SMS permanecem como **Em breve**.
23. Confirmar que cliente comum não acessa o módulo, detalhes, reenvio ou configuração.

## WhatsApp institucional de onboarding

A Central usa a mesma persistência, configuração por evento, histórico e estados para e-mail e WhatsApp. O canal WhatsApp utiliza **somente o número institucional do Disparador.net** como origem e `CLI_Telefone`, informado no cadastro, como destino. Credenciais e números Meta dos clientes nunca são encaminhados ao serviço institucional.

| Evento | Template | Parâmetros confirmados |
|---|---|---|
| Boas-vindas | `boas_vindas_cadastro` | `{{1}}`: nome do cliente |
| Cadastro pendente de conexão | `cadastro_pendente_conexao` | Nenhum parâmetro documentado no repositório |
| Conta Meta conectada | `conexao_meta_concluida` | Nenhum parâmetro documentado; aguardando aprovação |

As associações ficam em `WhatsAppInstitucionalService::TEMPLATES` e aparecem somente para leitura no painel. Antes de homologar os dois templates sem parâmetros documentados, confirme no WhatsApp Manager que eles realmente não possuem variáveis. Se houver variáveis, ajuste apenas o mapa centralizado e os testes.

### Configuração

Aplicar manualmente, com backup, `database/migrations/20260727_add_notificacoes_whatsapp_institucional.sql`. Ela complementa `notificacoes`; não cria outra Central.

```dotenv
WHATSAPP_INSTITUCIONAL_PHONE_NUMBER_ID=
WHATSAPP_INSTITUCIONAL_WABA_ID=
WHATSAPP_INSTITUCIONAL_ACCESS_TOKEN=
WHATSAPP_INSTITUCIONAL_API_VERSION=v23.0
WHATSAPP_INSTITUCIONAL_IDIOMA=pt_BR
WHATSAPP_INSTITUCIONAL_TIMEOUT=15
```

O token, Authorization e payload integral não são persistidos. Na normalização, símbolos são removidos, números brasileiros de 10/11 dígitos recebem DDI 55, números já internacionais não recebem outro 55 e comprimentos inválidos são rejeitados. A tela mascara destinos WhatsApp.

Boas-vindas vem habilitado por padrão para e-mail e WhatsApp. Cadastro pendente e Conta Meta conectada exigem ativação administrativa. **Não ative Conta Meta conectada enquanto `conexao_meta_concluida` não estiver aprovado.** Após a aprovação, confirme as credenciais e ative o checkbox; não é necessária mudança de código.

### Idempotência, tentativas e rotina de 24 horas

A chave única é `cliente:{id}:whatsapp:{evento}`. A reserva usa `INSERT ... ON DUPLICATE KEY`, e a transição condicional para processando acontece antes da Graph API, sem transação aberta durante HTTP. O limite é cinco tentativas; 429, 5xx e rede são temporários, enquanto configuração, telefone e rejeições 4xx são definitivos.

Comando real:

```bash
php processar_notificacoes_onboarding.php
```

Frequência recomendada, substituindo pelo caminho real da instalação:

```cron
0 * * * * cd /caminho/real/do/disparador && php processar_notificacoes_onboarding.php
```

O comando usa lock, lotes de 100 e revalida antes da reserva se o cliente segue ativo, tem cadastro há pelo menos 24 horas, não iniciou trial e continua sem WABA/número persistidos. Falha individual não interrompe o lote.

### Homologação

1. Use um número institucional de teste e templates aprovados.
2. Ative WhatsApp somente no evento testado.
3. Cadastre um cliente com telefone controlado e confirme registros independentes para e-mail e WhatsApp.
4. Repita evento/comando e confirme ausência de duplicidade.
5. Prepare clientes com 23h59 e 24h e conecte um antes do comando para validar a rechecagem.
6. Conclua Embedded Signup pelo backend e confira o evento após persistência.
7. No detalhe, confira template, telefone mascarado, tentativas, message ID ou erro sanitizado.
