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
- `TaskSchedulerLogger`: grava eventos relevantes em JSONL com campos fechados e sem payload.
- `TaskSchedulerCliOutput`: controla a saída manual `--verbose` sem interferir no processamento.
- `processar_tarefas.php`: entrada CLI finita, silenciosa por padrão, com `flock`, lote configurável e log sanitizado.

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

## CLI, saída e cron

A execução normal é silenciosa:

```bash
php processar_tarefas.php
```

Se nenhuma tarefa estiver elegível e não houver erro operacional, nada é escrito em stdout/stderr.

Para diagnóstico manual, use:

```bash
php processar_tarefas.php --verbose
```

O modo verbose mostra:

```text
Processadas: X
Concluídas: X
Retry: X
Falhas: X
```

O modo verbose altera somente a saída no terminal; não muda elegibilidade, prioridade, reserva, retry ou concorrência.

Cron recomendado:

```cron
* * * * * cd /opt/disparador-app && /usr/bin/php processar_tarefas.php > /dev/null 2>&1
```

O processador não é daemon: executa um lote e encerra. O cron é fallback para tarefas futuras, retries e tarefas imediatas que não receberam gatilho sob demanda.

### Exit codes

- `0`: ciclo do scheduler executado normalmente, com ou sem tarefas. Falhas funcionais de tarefas individuais permanecem registradas no próprio ciclo da tarefa e não tornam o processo CLI operacionalmente inválido.
- `1`: falha operacional que impediu o scheduler de executar corretamente.

## Logs

O arquivo configurado por `TASK_SCHEDULER_LOG_FILE` usa JSONL e registra apenas eventos relevantes. Ciclos vazios não geram linha de log.

Níveis:

- `INFO`: tarefa concluída;
- `WARNING`: retry ou recuperação de lease;
- `ERROR`: falha definitiva ou erro operacional do scheduler.

Campos permitidos: data, nível, ID da tarefa, tipo, status, tentativa, duração e código resumido de erro. Não registrar payload, tokens, senhas, credenciais, telefones, e-mails, stack traces ou mensagens externas brutas.

Exemplo:

```json
{"data":"2026-08-07T12:00:00-03:00","nivel":"INFO","tarefa_id":123,"tipo":"teste_scheduler","status":"concluida","tentativa":1,"duracao_ms":42}
```

## Rotação de logs

Mesmo com logging somente de eventos reais, o arquivo deve possuir rotação operacional. Configuração recomendada para `/etc/logrotate.d/disparador-task-scheduler`:

```conf
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

`copytruncate` não é necessário porque o PHP CLI abre o arquivo apenas durante a execução curta do processo. A configuração deve ser aplicada manualmente na VPS; o código não altera `/etc/logrotate.d`.

## Troubleshooting

- `php processar_tarefas.php --verbose` para validar manualmente o ciclo.
- Se o cron não processar, conferir `crontab`, usuário, caminho do PHP, permissões em `storage` e elegibilidade das tarefas.
- Se não houver linhas no log, isso pode ser normal: ciclos sem tarefas não são registrados.
- Erros operacionais aparecem com nível `ERROR` e código sanitizado, sem stack trace ou payload.
- Logs antigos devem ser tratados pelo `logrotate` e não por exclusão feita pelo scheduler.

## Validação recomendada

Após aplicar a migration em ambiente controlado:

1. agendar uma tarefa futura e confirmar que não executa antes do horário;
2. agendar tarefa imediata e processá-la via `TaskExecutionService`;
3. confirmar que tarefa imediata sem gatilho permanece pendente para o cron;
4. testar retry e backoff;
5. testar falha permanente;
6. repetir uma chave idempotente;
7. simular lease expirado;
8. confirmar que dois workers não reservam a mesma tarefa;
9. validar que um ciclo vazio não gera stdout nem log;
10. validar `--verbose` e os níveis `INFO`, `WARNING` e `ERROR`.

## Limitações e roadmap

Não existe tela administrativa, daemon distribuído, heartbeat por tarefa ou histórico separado por tentativa. Nenhum módulo legado deve ser migrado em bloco. Consumidores futuros devem ser adicionados um a um, com PR e testes próprios.
