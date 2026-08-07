# Task Scheduler central

## Objetivo e escopo

O `TaskSchedulerService` é uma infraestrutura genérica para registrar e executar tarefas imediatas ou futuras. Ele foi adicionado **em paralelo** às rotinas existentes: campanhas, disparos manuais, onboarding, notificações, trial, financeiro, NFS-e, WhatsApp institucional e workers legados não foram migrados nem alterados.

O Programa de Indicação é um consumidor futuro. Esta versão não contém regras comerciais, tipo ou handler de indicação.

```text
Módulo do sistema
      ↓
TaskSchedulerService
      ↓
tarefas_agendadas
      ↓
processar_tarefas.php
      ↓
TaskProcessor → TaskDispatcher → TaskRegistry
                                      ↓
                              Handler permitido
```

## Padrões do projeto considerados

- **Arquitetura:** MVC próprio, com Models em `app/Models`, Services em `app/Services`, Controllers e Views separados e autoload PSR-4 do Composer.
- **Bootstrap:** scripts CLI carregam `config/config.php` e `vendor/autoload.php`, após bloquear execução HTTP.
- **Configuração:** `config/env.php` lê ambiente e `config/config.php` define constantes e o timezone PHP.
- **Banco:** `Core\Database` fornece uma conexão PDO MySQL/MariaDB com exceções e sessão SQL em `-03:00`.
- **Datas:** a aplicação usa `APP_TIMEZONE` (`America/Sao_Paulo` por padrão); o banco é normalizado para `-03:00`. O scheduler converte `DateTimeInterface` para o timezone PHP antes de persistir `Y-m-d H:i:s`.
- **Models:** SQL em prepared statements e injeção opcional de PDO, o que permite SQLite em memória nos testes.
- **CLI/lock:** `processar_notificacoes_onboarding.php` e `worker.php` usam `flock()` não bloqueante em arquivo local.
- **Concorrência:** o worker usa também lock no banco; serviços que reservam recursos usam transações/updates condicionais.
- **Retry:** o worker de campanhas possui política centralizada e campos de próxima tentativa. O scheduler adota política própria porque seu ciclo e intervalos são genéricos, sem alterar a política legada.
- **Logs:** arquivos sob `storage/logs`, com mensagens sanitizadas e sem payload sensível.
- **Migrations:** SQL incremental, prefixos de três letras, `utf8mb4_unicode_ci`, índices explícitos e nenhuma execução automática.
- **Testes:** scripts PHP independentes, asserts simples, doubles e SQLite em memória; não há conexão com produção nos testes do scheduler.

## Componentes

| Componente | Responsabilidade |
|---|---|
| `TaskSchedulerService` | Validar e agendar, agendar agora, cancelar e reagendar. |
| `TarefaAgendada` | Persistência, idempotência, claim atômico, lease e transições condicionais. |
| `TaskRegistry` | Catálogo fechado `tipo → handler`; nunca lê nome de classe do banco. |
| `TaskDispatcher` | Decodificar JSON e entregar o payload ao handler permitido. |
| `TaskHandlerInterface` | Contrato mínimo `executar(array $payload): void`. |
| `TaskProcessor` | Reservar, executar fora da transação, concluir, aplicar retry ou falhar. |
| `TaskRetryPolicy` | Backoff central de 5, 15 e 60 minutos. |
| `processar_tarefas.php` | Entrada CLI finita, lock local, lote, log e resumo. |

## Tabela e estados

A migration incremental `20260807_create_tarefas_agendadas.sql` cria `tarefas_agendadas`. Ela deve ser revisada, aplicada manualmente em ambiente seguro e **não é executada pelo código**.

