# Worker Contínuo — Etapa 3 — Arquitetura para daemon em produção

## 0. Escopo, premissas e estado atual

Este documento projeta a Etapa 3 do Worker Contínuo do Disparador.net. A etapa é **somente arquitetural**: não propõe troca de stack, não implementa loop infinito agora, não cria migrations e não altera interfaces públicas dos services existentes.

Premissas obrigatórias consideradas:

- aplicação PHP MVC própria;
- execução em Hostinger VPS;
- MariaDB como banco transacional;
- systemd como supervisor do processo;
- Meta Cloud API como provedor externo;
- Worker atual executado por `php worker.php` em ciclo único;
- Etapa 2 já contém `WorkerService`, `CampanhaQueueService`, `DisparoManualQueueService`, `WorkerRetryPolicyService` e `WorkerOperationalValidatorService`;
- já existem reserva atômica, retry/backoff persistente, recuperação de travados, `GET_LOCK()`, `worker_id`, validação operacional e JSON output.

Objetivo da Etapa 3: transformar o modelo atual de execução única em um serviço contínuo e simples de operar, mantendo a compatibilidade com o ciclo existente.

Arquivos analisados:

- `worker.php`;
- `app/Services/WorkerService.php`;
- `app/Services/CampanhaQueueService.php`;
- `app/Services/DisparoManualQueueService.php`;
- `app/Services/WorkerRetryPolicyService.php`;
- `app/Services/WorkerOperationalValidatorService.php`;
- `config/config.php`;
- `docs/WORKER_LEVANTAMENTO_TECNICO.md`;
- `docs/WORKER_ETAPA_2_RETRY_BACKOFF.md`;
- `docs/WORKER_AUDITORIA_ETAPA_1.md`.

---

## 1. Arquitetura geral recomendada

### 1.1 Visão de alto nível

A Etapa 3 deve adicionar uma camada fina de execução contínua ao redor do ciclo já existente em `WorkerService::executarCiclo()`. O desenho recomendado é **não transformar `WorkerService` em daemon**. Ele deve continuar sendo o executor de um ciclo. O daemon deve apenas decidir quando chamar o ciclo, quando dormir, quando parar e quando reiniciar.

```text
systemd
  |
  | ExecStart=/usr/bin/php worker-daemon.php
  v
worker-daemon.php
  |
  | bootstrap/config/autoload
  | sinais Unix
  | política de loop
  | política de idle
  | limites de reinício
  v
WorkerDaemonRunner            (novo na Etapa 3)
  |
  | chama repetidamente
  v
WorkerService::executarCiclo() (existente)
  |
  +--> GET_LOCK() compartilhado
  +--> recuperar travados
  +--> DisparoManualQueueService
  +--> CampanhaQueueService
  +--> resumo JSON/array
```

### 1.2 Responsabilidades

#### `worker.php` atual

Deve permanecer compatível e continuar executando apenas um ciclo. Ele serve para:

- execução manual;
- smoke test controlado;
- fallback operacional;
- compatibilidade com cron temporário, se necessário.

#### Novo entrypoint recomendado: `worker-daemon.php`

Responsável por:

- carregar `config/config.php` e `vendor/autoload.php`;
- registrar handlers de sinais;
- criar o runner contínuo;
- iniciar loop principal;
- imprimir/logar sumários por ciclo;
- devolver exit code adequado ao systemd.

#### Novo service recomendado: `WorkerDaemonRunner`

Responsável por:

- manter loop principal;
- decidir sleep/idle entre ciclos;
- respeitar shutdown gracioso;
- aplicar limites de vida útil do processo;
- controlar contadores de ciclos;
- medir memória/duração;
- delegar trabalho a `WorkerService::executarCiclo()`;
- nunca chamar Meta diretamente;
- nunca manipular `fila_envio` diretamente.

#### Services existentes

Devem permanecer com responsabilidades atuais:

- `WorkerService`: executa um ciclo transacional/operacional;
- `CampanhaQueueService`: processa campanhas e fila de campanhas;
- `DisparoManualQueueService`: processa lotes manuais;
- `WorkerRetryPolicyService`: calcula retry/backoff e classifica falhas;
- `WorkerOperationalValidatorService`: valida cliente/plano/Meta/número.

### 1.3 Divisão recomendada

```text
app/Services/
  WorkerService.php                 # ciclo único existente
  WorkerDaemonRunner.php            # Etapa 3: loop e sinais
  WorkerDaemonSleepPolicy.php       # opcional se o método crescer
  WorkerDaemonMetricsService.php    # opcional, só se métricas forem persistidas
```

