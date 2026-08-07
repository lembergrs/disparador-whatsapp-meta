# Elegibilidade e liberação do crédito de indicação

## Evento de primeiro pagamento

`IndicacaoElegibilidadeService::confirmarPrimeiroPagamento()` é o ponto interno reutilizável para uma futura integração financeira. Ele não é Controller nem webhook e não procura pagamentos por polling. Ao receber o ID do cliente indicado e o timestamp confiável do primeiro pagamento, localiza a indicação em `aguardando_pagamento` e inicia sua qualificação.

O timestamp é preservado em `IND_PagamentoConfirmadoEm`. `IND_ConfirmacaoAte` é exatamente pagamento + 7 dias, mantendo hora, minuto e segundo. Datas mais de cinco minutos no futuro em relação ao relógio do domínio são rejeitadas.

## Estados e agendamento

Na mesma transação:

```text
aguardando_pagamento
→ pagamento_confirmado
→ agenda indicacao_confirmacao
→ em_confirmacao
```

A tarefa usa a data de `IND_ConfirmacaoAte`, payload mínimo `{"indicacao_id": 123}` e chave `indicacao_confirmacao_7d:{IND_ID}`. A constraint de idempotência do scheduler impede duplicação. Reprocessar o mesmo evento depois da transição não retrocede nem aprova a indicação.

Como o domínio e `tarefas_agendadas` usam a mesma conexão, indicação, auditoria e tarefa participam da mesma transação. Uma falha de agendamento causa rollback dos marcos e estados.

## Handler e revalidação

`IndicacaoConfirmacaoHandler` é registrado estaticamente no `TaskRegistry`; classes nunca são escolhidas pelo banco. Ele aceita exclusivamente `indicacao_id` e delega ao serviço, que busca novamente a indicação e o crédito com lock.

Antes de aprovar, são verificados existência, estado `em_confirmacao`, pagamento persistido, fim da janela e ausência de crédito. `cancelada`, `fraude` e `inelegivel` concluem sem ação e sem retry. Execução antecipada lança `TaskRetryException`; inconsistências irrecuperáveis usam `TaskPermanentFailureException`. Falhas de persistência são temporárias e reutilizam o retry/backoff central, sem sleeps ou política paralela.

## Aprovação, crédito e atomicidade

Depois dos sete dias completos, uma única transação executa:

```text
indicação: em_confirmacao → aprovada
crédito: inexistente → pendente → em_confirmacao → liberado
```

O crédito copia `IND_PercentualSnapshot`, nunca o percentual atual da campanha. `IND_AprovadaEm` e `ICR_LiberadoEm` são preenchidos pelas transições. As auditorias registram `indicacao_aprovada`, `credito_criado`, a transição intermediária e `credito_liberado`.

Se qualquer etapa falhar, indicação e crédito retornam ao estado anterior pelo rollback. A unique de `IND_ID` em `indicacao_creditos` é a barreira final entre workers. Nova execução de uma indicação aprovada com crédito liberado conclui idempotentemente; indicação aprovada sem crédito ou com crédito não liberado é tratada como inconsistência permanente, não mascarada.

## Logs e dados

O fluxo utiliza o JSONL oficial do Task Scheduler. Nenhum arquivo adicional é criado e o payload completo não é incluído no log. O domínio não agenda nome, e-mail, telefone, CPF/CNPJ, token, webhook ou dado de cobrança.

## Fora do escopo

Não há desconto, cálculo de 15%, mensalidade equivalente, ciclos, reserva financeira, Asaas, Controller/webhook específico, tela, captura de `ref`, notificação, WhatsApp, Analytics ou seed de campanha. A migration já aplicada da Sprint 1 não foi modificada e nenhuma migration nova é necessária.
