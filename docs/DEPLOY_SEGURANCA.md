# Deploy seguro — Disparador WhatsApp Meta

Este projeto não deve versionar credenciais reais. Todas as informações sensíveis devem ser configuradas por variáveis de ambiente ou por um arquivo `.env` local não versionado.

## Variáveis obrigatórias

```dotenv
APP_ENV=production
DB_HOST=
DB_NAME=
DB_USER=
DB_PASS=
DB_PORT=3306
RECAPTCHA_SITE_KEY=
RECAPTCHA_SECRET_KEY=
META_APP_SECRET=
```

### Descrição

- `APP_ENV`: use `local` em desenvolvimento e `production` em produção.
- `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_PORT`: dados de conexão com o banco.
- `RECAPTCHA_SITE_KEY` e `RECAPTCHA_SECRET_KEY`: chaves do Google reCAPTCHA.
- `META_APP_SECRET`: App Secret da aplicação Meta usado para validar `X-Hub-Signature-256` no webhook.

## Laragon / ambiente local

1. Copie `.env.example` para `.env` na raiz do projeto.
2. Preencha os dados locais, por exemplo banco local do Laragon.
3. Use `APP_ENV=local` para permitir `display_errors=1` e `E_ALL` durante desenvolvimento.
4. Nunca commite o arquivo `.env`.

Exemplo local sem segredos reais:

```dotenv
APP_ENV=local
DB_HOST=localhost
DB_NAME=whatsapp_disparador
DB_USER=root
DB_PASS=
DB_PORT=3306
RECAPTCHA_SITE_KEY=site_key_local
RECAPTCHA_SECRET_KEY=secret_key_local
META_APP_SECRET=app_secret_meta_local
```

## Hostinger / produção

1. Configure as variáveis no painel/ambiente da Hostinger quando disponível.
2. Se o ambiente não disponibilizar variáveis, crie um arquivo `.env` manualmente na raiz do projeto no servidor.
3. Use obrigatoriamente `APP_ENV=production`.
4. Confirme que o arquivo `.env` não está dentro de `public` e não fica acessível por URL.
5. Confirme que `display_errors` está desativado em produção. O sistema usa `APP_ENV=production` para forçar `display_errors=0` e registrar erros em `storage/logs/php-error.log` quando possível.

## META_APP_SECRET é obrigatório para webhook

O POST do webhook da Meta exige o header `X-Hub-Signature-256`. O backend calcula um HMAC SHA-256 usando `META_APP_SECRET` e rejeita a requisição com HTTP 403 se a assinatura estiver ausente ou inválida.

Sem `META_APP_SECRET` configurado, eventos reais da Meta serão rejeitados por segurança.

## Rotação obrigatória de credenciais expostas

As credenciais que já apareceram no código-fonte devem ser consideradas comprometidas. Antes do piloto real:

1. Rotacione a senha do banco de dados.
2. Gere novas chaves do reCAPTCHA.
3. Revise e rotacione qualquer segredo Meta que possa ter sido exposto.
4. Atualize as novas credenciais somente no ambiente ou no `.env` privado do servidor.

## Regras de segurança

- Nunca versionar `.env`.
- Nunca colocar senha real, token real ou chave real em `.env.example`.
- Manter `APP_ENV=production` em produção.
- Manter `display_errors` desativado em produção.
- Garantir permissão restrita para `.env` e para `storage/logs`.
- Validar o webhook Meta em produção após configurar `META_APP_SECRET`.