Recomendação de simplicidade: começar com apenas `WorkerDaemonRunner`. Criar `WorkerDaemonSleepPolicy` ou `WorkerDaemonMetricsService` somente se a classe passar a acumular regras difíceis de testar.

---

## 2. Ciclo de vida do daemon

### 2.1 Diagrama

```text
[systemd start]
      |
      v
[Inicialização PHP]
      |
      v
[Bootstrap do projeto]
      |
      v
[Carregar configurações]
      |
      v
[Registrar sinais]
      |
      v
[Validar ambiente]
      |
      v
[Loop principal]
      |
      +--> [Executar WorkerService::executarCiclo()]
      |          |
      |          +--> GET_LOCK()
      |          +--> recuperar travados
      |          +--> processar manual
      |          +--> processar campanhas
      |          +--> RELEASE_LOCK()
      |
      +--> [Calcular sleep]
      |
      +--> [Idle interrompível]
      |
      +--> [Checar limites]
      |
      v
[Shutdown gracioso]
      |
      v
[Exit code para systemd]
```

### 2.2 Inicialização

A inicialização deve ser pequena e previsível:

1. confirmar `PHP_SAPI === 'cli'`;
2. carregar `config/config.php`;
3. carregar Composer autoload e autoload legado se necessário;
4. configurar timezone já definido em `config/config.php`;
5. configurar `error_log` para arquivo ou stderr;
6. inicializar logger simples;
7. criar `WorkerDaemonRunner`.

### 2.3 Bootstrap

O bootstrap do daemon deve reutilizar o mesmo padrão do `worker.php` atual para evitar divergência. A diferença é que o daemon não deve imprimir JSON arbitrário a cada momento sem estrutura; cada ciclo deve gerar uma linha JSON ou log estruturado.

### 2.4 Carregamento de config

Configurações recomendadas para Etapa 3:

```php
WORKER_DAEMON_ENABLED=true
WORKER_IDLE_SLEEP_SECONDS=5
WORKER_IDLE_MAX_SLEEP_SECONDS=60
WORKER_BUSY_SLEEP_SECONDS=1
WORKER_LOCK_BUSY_SLEEP_SECONDS=10
WORKER_ERROR_SLEEP_SECONDS=10
WORKER_ERROR_MAX_SLEEP_SECONDS=60
WORKER_MAX_RUNTIME_SECONDS=3600
WORKER_MAX_CYCLES=1000
WORKER_MAX_MEMORY_MB=256
WORKER_SHUTDOWN_TIMEOUT_SECONDS=30
```

Os números acima são valores iniciais sugeridos de configuração, não valores hardcoded. A implementação futura deve sempre ler constantes/env vars com defaults seguros e deve permitir ajuste operacional sem editar o algoritmo do loop.

### 2.5 Aquisição de lock

O lock compartilhado deve continuar dentro de `WorkerService::executarCiclo()`, pois é ele que protege a seção crítica de processamento. O daemon não deve manter `GET_LOCK()` durante o tempo ocioso. Assim:

- cada ciclo tenta adquirir o lock;
- se não adquirir, o ciclo retorna rapidamente;
- o daemon dorme e tenta novamente depois;
- o lock é liberado ao final do ciclo.

### 2.6 Loop principal

O loop deve:

1. checar se recebeu sinal de parada;
2. executar um ciclo;
3. registrar resumo e métricas;
4. decidir sleep;
5. dormir em pequenos intervalos interrompíveis;
6. verificar limites de reinício;
7. encerrar com código apropriado.

### 2.7 Idle

O idle deve ser interrompível: em vez de `sleep(60)` único, usar pequenos sleeps de 1 segundo até atingir o alvo. Isso permite responder a `SIGTERM` rapidamente.

### 2.8 Shutdown

O shutdown deve iniciar quando:

- chegar `SIGTERM` do systemd;
- chegar `SIGINT` manual;
- chegar `SIGQUIT`;
- limite de runtime/ciclos/memória for atingido;
- erro fatal controlado exigir reinício.

---

## 3. Estratégia de loop

### 3.1 Alternativas consideradas

#### Alternativa A — `while (true)` simples

Exemplo conceitual:

```php
while(true){
    $worker->executarCiclo();
    sleep(5);
}
```

Vantagens:

- muito simples;
- fácil de entender;
- pouca infraestrutura.

Desvantagens:

- difícil encerrar com elegância se o sleep for longo;
- tende a ignorar memória, ciclo máximo e sinais;
- pouca observabilidade;
- risco de loop agressivo se não houver trabalho.

