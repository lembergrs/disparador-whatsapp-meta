# E-mail de boas-vindas do cadastro público

## Momento do envio

O e-mail de boas-vindas é tentado após a conclusão do cadastro público, somente depois do `COMMIT` que cria o cliente e o usuário principal. O envio não participa da transação principal e não altera o início do período de avaliação.

O trial continua começando apenas após a conexão do número do WhatsApp com a Meta, conforme regra operacional vigente.

## Conteúdo

O assunto enviado é:

> Bem-vindo ao Disparador.net — veja os próximos passos

O HTML e o texto alternativo orientam o cliente a acessar a conta, iniciar a conexão com a Meta, usar um administrador do Portfólio Empresarial, selecionar ou cadastrar empresa/WhatsApp, confirmar o número e preparar templates, contatos e disparos. A mensagem informa explicitamente que o período de avaliação começa somente após a conexão do número do WhatsApp.

Nenhuma senha, token, segredo, código fiscal ou detalhe técnico de API é incluído no corpo.

## Variáveis SMTP

As configurações são lidas do ambiente/.env por `config/config.php`:

- `MAIL_HOST`
- `MAIL_PORT` (padrão `587`)
- `MAIL_USERNAME`
- `MAIL_PASSWORD`
- `MAIL_ENCRYPTION` (`tls`, `ssl`, `smtps` ou vazio)
- `MAIL_FROM_ADDRESS`
- `MAIL_FROM_NAME` (padrão `Disparador.net`)
- `MAIL_REPLY_TO_ADDRESS`
- `MAIL_REPLY_TO_NAME` (padrão `Suporte Disparador.net`)
- `MAIL_TIMEOUT` (padrão `10` segundos)

Credenciais reais não devem ser versionadas.

## Não bloqueio do cadastro

Falhas de SMTP, configuração ausente, destinatário inválido ou indisponibilidade do transportador não desfazem cliente/usuário, não impedem o redirecionamento e não expõem erro técnico ao usuário público.

Quando o envio é confirmado, a mensagem de sucesso informa que os próximos passos foram enviados por e-mail. Quando falha, a tela mantém mensagem neutra de cadastro concluído.

## Idempotência

Cada cliente recebe uma chave lógica única:

```text
email:boas_vindas:cliente:{CLI_ID}
```

A tabela `notificacoes_transacionais` possui unique nessa chave. Se o POST for repetido, a tentativa já registrada impede duplicidade de envio.

## Persistência

A migration `20260717_create_notificacoes_transacionais.sql` cria a tabela genérica de notificações transacionais, já preparada para futuros eventos, como e-mail de pagamento confirmado, sem implementar esse fluxo agora.

Campos principais: cliente, usuário, cobrança opcional, tipo, canal, destinatário, assunto, status, tentativas, último erro seguro, data de criação, data de envio e chave idempotente.

## Logs

Eventos são registrados em:

```text
storage/logs/email-transacional.log
```

O log é JSONL sanitizado com timestamp, tipo, `CLI_ID`, `USU_ID`, status, tentativas, destinatário mascarado e código seguro. Não são registrados senha de usuário, senha SMTP, tokens, corpo completo, headers sensíveis ou stack trace.

## Testes

Os testes usam fakes/mocks de transporte e não fazem chamada real de SMTP. Eles validam idempotência, assunto, BASE_URL, HTML, AltBody, escape de variáveis, regra de trial, não exposição de senha/credenciais e integração do controller após o commit.

## Implantação

1. Revisar `.env` de produção e preencher variáveis `MAIL_*`.
2. Aplicar a migration em janela controlada.
3. Garantir permissão de escrita em `storage/logs` para o usuário do Apache/PHP.
4. Fazer cadastro público controlado em ambiente de homologação antes de produção.

## Rollback

Para rollback de código, remover o acionamento do `EmailBoasVindasService` no cadastro público. A tabela pode permanecer sem impacto operacional. Se necessário, remover a tabela apenas após confirmar que não há dependências futuras.

## Futuro Worker

O envio atual é síncrono controlado após o commit. A estrutura de persistência permite migrar futuramente para um Worker que consuma notificações pendentes/temporárias sem alterar o contrato funcional do cadastro.

## Futuro e-mail de pagamento confirmado

A tabela foi desenhada para suportar outros tipos transacionais (`NOT_Tipo`) e canais, mas esta etapa implementa somente `boas_vindas` por `email`.

## Recuperação de senha por e-mail

Os serviços de e-mail transacional ficam no namespace `Services\Email` e nos arquivos:

- `app/Services/Email/EmailTransacionalService.php`
- `app/Services/Email/EmailBoasVindasService.php`
- `app/Services/Email/EmailRecuperacaoSenhaService.php`

O fluxo de recuperação utiliza `Services\RecuperacaoSenhaService`, `Models\RecuperacaoSenha` e a tabela `recuperacoes_senha`.

### Fluxo

1. O usuário acessa `login/recuperarSenha` pelo link “Esqueceu sua senha?”.
2. O formulário envia o e-mail com CSRF para `login/enviarRecuperacao`.
3. A resposta pública é sempre neutra, independentemente de o e-mail existir, estar inativo, ser inválido ou sofrer limitação.
4. Para usuários recuperáveis, o sistema gera `bin2hex(random_bytes(32))`, salva somente `hash('sha256', $token)` e envia o link por e-mail.
5. O link `login/redefinirSenha&token=...` é válido por 30 minutos e só pode ser usado uma vez.
6. Ao salvar a nova senha, a operação valida novamente o token em transação, grava `password_hash()`, marca o token como utilizado e invalida outros pendentes.

### Segurança

- O token puro existe apenas no link enviado por e-mail.
- A tabela `recuperacoes_senha` armazena apenas `RSE_TokenHash`.
- A notificação transacional usa `NOT_Tipo = email_recuperacao_senha` e chave idempotente por solicitação, sem registrar URL completa com token.
- O serviço não faz login automático após a redefinição.
- As views usam CSRF separado do token de recuperação.
- A mensagem pública não revela existência, estado, bloqueio da conta ou aplicação de limite de solicitações.
- Há limitação básica por IP e por usuário/e-mail antes da criação de uma nova solicitação efetiva.

### Deploy e rollback

Aplicar a migration `20260717_create_recuperacoes_senha.sql` em janela controlada. Como não há nova dependência nesta etapa além do PHPMailer já declarado para e-mails transacionais, não é necessário alterar `composer.lock`; se a estrutura de autoload estiver cacheada, executar `composer dump-autoload`.

Para rollback, remover as rotas/métodos de recuperação no `LoginController`, manter ou descartar a tabela `recuperacoes_senha` após avaliar histórico/auditoria, e manter intacto o e-mail de boas-vindas.
