# Publicação do worker na VPS de produção

Este guia prepara a execução contínua do `worker.php` do Disparador em uma VPS Linux/Hostinger com PHP CLI, Composer, MySQL/MariaDB e systemd.

## Como o worker deve ser executado

O worker é um processo de linha de comando e deve ser iniciado a partir da raiz do projeto:

```bash
cd /var/www/disparador-whatsapp-meta
php worker.php
```

O arquivo `worker.php` bloqueia execução via navegador, carrega `config/config.php` e `vendor/autoload.php` por caminhos absolutos baseados em `__DIR__`, usa o `.env` da raiz pelo carregador de configuração do projeto e grava logs em `storage/logs/worker.log`. Erros do PHP em CLI são enviados para `storage/logs/worker-error.log`.

## Proteção contra múltiplas instâncias

O worker usa lock por arquivo em `storage/worker.lock` com `flock(LOCK_EX | LOCK_NB)`. Se outra instância já estiver em execução, a nova execução encerra sem processar a fila. Isso evita dois workers processando a mesma fila em paralelo.

> Atenção: o lock protege uma única cópia do projeto/servidor. Se houver mais de uma VPS, container, release path ou cópia distinta do repositório apontando para o mesmo banco, será necessário um lock compartilhado no banco ou em serviço externo.

## Pré-requisitos na VPS

- PHP CLI compatível com o projeto e extensões necessárias para PDO/MySQL.
- Composer instalado.
- MySQL/MariaDB acessível com as credenciais do `.env`.
- Usuário Linux da aplicação, por exemplo `disparador`, sem usar `root` para rodar o serviço.
- Diretório `storage/logs` gravável pelo usuário do serviço.
- `.env` de produção criado manualmente na raiz do projeto, sem versionar segredos.

## Copiar ou atualizar código

### Primeira publicação

```bash
sudo mkdir -p /var/www
sudo chown -R disparador:www-data /var/www
git clone git@github.com:lembergrs/disparador-whatsapp-meta.git /var/www/disparador-whatsapp-meta
cd /var/www/disparador-whatsapp-meta
git checkout main
```

### Atualização de uma instalação existente

```bash
cd /var/www/disparador-whatsapp-meta
git fetch origin
git checkout main
git pull --ff-only origin main
```

Se for publicar esta branch antes do merge:

```bash
cd /var/www/disparador-whatsapp-meta
git fetch origin
git checkout fix/worker-vps-deploy
git pull --ff-only origin fix/worker-vps-deploy
```

## Configurar `.env` e permissões

Crie/atualize o `.env` diretamente na VPS com os dados reais de produção. Não copie senhas para o repositório.

```bash
cd /var/www/disparador-whatsapp-meta
sudo install -d -o disparador -g www-data -m 0775 storage storage/logs
sudo chown -R disparador:www-data storage
sudo chmod -R ug+rwX storage
```

## Instalar dependências Composer

```bash
cd /var/www/disparador-whatsapp-meta
composer install --no-dev --optimize-autoloader
```

## Aplicar migrations pendentes

O projeto possui migrations SQL em `database/migrations`. Revise cada arquivo antes de aplicar e execute somente as pendentes no banco de produção.

Exemplo aplicando todos os arquivos versionados em ordem alfabética:

```bash
cd /var/www/disparador-whatsapp-meta
for file in database/migrations/*.sql; do
  echo "Aplicando ${file}"
  mysql --host="$DB_HOST" --port="${DB_PORT:-3306}" --user="$DB_USER" --password "$DB_NAME" < "$file"
done
```

Alternativa com credenciais informadas pelo cliente MySQL ou `~/.my.cnf`:

```bash
cd /var/www/disparador-whatsapp-meta
for file in database/migrations/*.sql; do
  echo "Aplicando ${file}"
  mysql "$DB_NAME" < "$file"
done
```

## Teste manual do worker

Execute manualmente antes de ativar o systemd:

```bash
cd /var/www/disparador-whatsapp-meta
php -l worker.php
php worker.php
```

Acompanhe os logs de arquivo:

```bash
tail -f storage/logs/worker.log storage/logs/worker-error.log
```

## Instalar serviço systemd

Copie o exemplo e ajuste `User`, `Group`, `WorkingDirectory` e `ExecStart` se o caminho da VPS for diferente:

```bash
cd /var/www/disparador-whatsapp-meta
sudo cp deploy/systemd/disparador-worker.service.example /etc/systemd/system/disparador-worker.service
sudo nano /etc/systemd/system/disparador-worker.service
sudo systemctl daemon-reload
sudo systemctl enable disparador-worker
sudo systemctl start disparador-worker
```

## Operação do serviço

```bash
sudo systemctl start disparador-worker
sudo systemctl stop disparador-worker
sudo systemctl restart disparador-worker
sudo systemctl status disparador-worker --no-pager
journalctl -u disparador-worker -f
```

Logs adicionais da aplicação:

```bash
cd /var/www/disparador-whatsapp-meta
tail -f storage/logs/worker.log storage/logs/worker-error.log
```

## Cuidados antes de ativar em produção

1. Confirmar que o `.env` da VPS aponta para o banco e credenciais de produção corretos.
2. Confirmar que a conta Meta/WhatsApp e templates usados nas campanhas estão prontos para envio real.
3. Rodar `php worker.php` manualmente uma vez e validar `storage/logs/worker.log`.
4. Confirmar que não existe outro cron, supervisor ou terminal executando `worker.php` na mesma aplicação.
5. Se houver múltiplos servidores ou múltiplas cópias do projeto usando o mesmo banco, implementar lock compartilhado antes de habilitar mais de um worker.
6. Aplicar migrations com backup recente do banco e conferência de quais arquivos já foram executados.