Quando seria melhor: ambiente pequeno, temporário, sem systemd, com volume muito baixo e operador técnico acompanhando manualmente.

#### Alternativa B — scheduler interno

O daemon teria uma agenda interna por tarefa:

```text
recuperar travados a cada 60s
processar manuais a cada 2s
processar campanhas a cada 5s
emitir métricas a cada 30s
```

Vantagens:

- controle fino por tarefa;
- evita executar tarefas pesadas sempre;
- flexível para tarefas futuras.

Desvantagens:

- mais complexo;
- duplica decisões que hoje estão em `WorkerService`;
- mais difícil para um único mantenedor;
- maior risco de bugs de calendário/tempo.

Quando seria melhor: se houver muitas tarefas independentes com frequências diferentes e alto volume operacional.

#### Alternativa C — loop baseado em backoff/idle adaptativo

O daemon chama o ciclo único e ajusta o sono conforme o resumo:

- se houve trabalho, dorme pouco;
- se não houve trabalho, aumenta o idle até um teto;
- se houve erro/lock ocupado, usa sleep específico;
- qualquer sinal interrompe o idle.

Vantagens:

- simples;
- reduz CPU quando não há fila;
- mantém baixa latência quando há trabalho;
- reaproveita `WorkerService` sem mudar interfaces;
- combina com systemd.

Desvantagens:

- depende de resumo confiável do ciclo;
- pode atrasar alguns segundos em períodos ociosos;
- não diferencia todas as tarefas internamente.

Quando seria melhor: cenário atual do projeto, com um único daemon, MariaDB e filas simples.

#### Alternativa D — loop híbrido com scheduler mínimo

Combina idle adaptativo com alguns intervalos internos, por exemplo recuperação de travados só a cada N ciclos.

Vantagens:

- otimiza tarefas custosas;
- ainda é mais simples que scheduler completo.

Desvantagens:

- adiciona estado interno;
- exige contadores e maior superfície de teste.

Quando seria melhor: se a recuperação de travados ou métricas passarem a ter custo significativo.

### 3.2 Escolha recomendada

Recomenda-se **loop adaptativo baseado no resultado do ciclo**, sem scheduler interno completo na primeira versão da Etapa 3.

Motivo técnico:

- menor mudança sobre arquitetura existente;
- preserva `WorkerService::executarCiclo()` como unidade testável;
- reduz CPU em períodos sem trabalho;
- mantém latência baixa quando há filas;
- é simples para manutenção por um único desenvolvedor.

Impactos:

- desempenho: bom equilíbrio entre CPU e latência;
- manutenção: baixa complexidade;
- escalabilidade: suficiente para um Worker único e base para particionamento futuro;
- operação: fácil de observar via logs e systemd.

---

## 4. Sleep inteligente

### 4.1 Alternativas

| Estratégia | CPU | Latência | Simplicidade | Observação |
|---|---:|---:|---:|---|
| sleep fixo curto | maior | baixa | alta | desperdiça ciclos quando não há fila |
| sleep fixo longo | baixa | alta | alta | demora a reagir a novas campanhas |
| sleep exponencial | baixa em idle | variável | média | bom para ociosidade, mas pode atrasar pico repentino |
| sleep baseado em trabalho | equilibrada | baixa quando há carga | média | melhor encaixe atual |
| scheduler por tarefa | ajustável | ajustável | baixa | mais complexo que necessário |

### 4.2 Recomendação

Usar sleep adaptativo por existência de trabalho:

```text
se ciclo enviou/reservou/recuperou itens:
    sleep = WORKER_BUSY_SLEEP_SECONDS
se ciclo não fez trabalho:
    sleep = min(sleep_atual * 2, WORKER_IDLE_MAX_SLEEP_SECONDS)
se ciclo teve lock ocupado:
    sleep = WORKER_LOCK_BUSY_SLEEP_SECONDS
se ciclo teve exceção:
    sleep = min(erro_count * base, max_erro_sleep)
```

Valores iniciais devem vir de configuração, por exemplo:

- ocupado/com trabalho: `WORKER_BUSY_SLEEP_SECONDS`;
- idle inicial: `WORKER_IDLE_SLEEP_SECONDS`;
- idle máximo: `WORKER_IDLE_MAX_SLEEP_SECONDS`;
- lock ocupado: `WORKER_LOCK_BUSY_SLEEP_SECONDS`;
- erro: `WORKER_ERROR_SLEEP_SECONDS` até `WORKER_ERROR_MAX_SLEEP_SECONDS`.

