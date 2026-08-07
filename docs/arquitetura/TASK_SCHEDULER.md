# Task Scheduler central

## Objetivo

O `TaskSchedulerService` fornece uma infraestrutura genérica e paralela para tarefas imediatas ou futuras. Nenhum fluxo legado foi migrado nesta entrega: campanhas, disparos manuais, onboarding, notificações, trial, financeiro, NFS-e, WhatsApp institucional e workers existentes continuam funcionando como antes.

O Programa de Indicação será um consumidor futuro desta infraestrutura.

```text
Módulo
  ↓
TaskSchedulerService
  ↓
tarefas_agendadas
  ↓
TaskExecutionService / processar_tarefas.php
  ↓
TaskProcessor → TaskDispatcher → TaskRegistry → Handler permitido
```

## Componentes

- `TaskSchedulerService`: valida e agenda tarefas, inclusive `agendarAgora()`, além de cancelar e reagendar.
- `TarefaAgendada`: persistência, idempotência, reserva atômica, lease, conclusão, retry e falha.
- `TaskRegistry`: catálogo fechado de tipos. O banco nunca escolhe uma classe arbitrária.
- `TaskDispatcher`: decodifica o JSON e chama o handler autorizado.
- `TaskProcessor`: reserva tarefas elegíveis, executa handlers fora da transação e aplica o ciclo de sucesso/retry/falha.
- `TaskExecutionService`: aciona o mesmo `TaskProcessor` sob demanda, sem criar um segundo motor de execução.
- `TaskRetryPolicy`: backoff central de 5, 15 e 60 minutos.
- `processar_tarefas.php`: entrada CLI finita, com `flock`, lote configurável e log sanitizado.

## Banco e estados

A migration `database/migrations/20260807_create_tarefas_agendadas.sql` cria `tarefas_agendadas`. Ela não é executada automaticamente.

Estados:

- `pendente`
- `processando`
- `concluida`
- `falha`
- `cancelada`

Retry retorna a tarefa para `pendente` com `TAG_ProximaTentativaEm`.

A chave opcional `TAG_ChaveIdempotencia` possui índice único e impede duplicidade lógica inclusive sob concorrência.

## Reserva, concorrência e lease

A reserva ocorre em transação curta. No MariaDB é utilizado `SELECT ... FOR UPDATE`, seguido de update condicional para `processando`, incremento de tentativa, `TAG_WorkerId` e `TAG_ReservadaEm`. A transação é concluída antes de executar o handler.

Tarefas em `processando` com lease expirado tornam-se novamente elegíveis se ainda houver tentativas. Se o limite estiver esgotado, terminam em `falha`.

O `flock` do CLI evita sobreposição local; a reserva no banco continua sendo a garantia entre processos.

## Retry

- primeira falha temporária: +5 minutos;
- segunda: +15 minutos;
- seguintes: +60 minutos;
- atingido `TAG_MaxTentativas`: `falha`.

Handlers indicam falha temporária com `TaskRetryException` e permanente com `TaskPermanentFailureException`.

## Execução imediata x agendada

`agendarAgora()` apenas persiste a tarefa com `TAG_ExecutarEm` no momento atual. Ele não executa automaticamente trabalho pesado dentro da requisição HTTP.

Quando for necessário iniciar o processamento sem esperar a próxima execução do cron, um módulo pode usar `TaskExecutionService::processarSobDemanda()`.

Esse método delega ao mesmo `TaskProcessor`; por isso continuam valendo reserva atômica, lease, prioridade, elegibilidade, retry, backoff, idempotência e concorrência.

Tarefas futuras nunca são antecipadas. A consulta continua exigindo `TAG_ExecutarEm <= agora` e, quando existir, `TAG_ProximaTentativaEm <= agora`.

### Fallback

Se uma tarefa criada com `agendarAgora()` não receber o gatilho sob demanda, permanece `pendente`. O cron a encontrará posteriormente.

```text
agendarAgora()
   ↓
pendente e elegível
   ├─ processamento sob demanda → TaskExecutionService → TaskProcessor
   └─ sem gatilho               → cron → TaskExecutionService → TaskProcessor
```

Para operações longas, controllers futuros não devem ficar aguardando o handler pesado. O request pode registrar a tarefa e disparar um mecanismo sob demanda apropriado, mantendo o cron como fallback.

## Payload e segurança

O payload é JSON, limitado a 16 KB e profundidade controlada. Deve conter somente referências e valores necessários, como IDs técnicos.

Não armazenar tokens, senhas, credenciais, payload externo bruto, SQL, nomes de classes, callbacks, funções, objetos serializados ou dados pessoais desnecessários. Não há `eval`, `unserialize` nem execução de shell baseada em payload.

