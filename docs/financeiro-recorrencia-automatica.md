# Recorrência financeira automática

## Responsabilidade

O Disparador é a fonte de verdade da assinatura, competência, vencimento e avanço do ciclo. O Asaas recebe pagamentos avulsos e devolve fatos externos; não existe assinatura recorrente criada no gateway.

## Scheduler diário

O cron de produção continua executando `processar_tarefas.php` a cada minuto. Sob o lock global do scheduler, `FinanceiroSchedulerBootstrapService` garante uma tarefa do tipo `financeiro_gerar_cobrancas_recorrentes` para o dia corrente.

A chave `financeiro:gerar_recorrentes:AAAA-MM-DD`, protegida pela unique key de `tarefas_agendadas`, permite várias chamadas ao bootstrap sem criar duas execuções diárias. O handler delega a `FinanceiroRecorrenciaService::gerarCobrancasRecorrentes()` e solicita o retry padrão do scheduler quando o lote reporta erro. Não há processamento financeiro no worker de mensagens.

## Antecedência e competência

`ASS_DataProximaCobranca` continua representando a competência e o vencimento contratual. `FINANCEIRO_DIAS_ANTECEDENCIA_COBRANCA`, com padrão 7, apenas antecipa a seleção:

```text
competência/vencimento: 17/09
elegível para geração:  10/09
COB_DataVencimento:     17/09
```

Depois da integração bem-sucedida, a próxima competência é calculada a partir da competência processada, preservando o calendário mensal, trimestral, semestral ou anual.

## Idempotência e retry

Cada competência usa a chave local formada por assinatura, vencimento e tipo, protegida pelo índice `uk_cobrancas_assinatura_competencia_tipo`. A integração mantém o lock por cobrança e sempre consulta `externalReference` antes de criar um pagamento no Asaas.

Uma cobrança local pendente sem `COB_ProviderPaymentId` permanece associada à mesma competência. Enquanto a integração falhar, `ASS_DataProximaCobranca` não avança; a execução diária ou o retry da tarefa volta a reconciliar a mesma cobrança. Se o Asaas já possuir `cobranca_<COB_ID>`, o pagamento é vinculado em vez de recriado.

## Recuperação de competência atrasada

Não foi criada migration. A competência original permanece identificável por `ASS_DataProximaCobranca`, `ASS_ID` e `COB_DataVencimento`.

Quando essa data já passou e a cobrança ainda não possui pagamento no gateway, somente o payload enviado ao Asaas usa um vencimento efetivo de recuperação, calculado por `FINANCEIRO_DIAS_VENCIMENTO_RECUPERACAO` (padrão: hoje + 3 dias). O calendário contratual e a chave local de idempotência não são alterados.

## Diagnóstico seguro

Falhas de cliente, reconciliação ou criação persistem e registram `http_code`, endpoint, método, erro resumido, resposta sanitizada e `externalReference`. Chaves e textos associados a token, autorização, senha, segredo, credencial ou API key são removidos. Headers e credenciais do request não são persistidos.

## Operação

Após o deploy, validar:

1. que a migration de `tarefas_agendadas` e sua unique key estão aplicadas;
2. que o cron por minuto está ativo em uma única instalação;
3. que uma tarefa financeira é criada e concluída por dia;
4. logs `task-scheduler.log` e `financeiro-workflow.log` em caso de retry;
5. timezone `America/Sao_Paulo` no processo CLI.