### 4.3 Idle interrompível

Implementação futura recomendada:

```php
private function dormirInterrompivel(int $segundos): void
{
    for($i = 0; $i < $segundos; $i++){
        if($this->shutdownSolicitado){
            return;
        }
        sleep(1);
        $this->despacharSinais();
    }
}
```

Se `pcntl_signal_dispatch()` estiver disponível, deve ser chamado a cada segundo. Se `pcntl` não estiver disponível, o daemon ainda funciona, mas o encerramento dependerá mais do systemd e do término do ciclo atual.

---

## 5. Encerramento gracioso

### 5.1 Sinais

| Sinal | Origem comum | Comportamento recomendado |
|---|---|---|
| `SIGTERM` | `systemctl stop`/deploy | parar após ciclo atual e liberar recursos |
| `SIGINT` | Ctrl+C | parar após ciclo atual ou idle atual |
| `SIGQUIT` | encerramento manual mais forte | parar após ciclo atual, registrando motivo |
| `SIGHUP` | reload/config | não recarregar config no início; tratar como pedido de restart gracioso |

### 5.2 Regra principal

O daemon **não deve interromper uma chamada Meta no meio**. Ao receber sinal durante envio:

1. marca `shutdownSolicitado = true`;
2. deixa `WorkerService::executarCiclo()` terminar;
3. não inicia novo ciclo;
4. libera locks;
5. encerra com código 0 se foi parada esperada.

### 5.3 Liberação de recursos

Recursos a liberar/fechar:

- `GET_LOCK()` — já liberado por `WorkerService` ao final do ciclo;
- `flock()` local — se o daemon mantiver camada local no entrypoint;
- arquivos de log — se houver handles dedicados;
- conexões PDO — normalmente encerradas pelo fim do processo;
- caches de `MetaService` — liberados pelo fim do processo ou recriação por ciclo.

### 5.4 Timeout de parada

No systemd, configurar `TimeoutStopSec=45` inicialmente. O daemon deve tentar parar em até 30s; systemd dá margem adicional. Se uma chamada HTTP à Meta ficar presa, o timeout de cURL em `MetaService` deve ser menor que `TimeoutStopSec`.

---

## 6. Exceções fatais

A política recomendada para exceções fatais é encerrar o processo e deixar o systemd reiniciar. O daemon não deve tentar continuar indefinidamente após falhas inesperadas fora do controle normal do ciclo, pois isso pode manter estado interno corrompido, conexão instável ou caches inconsistentes.

Fluxo esperado:

```text
Exception fatal/inesperada
        |
        v
Registrar log critical sanitizado
        |
        v
Solicitar parada do loop
        |
        v
Liberar recursos locais
        |
        v
Garantir RELEASE_LOCK/fechamento da sessão
        |
        v
Encerrar processo com exit code de falha
        |
        v
systemd reinicia conforme Restart=always/on-failure
```

Diretrizes:

- exceções internas de um item/campanha continuam sendo tratadas pelos services existentes quando forem recuperáveis;
- exceções fatais no runner, bootstrap, conexão principal ou logger devem encerrar o daemon;
- antes de encerrar, registrar `worker_id`, `cycle`, etapa, mensagem sanitizada e memória;
- não iniciar novo ciclo depois de uma exceção fatal;
- não manter `GET_LOCK()` após falha fatal;
- preferir processo novo via systemd a tentar reconstruir manualmente todo o estado em memória.

Essa decisão é mais simples e segura para PHP long-running: falhas recuperáveis permanecem dentro do ciclo; falhas inesperadas reiniciam o processo com ambiente limpo.

---

## 7. Reinicialização planejada

Processos PHP long-running podem acumular memória por caches, bibliotecas ou leaks não óbvios. A Etapa 3 deve aceitar reinício periódico como mecanismo simples de higiene operacional.

### 7.1 Critérios recomendados

Encerrar voluntariamente com exit code 0 quando qualquer condição ocorrer:

- runtime máximo: 1 hora;
- ciclos máximos: 1000;
- memória acima de 256 MB ou 70% de `memory_limit`, o que for menor;
- `SIGHUP` recebido;
- configuração crítica inválida detectada no início.

### 7.2 systemd como responsável pelo restart

O daemon não deve reiniciar a si mesmo com `exec()` na primeira versão. Deve sair de forma limpa e deixar o systemd reiniciar com `Restart=always` ou `Restart=on-failure`, conforme decisão operacional.