## Catálogo

Nesta versão existe somente o tipo interno `teste_scheduler`, sem rota pública. Tipos funcionais futuros precisam ser adicionados explicitamente ao `TaskRegistry` e possuir handler que implemente `TaskHandlerInterface`.

Exemplo conceitual futuro para o Programa de Indicação:

```php
$scheduler->agendar(
    'liberar_credito_indicacao',
    ['indicacao_id' => $indicacaoId],
    $dataPagamento->modify('+7 days'),
    'liberar_credito_indicacao:' . $indicacaoId
);
```

Esse tipo ainda não está implementado.

## CLI e cron

Execução manual:

```bash
php processar_tarefas.php
```

Essa execução é silenciosa: um ciclo normal, inclusive sem tarefas elegíveis, não escreve resumo em `stdout` e não cria uma entrada de log para "zero tarefas". Para diagnóstico manual, use o modo verbose, que altera somente a saída e não as regras de processamento:

```bash
php processar_tarefas.php --verbose
```

O modo verbose imprime as quantidades de processadas, concluídas, retries e falhas.

Cron recomendado, somente após homologação:

```cron
* * * * * cd /opt/disparador-app && /usr/bin/php processar_tarefas.php > /dev/null 2>&1
```

O processador não é daemon: executa um lote e encerra. A latência típica do cron é de até 59 segundos; o processamento sob demanda existe para tarefas que precisam começar antes disso. O cron e o redirecionamento devem ser configurados manualmente somente depois de deploy e homologação.

## Logs

O arquivo definido por `TASK_SCHEDULER_LOG_FILE` (por padrão, `storage/logs/task-scheduler.log`) é o log oficial. Cada evento ocupa uma linha JSON (JSONL) com os campos permitidos: `data`, `nivel`, `tarefa_id`, `tipo`, `status`, `tentativa`, `duracao_ms` e, quando aplicável, `erro_codigo`.

Os níveis são:

- `INFO`: tarefa concluída;
- `WARNING`: retry ou recuperação de lease expirado;
- `ERROR`: falha definitiva de uma tarefa ou falha operacional do ciclo.

O ciclo vazio não é registrado. O logger usa uma lista fechada de campos e nunca registra payload completo, telefone, e-mail, token, senha, credenciais, mensagem original de exceção ou stack trace. Falhas operacionais são reduzidas a códigos genéricos e sanitizados, como `banco_indisponivel` ou `falha_inicializacao_ou_processamento`.

### Exit codes e troubleshooting

- `0`: o ciclo foi executado normalmente, com ou sem tarefas. Uma falha definitiva de tarefa individual também mantém `0`, pois ela é um resultado de negócio já tratado e registrado pelo scheduler;
- `1`: uma falha operacional impediu o ciclo, por exemplo indisponibilidade do banco, falha de inicialização ou exceção inesperada do processador.

Para investigar, execute `php processar_tarefas.php --verbose`, confira o exit code (`echo $?`) e consulte o JSONL oficial. Se o diretório do log não puder ser criado ou escrito, o processo retorna `1` e emite somente um aviso genérico em `stderr`, pois não existe meio de persistir no arquivo indisponível. Não habilite dump de payload ou stack trace como solução de diagnóstico.

### Rotação

Como o PHP CLI abre o arquivo somente durante cada execução curta, não é necessário usar `copytruncate`. Após homologar usuário, grupo e caminho do deploy, uma política conceitual de `/etc/logrotate.d` é:

```logrotate
/opt/disparador-app/storage/logs/task-scheduler.log {
    daily
    rotate 14
    compress
    delaycompress
    missingok
    notifempty
    create 0664 disparador disparador
}
```

Ela faz rotação diária, mantém 14 arquivos, compacta os antigos, ignora arquivo ausente ou vazio e cria o sucessor com permissões explícitas. Essa configuração deve ser instalada manualmente na infraestrutura; a aplicação não altera `logrotate`.

## Validação recomendada

Após aplicar a migration em ambiente controlado:

1. agendar uma tarefa futura e confirmar que não executa antes do horário;
2. agendar tarefa imediata e processá-la via `TaskExecutionService`;
3. confirmar que tarefa imediata sem gatilho permanece pendente para o cron;
4. testar retry e backoff;
5. testar falha permanente;
6. repetir uma chave idempotente;
7. simular lease expirado;
8. confirmar que dois workers não reservam a mesma tarefa.

## Limitações e roadmap

Não existe tela administrativa, daemon distribuído, heartbeat por tarefa ou histórico separado por tentativa. Nenhum módulo legado deve ser migrado em bloco. Consumidores futuros devem ser adicionados um a um, com PR e testes próprios.
