# Auditoria — Etapa 3.1: Fundação do daemon do worker

Base auditada: `016477e feat: implementar fundacao do daemon do worker`.

## Problemas críticos

Nenhum problema crítico permaneceu após a auditoria.

## Problemas altos

### Configurações divergentes e limites sem normalização completa

- **Arquivo:** `config/config.php` e `app/Services/WorkerDaemonRunner.php`
- **Método:** `defaultConfig()` / `normalizeConfig()`
- **Comportamento:** a versão auditada usava `WORKER_DAEMON_MEMORY_LIMIT_BYTES` e `WORKER_DAEMON_HEARTBEAT_ENABLED`, enquanto a especificação operacional pedia memória em MB e heartbeat por intervalo. Valores negativos também podiam gerar comportamento inválido.
- **Risco:** divergência de documentação, conversão incorreta de memória e loops acelerados por configuração inválida.
- **Correção realizada:** adotado `WORKER_DAEMON_MAX_MEMORY_MB`, `WORKER_DAEMON_HEARTBEAT_SECONDS` e `WORKER_DAEMON_SLEEP_GRANULARITY_SECONDS`, com normalização de limites e sleeps.

### SIGHUP com semântica ambígua

- **Arquivo:** `app/Services/WorkerDaemonRunner.php`
- **Método:** `handleSignal()`
- **Comportamento:** a flag anterior `reload_requested` permanecia ativa indefinidamente sem recarregar configuração.
- **Risco:** operador poderia interpretar que houve reload real.
- **Correção realizada:** `SIGHUP` agora emite `reload_unsupported` e mantém o processo, sem afirmar reload.

### Heartbeat sem intervalo e sem campos mínimos de auditoria

- **Arquivo:** `app/Services/WorkerDaemonRunner.php`
- **Método:** `emitHeartbeat()`
- **Comportamento:** heartbeat era emitido por ciclo, sem `level`, uptime, memória e duração do último ciclo.
- **Risco:** ruído em logs e baixa utilidade operacional.
- **Correção realizada:** heartbeat com intervalo por tempo, campos mínimos e eventos forçados para transições importantes.

## Problemas médios

### Detecção de busy por regex no JSON do resumo

- **Arquivo:** `app/Services/WorkerDaemonRunner.php`
- **Método:** `isBusy()`
- **Comportamento:** a detecção anterior usava regex genérica sobre JSON.
- **Risco:** falsos positivos e falsos negativos em resumos inesperados.
- **Correção realizada:** detecção explícita pelos campos reais retornados por `WorkerService`.

### Sleep interrompível com granularidade fixa

- **Arquivo:** `app/Services/WorkerDaemonRunner.php`
- **Método:** `interruptibleSleep()`
- **Comportamento:** a espera usava passos fixos de 1 segundo.
- **Risco:** falta de ajuste fino para shutdown mais responsivo ou menor overhead.
- **Correção realizada:** adicionada `WORKER_DAEMON_SLEEP_GRANULARITY_SECONDS`, com mínimo normalizado.

### Runtime baseado apenas em `microtime(true)`

- **Arquivo:** `app/Services/WorkerDaemonRunner.php`
- **Método:** `now()` / `limitReached()`
- **Comportamento:** a versão anterior dependia de `microtime(true)`.
- **Risco:** menor robustez em comparação com relógio monotônico.
- **Correção realizada:** uso de `hrtime(true)` quando disponível, com fallback para `microtime(true)`.

### Lock ocupado e arquivo legítimo

- **Arquivo:** `app/Services/WorkerDaemonRunner.php`
- **Método:** `acquireLock()` / `releaseLock()`
- **Comportamento:** era necessário garantir que lock ocupado não sobrescrevesse nem removesse arquivo legítimo.
- **Risco:** interferência indevida em outro daemon.
- **Correção realizada:** arquivo só é truncado após `flock` adquirido e só é removido quando o próprio processo adquiriu o lock.

## Problemas baixos

### `worker-daemon.php` retornava saída HTTP/CLI ambígua

- **Arquivo:** `worker-daemon.php`
- **Método:** bootstrap
- **Comportamento:** a rejeição de não CLI usava `exit(string)`.
- **Risco:** código de saída não explícito em execução indevida.
- **Correção realizada:** mensagem simples em STDERR e `exit(2)`.

### Testes com cobertura insuficiente

- **Arquivo:** `tests/WorkerDaemonRunnerTest.php`
- **Método:** script de teste standalone
- **Comportamento:** a versão anterior cobria alguns fluxos, mas não todos os cenários exigidos.
- **Risco:** falsos positivos por ausência de validações de lock cleanup, SIGHUP, heartbeat por intervalo, sanitização aninhada e limites desabilitados.
- **Correção realizada:** testes ampliados sem banco, sem Meta e sem sleeps longos.

## Recomendações sem implementação nesta etapa

- Documentar, no futuro arquivo systemd, `RestartPreventExitStatus=3` caso `Restart=always` ou `Restart=on-failure` seja usado.
- Manter a estratégia de reinício limpo pelo supervisor para falhas inesperadas de banco, sem implementar reconexão manual nesta etapa.
- Revisar a versão mínima de PHP do projeto em `composer.json` em uma etapa própria, se o projeto decidir formalizar plataforma PHP.