Recomendação: `Restart=always`, porque saídas voluntárias por limite de ciclos/runtime também devem reiniciar o serviço.

---

## 8. Logs

### 8.1 Estrutura recomendada

Usar JSON Lines, uma linha por evento:

```json
{"ts":"2026-07-15T12:00:00-03:00","level":"info","event":"worker.cycle.finished","worker_id":"srv-123-a1b2c3","cycle":42,"duration_ms":381,"manual":{"reservados":0,"enviados":0},"campanhas":{"reservados":2,"enviados":2},"idle_seconds":1}
```

Campos base:

- `ts`;
- `level` (`debug`, `info`, `warning`, `error`, `critical`);
- `event`;
- `worker_id`;
- `pid`;
- `cycle`;
- `duration_ms`;
- `memory_mb`;
- `lock_status`;
- `summary` sanitizado.

Heartbeat periódico recomendado:

```json
{"ts":"2026-07-15T12:00:30-03:00","level":"info","event":"worker.heartbeat","worker_id":"srv-123-a1b2c3","cycle":42,"uptime_seconds":1800,"memory_mb":96,"last_cycle_duration_ms":381}
```

O heartbeat deve ser emitido por tempo configurável, por exemplo `WORKER_HEARTBEAT_SECONDS`, sem depender de valor hardcoded.

### 8.2 Níveis

- `debug`: decisões de sleep, contadores detalhados; desabilitado em produção por padrão.
- `info`: start, stop, ciclo concluído, lock adquirido.
- `warning`: lock ocupado, bloqueio temporário recorrente, idle máximo atingido.
- `error`: exceção recuperável, falha de banco, falha de Meta recorrente.
- `critical`: falha pós-envio sem persistência, perda inesperada de lock, erro fatal.

### 8.3 Sanitização

Nunca logar:

- `MTA_Token`;
- Authorization/Bearer;
- senha/API key;
- payload completo da Meta com dados sensíveis;
- corpo completo de mensagem se não for necessário.

Manter a sanitização textual atual e evoluir para sanitização por chaves quando possível.

### 8.4 Rotação

Duas opções compatíveis:

1. logs para stdout/stderr e `journalctl`;
2. logs em arquivo com `logrotate`.

Recomendação: para systemd, escrever logs operacionais em stdout/stderr e deixar journald coletar. Se o projeto já usa `storage/logs/worker.log`, manter também arquivo por compatibilidade, mas configurar logrotate.

Exemplo de `logrotate`:

```text
/var/www/disparador/storage/logs/worker*.log {
    daily
    rotate 14
    compress
    missingok
    notifempty
    copytruncate
}
```

---

## 9. systemd

### 9.1 Unidade recomendada

```ini
[Unit]
Description=Disparador.net Worker Continuo
After=network-online.target mariadb.service
Wants=network-online.target

[Service]
Type=simple
WorkingDirectory=/var/www/disparador
ExecStart=/usr/bin/php /var/www/disparador/worker-daemon.php
User=www-data
Group=www-data
Environment=APP_ENV=production
Restart=always
RestartSec=5
TimeoutStopSec=45
KillSignal=SIGTERM
RestartPreventExitStatus=64
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
```

### 9.2 Justificativa das opções

- `Type=simple`: PHP roda em foreground; não há fork.
- `WorkingDirectory`: garante paths relativos corretos.
- `ExecStart`: aponta para novo entrypoint daemon, mantendo `worker.php` one-shot.
- `User`/`Group`: usuário sem privilégios, com acesso a projeto e logs.
- `Environment`: fixa ambiente sem depender de shell interativo.
- `Restart=always`: reinicia após falha e após encerramento voluntário por limite de runtime.
- `RestartSec=5`: evita loop de restart agressivo.
- `TimeoutStopSec=45`: tempo para terminar ciclo/chamada atual.
- `KillSignal=SIGTERM`: sinal padrão para shutdown gracioso.
- `RestartPreventExitStatus=64`: reservado para erro de configuração permanente, se implementado.
- `WantedBy=multi-user.target`: inicia no boot normal.

### 9.3 Alternativas descartadas

- `cron * * * * php worker.php`: simples, mas perde estado contínuo e dificulta shutdown/observabilidade.
- Supervisor: válido, mas systemd já está disponível na VPS e reduz dependências.
- container permanente: adiciona complexidade desnecessária para a stack atual.

---

## 10. Observabilidade

### 10.1 Métricas mínimas

Por ciclo:

- duração total;
- tempo de idle escolhido;
- memória atual e pico;
- lock adquirido/ocupado;
- campanhas encontradas/processadas;
- itens de campanha reservados/enviados/erros/bloqueados;
- lotes manuais processados/reservados/enviados/erros/bloqueados;
- itens recuperados;
- exceções;
- tempo médio por envio, se mensurável sem invadir `MetaService`.

Por processo:

- ciclos desde start;
- tempo vivo;
- reinícios pelo systemd;
- último motivo de shutdown;
- maior memória observada.

### 10.2 Formas simples de exposição

Etapa 3 inicial:

- logs JSON;
- `journalctl -u disparador-worker -f`;
- comando de status via `systemctl status`;
- heartbeat periódico em logs estruturados.

Não recomendar `worker-state.json` nesta etapa. A observabilidade inicial deve ficar em logs estruturados, `journalctl`, `systemctl status` e métricas futuras. Se persistência de estado for necessária, registrar como possibilidade de etapa posterior, sem implementação agora.

Não recomendar Prometheus, Redis ou filas externas nesta etapa por simplicidade e restrições do projeto.

---

## 11. Lock

### 11.1 GET_LOCK()

Vantagens:

- lock compartilhado entre processos no mesmo MariaDB;
- não depende do path de release;
- evita dois daemons processarem filas simultaneamente;
- já implementado no ciclo atual.

Desvantagens:

- depende da conexão MariaDB permanecer viva;
- se a conexão cair, o lock é liberado;
- exige cuidado para adquirir e liberar na mesma sessão.

### 11.2 flock()

Vantagens:

- simples;
- protege processos na mesma cópia do projeto;
- funciona mesmo se MariaDB estiver indisponível.

Desvantagens:

- não protege múltiplos releases/diretórios/servidores;
- lock stale precisa de cuidado;
- não substitui lock compartilhado.

### 11.3 Uso conjunto recomendado

Manter ambos:

```text
flock local no entrypoint daemon
  +
GET_LOCK por ciclo no WorkerService
```

O `flock` evita dois processos do mesmo diretório. O `GET_LOCK()` protege a seção crítica global. O daemon não deve segurar `GET_LOCK()` durante o idle.

### 11.4 Perda do lock

Se MariaDB cair durante o ciclo, a operação atual deve gerar exceção e o ciclo deve terminar com erro. Na próxima iteração, a recuperação de travados e retry/backoff devem tratar itens deixados em estado intermediário.

---

## 12. Escalabilidade futura

A Etapa 3 deve operar com **um Worker daemon em produção**. Mesmo assim, o desenho deve não bloquear evoluções futuras.

### 12.1 Múltiplos workers

Não recomendado inicialmente. Para múltiplos workers seriam necessários:

- particionamento por cliente, `MTA_ID` ou campanha;
- locks por partição;
- limite de envio por conta Meta;
- maior idempotência;
- observabilidade por worker.

### 12.2 Filas por cliente

Vantagens:

- isola cliente grande;
- permite pausar cliente problemático;
- facilita limites financeiros.

Desvantagens:

- aumenta complexidade;
- precisa scheduler/particionamento.

Melhor para etapa futura, após daemon único estabilizado.

### 12.3 Filas por conta Meta

Vantagens:

- combina melhor com limites da Cloud API;
- reduz risco de uma conta impactar outra.

Desvantagens:

- exige rate limit por `MTA_ID`/WABA;
- exige seleção por conta nos services.

Também deve ficar para etapa posterior.

---

## 13. Compatibilidade com services atuais

A Etapa 3 deve manter as interfaces públicas:

- `WorkerService::__construct(array $opcoes = [])`;
- `WorkerService::executarCiclo(): array`;
- `CampanhaQueueService::processar(int $limitePorExecucao, string $workerId)`;
- `DisparoManualQueueService::processarPendentes(int $limite, string $origem, string $workerId)`;
- `WorkerRetryPolicyService` sem alteração pública;
- `WorkerOperationalValidatorService::validarEnvio()`.

O daemon deve depender apenas de `WorkerService::executarCiclo()` e do resumo retornado. Se o resumo precisar de novos campos, adicionar de forma retrocompatível.

---

## 14. Plano de implementação da Etapa 3

### Entrega 1 — Documento e checklist operacional

- Finalizar este documento.
- Revisar comandos systemd e paths reais da VPS.
- Confirmar usuário Linux e diretório de deploy.

Baixo risco, reversível, sem runtime.

### Entrega 2 — Configurações do daemon

- Adicionar constantes com defaults seguros.
- Não alterar comportamento do `worker.php`.
- Testar leitura de config em CLI.