| Campo | Uso |
|---|---|
| `TAG_ID` | Identificador técnico. |
| `TAG_Tipo` | Chave presente no catálogo fechado. |
| `TAG_Status` | Estado do ciclo de vida. |
| `TAG_Prioridade` | Menor número é mais prioritário: 10 alta, 100 normal, 200 baixa. |
| `TAG_ExecutarEm` | Primeira data em que a tarefa pode executar. |
| `TAG_Payload` | JSON pequeno e validado. |
| `TAG_ChaveIdempotencia` | Chave lógica opcional e única. |
| `TAG_Tentativas` / `TAG_MaxTentativas` | Controle finito de execuções. |
| `TAG_ProximaTentativaEm` | Elegibilidade após backoff. |
| `TAG_ReservadaEm` / `TAG_WorkerId` | Lease e proprietário atual. |
| `TAG_IniciadaEm` / `TAG_FinalizadaEm` | Auditoria do ciclo. |
| `TAG_UltimoErro` | Mensagem curta e sanitizada. |
| `TAG_CriadaEm` / `TAG_AtualizadaEm` | Auditoria de persistência. |

Estados não sobrepostos:

- `pendente`: aguarda `TAG_ExecutarEm` ou `TAG_ProximaTentativaEm`;
- `processando`: reservada por um worker;
- `concluida`: handler terminou com sucesso;
- `falha`: falha permanente, exceção não classificada ou tentativas esgotadas;
- `cancelada`: cancelada antes da reserva.

Retry volta a `pendente` com `TAG_ProximaTentativaEm`; não há estado redundante `aguardando_retry`.

## Agendamento e idempotência

```php
$scheduler = new \Services\TaskSchedulerService();
$resultado = $scheduler->agendar(
    'teste_scheduler',
    ['resultado' => 'sucesso'],
    new \DateTimeImmutable('+10 minutes'),
    'teste_scheduler:execucao:123',
    100,
    3
);
```

`$resultado['criada']` informa se houve inserção. Quando a chave já existe, o service retorna o ID existente. A garantia real é o índice único do banco, portanto duas requisições concorrentes não criam duplicidade lógica. Chaves nulas permitem múltiplas tarefas.

## Payload e segurança

Permitido: JSON com escalares, listas, pequenos mapas e referências técnicas mínimas, por exemplo `['cliente_id'=>123]`.

Não armazenar:

- tokens, senhas, credenciais ou cabeçalhos de autorização;
- telefone completo, e-mail ou dados pessoais sem necessidade;
- payload externo bruto ou snapshots extensos;
- SQL, nomes de classes, callbacks ou funções;
- objetos serializados, recursos PHP ou código executável.

O service limita o JSON a 16 KB e profundidade 6, recusa chaves sensíveis/executáveis e usa `json_encode`, nunca `serialize`. Não existe `eval`, desserialização, shell command ou SQL vindo do payload.

## Catálogo e handlers

O catálogo inicial contém apenas `teste_scheduler`, destinado a testes internos e sem rota pública. `TAG_Tipo` é somente uma chave: a classe é escolhida pelo `TaskRegistry` definido em código e precisa implementar `TaskHandlerInterface`.

Handlers executam apenas a regra específica. Eles não recebem o repositório, não alteram a fila e não calculam retry. Resultados são comunicados por:

- retorno normal: sucesso;
- `TaskRetryException`: falha temporária;
- `TaskPermanentFailureException`: falha permanente;
- outra exceção: falha segura e definitiva nesta primeira versão.

## Reserva atômica, concorrência e lease

O claim executa:

1. encerra leases expirados que já esgotaram tentativas;
2. inicia transação curta;
3. seleciona uma tarefa elegível com `FOR UPDATE` no MariaDB;
4. faz update condicional para `processando`, incrementa tentativa e grava worker/lease;
5. confirma a transação;
6. somente então executa o handler.

A execução do handler nunca ocorre dentro da transação. O `flock` evita sobreposição do mesmo cron na máquina, mas a reserva no banco permanece a garantia entre processos/hosts.

Uma tarefa em `processando` com reserva anterior ao lease configurável (15 minutos por padrão) volta a ser elegível se ainda tiver tentativas. A recuperação incrementa a tentativa e registra `lease_expirado_recuperado`. Se o limite já foi atingido, termina em `falha` com `lease_expirado_max_tentativas`, evitando recuperação infinita.

