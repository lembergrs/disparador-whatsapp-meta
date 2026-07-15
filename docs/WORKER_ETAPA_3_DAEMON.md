# Worker contínuo — Etapa 3.1: Fundação do daemon

Este documento registra a arquitetura vigente da fundação do daemon do worker.

## Escopo implementado

- `worker-daemon.php` é o entrypoint CLI do daemon contínuo.
- `worker.php` permanece como execução one-shot independente.
- `Services\WorkerDaemonRunner` executa ciclos sucessivos chamando `WorkerService::executarCiclo()`.
- O daemon não implementa systemd, deploy, logrotate, banco, migrations, webhook, retry de mensagens, múltiplos workers, `worker-state.json` nem monitoramento externo.

## Requisitos de ambiente

- PHP CLI compatível com `Throwable`, tipos escalares/retorno e `hrtime()` quando disponível.
- A extensão `pcntl` é obrigatória para o daemon. Se ausente, o daemon encerra explicitamente com código `2`.
- Execução por HTTP é rejeitada por `worker-daemon.php` com mensagem simples em STDERR e código `2`.

## Configurações

Todas as configurações operacionais do daemon devem vir de `config/config.php`/ambiente:

- `WORKER_DAEMON_BUSY_SLEEP_SECONDS`: sleep após ciclo com trabalho.
- `WORKER_DAEMON_IDLE_SLEEP_SECONDS`: sleep base para ciclo sem trabalho.
- `WORKER_DAEMON_MAX_SLEEP_SECONDS`: teto do backoff ocioso.
- `WORKER_DAEMON_SLEEP_GRANULARITY_SECONDS`: granularidade do sleep interrompível.
- `WORKER_DAEMON_MAX_RUNTIME_SECONDS`: limite de runtime; `0` desabilita.
- `WORKER_DAEMON_MAX_MEMORY_MB`: limite de memória em MB; `0` desabilita.
- `WORKER_DAEMON_MAX_CYCLES`: limite de ciclos; `0` desabilita.
- `WORKER_DAEMON_HEARTBEAT_SECONDS`: intervalo mínimo de heartbeat; `0` desabilita.
- `WORKER_DAEMON_HEARTBEAT_FILE`: destino JSON Lines do heartbeat, podendo ser arquivo ou stream PHP.
- `WORKER_DAEMON_LOCK_FILE`: arquivo de lock exclusivo do daemon.
- `WORKER_DAEMON_ID`: identificador estável opcional do processo daemon.
- `WORKER_DAEMON_LIMITE_CAMPANHAS`: limite repassado ao processamento de campanhas por ciclo.
- `WORKER_DAEMON_LIMITE_DISPARO_MANUAL`: limite repassado ao processamento manual por ciclo.

Valores negativos são normalizados para faixas seguras no runner. Limites com `0` permanecem desabilitados quando documentado.

## Locks

Há três camadas independentes:

1. **Lock do daemon**: `flock` não bloqueante em `WORKER_DAEMON_LOCK_FILE`, exclusivo para impedir dois daemons contínuos no mesmo host/caminho.
2. **Lock one-shot**: `worker.php` mantém seu próprio `storage/worker.lock` para evitar duas execuções one-shot simultâneas.
3. **Lock compartilhado MariaDB**: `WorkerService` usa `GET_LOCK(..., 0)` para impedir ciclos concorrentes no banco entre processos diferentes.

O lock do daemon não substitui o lock one-shot nem o lock MariaDB. O lock MariaDB continua protegendo a regra crítica entre daemon e one-shot.

## Busy, idle e backoff

Um ciclo é considerado ocupado somente se o lock compartilhado MariaDB foi adquirido e algum campo real do resumo indicar trabalho ou erro operacional tratado:

- `lotes_manuais`: `processados`, `reservados`, `enviados`, `erros`, `bloqueados`.
- `campanhas`: `campanhas_encontradas`, `processadas`, `reservados`, `enviados`, `erros_temporarios`, `erros_definitivos`, `bloqueados`, `excecoes`.
- `recuperados`: `manual`, `campanhas`, `total`.
- `excecoes`: lista não vazia.

Se `lock_compartilhado` não for `adquirido`, o ciclo não é tratado como ocupado para evitar busy loop.

Fórmula do backoff ocioso:

```text
proximo_sleep = min(WORKER_DAEMON_MAX_SLEEP_SECONDS, max(WORKER_DAEMON_IDLE_SLEEP_SECONDS, sleep_atual * 2))
```

Qualquer ciclo ocupado reseta o próximo sleep para `WORKER_DAEMON_BUSY_SLEEP_SECONDS`.

## Sinais

- `SIGTERM`, `SIGINT` e `SIGQUIT`: solicitam shutdown gracioso; o ciclo atual termina e nenhum novo ciclo é iniciado.
- `SIGHUP`: não recarrega configuração. A semântica escolhida é registrar o evento `reload_unsupported` e manter o processo rodando.

Handlers apenas alteram estado/registram heartbeat; nenhuma regra de negócio é executada dentro deles.

## Heartbeat e logs

O heartbeat é JSON Lines com `JSON_UNESCAPED_UNICODE`. Cada entrada inclui:

- `timestamp`;
- `level`;
- `event`;
- `daemon_id`;
- `cycle`;
- `pid`;
- `uptime_seconds`;
- `memory_usage_bytes`;
- `last_cycle_duration_seconds`;
- `data` sanitizado.

Dados sensíveis como tokens, authorization, bearer, senha, password, secret e payloads são mascarados ou omitidos.

## Códigos de saída

- `0`: shutdown normal por sinal ou limite.
- `1`: falha inesperada/fatal capturada pelo daemon.
- `2`: ambiente/configuração inválida, incluindo não CLI ou ausência de `pcntl`.
- `3`: daemon já em execução/lock ocupado.

Para systemd futuro, o código `3` deve ser documentado como condição operacional esperada e tratado com `RestartPreventExitStatus=3` se for usado `Restart=on-failure` ou `Restart=always`.

## Limitações conhecidas

`catch(Throwable)` cobre falhas lançadas durante criação do `WorkerService`, execução do ciclo, interpretação do resumo, heartbeat e cálculo de sleep. Não cobre parse errors antes do carregamento do runner, esgotamento severo de memória que impeça o runtime de executar handlers, nem falhas fatais externas ao processo.