### Entrega 3 — `WorkerDaemonRunner`

- Criar runner com loop adaptativo.
- Usar `WorkerService::executarCiclo()` sem alterar services de fila.
- Incluir limites de runtime/ciclos/memória.
- Testar em modo simulado/mocks.

### Entrega 4 — Entry point `worker-daemon.php`

- Bootstrap CLI.
- Handler de sinais.
- Execução do runner.
- Exit codes documentados.

### Entrega 5 — Logs estruturados e heartbeat

- JSON Lines por ciclo.
- Heartbeat periódico em log, com `worker_id`, `cycle`, uptime e memória.
- Sanitização igual ou superior à atual.
- Não implementar `worker-state.json` nesta etapa; persistência de estado pode ser avaliada em etapa posterior.

### Entrega 6 — systemd em staging/VPS

- Criar arquivo `.service` documentado.
- Instalar manualmente em staging.
- Validar `start`, `stop`, `restart`, boot e logs.

### Entrega 7 — Testes operacionais sem envio real

- Rodar com modo teste ou base controlada.
- Simular fila vazia.
- Simular lock ocupado.
- Simular SIGTERM durante idle.
- Simular limite de ciclos.

### Entrega 8 — Rollout controlado

- Parar cron antigo se existir.
- Subir daemon único.
- Monitorar logs e consumo.
- Ter plano de rollback para `php worker.php` one-shot/cron.

---

## 15. Recuperação automática

### 15.1 Queda do PHP/Worker

systemd reinicia. Itens em `processando` sem `MessageId` são recuperados pela Etapa 2 após timeout. Itens com `MessageId` ficam para reconciliação.

### 15.2 Queda do MariaDB

O ciclo deve falhar, registrar erro e dormir com backoff de erro. systemd não precisa reiniciar imediatamente se a exceção for capturada; se o processo morrer, reinicia.

### 15.3 Queda da Meta

`WorkerRetryPolicyService` classifica falhas temporárias; itens voltam a `pendente` com próxima tentativa. O daemon deve continuar vivo, aumentando idle de erro se muitos ciclos falharem.

### 15.4 Reinício do servidor

systemd inicia no boot. O primeiro ciclo recupera itens travados após timeout e processa pendentes elegíveis.

### 15.5 Perda do GET_LOCK()

Se a conexão cair, o lock é perdido. A falha deve aparecer como exceção de banco. A mitigação é não manter transação aberta durante HTTP e confiar nas reservas por item/recuperação de timeout.

### 15.6 Exceções inesperadas

`WorkerService` já captura exceções por etapa. O daemon deve capturar exceções fora do ciclo, registrar `critical`, dormir curto ou sair para restart se repetirem muitas vezes.

---

## 16. Riscos e mitigações

| Risco | Tipo | Impacto | Mitigação |
|---|---|---|---|
| loop consome CPU sem fila | operacional | custo/instabilidade | sleep adaptativo com teto |
| daemon não para no deploy | deploy | deploy travado | sinais + TimeoutStopSec |
| chamada Meta longa bloqueia parada | externo | stop lento | timeout HTTP menor que TimeoutStopSec |
| memória cresce ao longo do tempo | técnico | OOM/restart abrupto | max runtime/ciclos/memória |
| dois daemons rodam juntos | concorrência | duplicidade | flock + GET_LOCK + reserva atômica |
| MariaDB cai no meio do ciclo | infraestrutura | itens processando | timeout/recovery/backoff |
| logs expõem tokens | segurança | vazamento | sanitização por chave/texto |
| logs crescem sem limite | operação | disco cheio | journalctl/logrotate |
| PR/deploy ativa daemon antes de migration | deploy | SQL errors | checklist pré-deploy |
| resumo do ciclo insuficiente para sleep | manutenção | idle ruim | adicionar campos retrocompatíveis |
| múltiplos workers sem particionamento | concorrência | rate limit/duplicidade | não habilitar na Etapa 3 |
| bugs latentes em services aparecem em daemon | técnico | falhas recorrentes | lint/testes antes de daemon, rollout staging |

---

## 17. Justificativa das decisões arquiteturais

### 17.1 Manter `WorkerService` como ciclo único

Alternativas:

- transformar `WorkerService` em daemon;
- criar `WorkerDaemonRunner` separado;
- manter apenas cron.

Escolha: criar runner separado.

Vantagens:

- preserva compatibilidade;
- facilita testes;
- evita misturar orquestração contínua com processamento de fila.

Desvantagens:

- adiciona uma classe nova.