Ordem de seleção:

1. `TAG_ExecutarEm` crescente;
2. `TAG_Prioridade` crescente;
3. `TAG_ID` crescente.

## Retry e backoff

| Tentativa que falhou | Próxima execução |
|---:|---:|
| 1 | +5 minutos |
| 2 | +15 minutos |
| 3 e seguintes | +60 minutos |

O handler não escolhe o intervalo. Se `TAG_Tentativas >= TAG_MaxTentativas`, uma falha transitória termina como `falha`.

## CLI, lock e cron

Execução manual:

```bash
php processar_tarefas.php
```

O comando aceita somente CLI, obtém `flock()` não bloqueante, processa no máximo `TASK_SCHEDULER_BATCH_SIZE`, imprime contadores e encerra. Não é daemon e não imprime payload.

Cron recomendado (não instalado por esta entrega):

```cron
* * * * * cd /opt/disparador-app && php processar_tarefas.php
```

Uma nova execução ocorre no minuto seguinte. Ajuste caminho, usuário e permissões conforme o deploy. Mesmo com cron em múltiplos hosts, o claim no banco impede execução simultânea da mesma tarefa.

## Logs

O log JSONL contém somente data, ID, tipo, status, número da tentativa, duração em milissegundos e código resumido. Payload, mensagens completas, tokens, telefones e dados pessoais não são registrados. O erro sanitizado fica limitado a 500 caracteres na tabela.

## Como adicionar um novo tipo

### 1. Criar o handler

```php
class LiberarCreditoIndicacaoHandler implements TaskHandlerInterface
{
    public function executar(array $payload): void
    {
        // Implementação futura, idempotente e validada.
    }
}
```

### 2. Registrar explicitamente

```php
'liberar_credito_indicacao' => LiberarCreditoIndicacaoHandler::class
```

### 3. Agendar

```php
$scheduler->agendar(
    'liberar_credito_indicacao',
    ['indicacao_id' => $indicacaoId],
    $data,
    'liberar_credito_indicacao:' . $indicacaoId
);
```

Esse exemplo é apenas roteiro. O tipo e o handler de indicação **não existem nesta versão**. Antes de adicioná-los, criar validação de payload, testes de idempotência e definir quais exceções são temporárias/permanentes.

## Validação manual em ambiente seguro

Depois de aplicar a migration fora de produção:

1. agendar `teste_scheduler` para alguns minutos no futuro;
2. executar o CLI antes e confirmar zero processadas;
3. executar após o horário e confirmar `concluida`;
4. criar payload de teste com `resultado=retry` e confirmar próxima tentativa/backoff;
5. criar payload com `resultado=falha` e confirmar falha permanente;
6. repetir uma chave e confirmar que o mesmo ID é retornado.

O handler de teste não possui endpoint ou botão. Não executar esse roteiro em produção.

## Troubleshooting

- **Tabela não encontrada:** aplicar a migration incremental pelo processo de deploy; o CLI não a aplica.
- **Zero tarefas:** conferir estado, `TAG_ExecutarEm`, `TAG_ProximaTentativaEm` e timezone.
- **Lock ocupado:** verificar se há execução legítima anterior; não remover lock de processo vivo.
- **Processando há muito tempo:** conferir lease, tentativas e log mínimo; o próximo ciclo recupera quando elegível.
- **Duplicidade:** usar chave lógica determinística e confirmar o índice único.
- **Falha permanente:** inspecionar código resumido e erro sanitizado; nunca editar payload manualmente.

## Administração futura, limitações e roadmap

Não existe tela administrativa, daemon distribuído, histórico separado por tentativa, heartbeat ou edição manual. Uma futura tela somente de leitura poderá listar estados, retries, duração, tipo e próxima tentativa, sem CRUD de payload.

Após homologação, módulos poderão migrar individualmente, com PR e testes próprios. Possíveis consumidores: Programa de Indicação, notificações, onboarding, trial, financeiro, NFS-e, relatórios e manutenção. Nenhum legado deve ser migrado em bloco.
