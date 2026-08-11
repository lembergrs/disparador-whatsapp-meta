# Reservas financeiras de créditos de indicação

## Integração com o Financeiro (Sprint 3B)

`FinanceiroWorkflowService` cria primeiro `cobrancas.COB_ID` e, antes de chamar o
Asaas, delega a preparação da cobrança elegível a
`IndicacaoDescontoService::prepararDesconto()`, usando a referência
`cobranca:<COB_ID>`. O workflow não seleciona créditos, não calcula percentuais e
não implementa FIFO: essas responsabilidades continuam no domínio de indicação.

A composição enviada ao provedor fica congelada na cobrança (valor-base, ciclo,
desconto inicial, desconto de indicação, adicionais e valor final). A primeira
cobrança recebe o benefício comercial independente de 50% e não consulta nem
reserva créditos. Somente cobranças posteriores podem reservar créditos; valores
adicionais permanecem integrais.

Na fronteira do Asaas, `value` recebe o nominal congelado (valor-base mais
adicionais) e a soma monetária dos benefícios congelados é enviada como
`discount.value`, com `type=FIXED` e `dueDateLimitDays=0`. `COB_Valor` preserva a
semântica interna líquida usada pelas telas e pela NFS-e; percentuais, FIFO,
campanha e limites por ciclo nunca são enviados ao provedor.

Uma criação bem-sucedida no Asaas mantém as reservas em `reservada`. O pagamento
confirmado pelo workflow (webhook idempotente ou lançamento manual) chama
`confirmarUtilizacao()`, realizando `reservada -> utilizada`. Vencimento simples
mantém a reserva porque a cobrança ainda aceita pagamento. Cancelamento ou falha
definitiva de criação externa chama `liberarReservas()`, realizando
`reservada -> liberada`. Eventos duplicados são descartados pela chave
idempotente financeira antes da transição do domínio, que também mantém seus locks
e sua própria idempotência.

## Escopo

A Sprint 3A implementou um motor interno e isolado para calcular e reservar créditos antes de uma cobrança. O motor não conhece o Asaas; a Sprint 3B apenas passou a consumi-lo pelo workflow financeiro.

## Tabela e cardinalidade

A migration incremental `20260807_create_indicacao_credito_reservas.sql` cria `indicacao_credito_reservas`. Uma referência técnica (`ICRR_ReferenciaTipo`, `ICRR_ReferenciaID`) pode possuir N reservas, limitada no serviço pelos meses do ciclo. Um crédito pode possuir N reservas históricas, mas somente uma `reservada` por vez.

`ICRR_CreditoAtivo` é uma coluna gerada que contém `ICR_ID` apenas no estado `reservada`; seu índice unique é a barreira final contra duas reservas ativas. A combinação referência + crédito também é única. A migration não possui FK de cobrança, assinatura ou provedor.

## Estados

```text
reservada → utilizada
reservada → liberada
reservada → cancelada
liberada → reservada (retry da mesma referência e mesmos snapshots)
```

`utilizada` e `cancelada` são estados finais. `liberada` representa devolução normal do crédito ao FIFO; no retry da mesma referência financeira, ela pode ser restabelecida como `reservada` se todos os snapshots ainda corresponderem ao desconto congelado e os créditos originais continuarem disponíveis.

## Ciclos, centavos e mensalidade equivalente

`IndicacaoCalculoDescontoService` centraliza os ciclos: mensal 1, trimestral 3, semestral 6 e anual 12. Todos os valores monetários são inteiros em centavos e a base é o valor efetivamente contratado para o ciclo, sem `PLA_ValorMensal`, excedentes, adicionais, taxas ou benefício inicial.

Quando a divisão não é exata, usa-se quociente inteiro e os centavos restantes são distribuídos, um por fração, a partir da primeira. Exemplo: 10.001 / 3 gera `[3.334, 3.334, 3.333]`; a soma continua exatamente 10.001. Cada reserva congela a fração mensal efetivamente usada.

Percentuais DECIMAL(5,2) são convertidos em centésimos de percentual sem float. O desconto usa arredondamento half-up central: `(mensalidade × percentual_em_centésimos + 5000) div 10000`. Cada crédito é calculado separadamente; nunca se agrega percentual e quantidade sobre o ciclo inteiro.

## Preparação e snapshots

`IndicacaoDescontoService::prepararDesconto()` valida referência, ciclo, cliente e valor-base; serializa a referência com lock nomeado no MariaDB; retorna snapshots existentes se a referência já foi preparada; seleciona com lock somente créditos `liberado`, em `ICR_LiberadoEm ASC, ICR_ID ASC`; limita a seleção aos meses do ciclo; calcula cada desconto histórico; cria reservas e muda cada crédito para `reservado`; e confirma tudo em uma transação.

Cada linha congela ciclo, meses, valor-base, fração mensal, percentual histórico e desconto. O resultado contém referência, ciclo, meses, valor-base, distribuição mensal, quantidade, desconto total, valor final e reservas sem dados pessoais.

## Idempotência e concorrência

Preparar novamente a mesma referência devolve as mesmas linhas e snapshots, mesmo que seja informado outro preço ou existam créditos mais novos. O lock nomeado fecha a corrida da ausência inicial da referência; locks dos créditos e a unique da reserva ativa impedem duas referências de consumir o mesmo crédito. Qualquer falha em inserção, auditoria ou mudança de estado desfaz o lote inteiro.

## Utilização e liberação

`confirmarUtilizacao()` bloqueia todas as reservas da referência e, atomicamente, muda reservas para `utilizada` e créditos para `utilizado`. Repetição é idempotente.

`liberarReservas()` exige motivo e muda todas as reservas para `liberada` e todos os créditos para `liberado`. A data original `ICR_LiberadoEm` é preservada quando o crédito retorna do estado `reservado`, mantendo a posição histórica no FIFO. Repetição é idempotente; uma referência já liberada não pode ser confirmada.

`garantirReservasDaReferencia()` protege retries após falha externa. Sob o mesmo lock e transação da referência, valida o total congelado, reutiliza reservas ainda ativas ou restabelece as mesmas reservas liberadas e seus créditos originais. Percentuais, bases, descontos individuais e quantidade não são recalculados. Se algum crédito não puder ser restabelecido ou o total divergir, o retry falha antes de enviar a cobrança ao Asaas.

A auditoria registra `reserva_criada`, `credito_reservado`, `reserva_utilizada`, `credito_utilizado`, `reserva_liberada` e `credito_reliberado`, sem PII.

## Fora do escopo

Não há geração de cobrança, Asaas, webhook, Financeiro real, assinatura, recorrência, excedentes, desconto inicial, aplicação HTTP, tela, Scheduler ou nova tarefa. A migration aplicada da Sprint 1 não foi modificada e a migration incremental não é executada automaticamente.