Rejeição das alternativas:

- transformar `WorkerService` em daemon aumenta acoplamento;
- cron não atende objetivo de serviço contínuo.

Impacto:

- desempenho: neutro;
- manutenção: melhor;
- escalabilidade: abre caminho para runners especializados;
- operação: mantém `php worker.php` como fallback.

Outra alternativa seria melhor se o projeto fosse descartável ou se não houvesse necessidade de manter execução one-shot.

### 17.2 Usar systemd

Alternativas:

- systemd;
- cron;
- Supervisor;
- container.

Escolha: systemd.

Vantagens:

- já disponível na VPS;
- reinício automático;
- logs via journal;
- integração com boot/stop/deploy.

Desvantagens:

- exige configuração no servidor;
- precisa conhecimento operacional básico.

Rejeição:

- Supervisor adiciona dependência;
- container é overengineering;
- cron não é daemon real.

### 17.3 Sleep adaptativo

Alternativas:

- fixo curto;
- fixo longo;
- exponencial puro;
- adaptativo por trabalho.

Escolha: adaptativo por trabalho.

Vantagens:

- baixa CPU em idle;
- baixa latência com fila;
- fácil de explicar.

Desvantagens:

- precisa interpretar resumo do ciclo.

Outra alternativa seria melhor se houvesse SLA de latência subsegundo, o que não é o caso.

### 17.4 Um daemon único na Etapa 3

Alternativas:

- um daemon único;
- múltiplos daemons iguais;
- múltiplos daemons particionados.

Escolha: um daemon único.

Vantagens:

- menor risco;
- combina com GET_LOCK global;
- suficiente para validar operação contínua.

Desvantagens:

- throughput limitado;
- um cliente grande pode ocupar ciclos.

Outra alternativa será melhor quando houver volume suficiente para particionar por `MTA_ID` ou cliente.

### 17.5 Não introduzir Redis/RabbitMQ/Kafka

Alternativas:

- manter MariaDB;
- adicionar broker.

Escolha: manter MariaDB.

Vantagens:

- compatível com stack;
- menor custo operacional;
- já há schema de filas;
- menos componentes para um mantenedor.

Desvantagens:

- menos recursos avançados de fila;
- cuidado maior com locks e índices.

Broker seria melhor em arquitetura com múltiplos serviços, alto volume e equipe operacional maior.

---

## 18. Compatibilidade com o projeto

As recomendações respeitam:

- PHP CLI tradicional;
- MVC próprio;
- MariaDB/PDO;
- Hostinger VPS;
- systemd;
- Meta Cloud API;
- services existentes;
- execução one-shot atual.

Não é recomendado nesta etapa:

- trocar framework;
- trocar linguagem;
- trocar banco;
- Redis;
- RabbitMQ;
- Kafka;
- Swoole;
- RoadRunner;
- ReactPHP;
- bibliotecas assíncronas.

---

## 19. Simplicidade operacional

A arquitetura recomendada prioriza:

1. uma classe runner simples;
2. um entrypoint novo;
3. systemd padrão;
4. logs JSON Lines;
5. sleep adaptativo simples;
6. reinício periódico pelo próprio systemd;
7. zero dependências externas novas.

Essa abordagem é superior para o contexto atual porque reduz a quantidade de conceitos novos e mantém o processamento real nos services já testados em produção.

---

## 20. Checklist de implementação futura

Antes de implementar:

- rodar `php -l` nos services atuais;
- confirmar que `worker.php` one-shot continua funcionando em staging;
- confirmar migration da Etapa 2 aplicada;
- confirmar usuário Linux e permissões em `storage/`;
- confirmar path absoluto do PHP;
- confirmar timeout de HTTP da Meta;
- confirmar que não há cron concorrente ativo.

Antes de produção:

- testar `systemctl start`;
- testar `systemctl stop` durante idle;
- testar `systemctl stop` durante ciclo simulado;
- testar reboot da VPS;
- monitorar `journalctl`;
- validar que apenas um daemon está ativo;
- validar recuperação de `processando` após queda simulada.

---

## 21. Conclusão

A Etapa 3 deve ser implementada como uma camada contínua simples ao redor do ciclo já existente, não como reescrita do Worker. A decisão central é preservar `WorkerService::executarCiclo()` como unidade de trabalho e adicionar um `WorkerDaemonRunner` responsável por loop, sinais, idle, limites e integração com systemd.

A solução recomendada é simples, compatível com a stack atual, reversível, testável em partes e adequada para operação por um único mantenedor em VPS.
