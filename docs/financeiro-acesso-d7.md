# Política financeira de acesso D+7

## Separação de responsabilidades

- A cobrança representa a obrigação financeira e pode estar `pendente`, `vencido`, `pago` ou `cancelado`.
- A assinatura representa o vínculo comercial. O vencimento de uma cobrança não altera automaticamente uma assinatura `ativa`.
- O acesso operacional é uma decisão derivada por `FinanceiroAccessPolicyService`.

O gateway não participa dessa decisão.

## Data financeira

Toda avaliação usa:

```sql
COALESCE(COB_DataVencimentoEfetivo, COB_DataVencimento)
```

A regra trabalha por data no timezone configurado da aplicação:

- D0: regular para acesso;
- D+1 a D+6: `tolerancia`, com acesso operacional;
- D+7 em diante: `suspenso`, sem acesso operacional.

`FINANCEIRO_DIAS_TOLERANCIA_VENCIMENTO` possui padrão 7 e é a configuração única do limite.

Na implantação, deve-se confirmar que uma variável de ambiente preexistente com esse nome não sobrescreve o padrão com outro valor.

## Obrigação relevante

A política busca somente cobranças:

- do cliente avaliado;
- vinculadas por `ASS_ID` à assinatura `ativa` atual;
- com status `pendente` ou `vencido`;
- cujo vencimento financeiro já passou.

São ignoradas cobranças pagas, canceladas, futuras, de assinaturas encerradas e registros legados sem `ASS_ID`. Havendo mais de uma obrigação aberta na assinatura atual, a mais antiga determina os dias de atraso e eventual suspensão.

## Interface e worker

`Core\Auth` e `WorkerOperationalValidatorService` chamam o mesmo serviço. Não existem consultas próprias de tolerância nesses componentes.

Durante a suspensão continuam acessíveis as rotas de regularização já permitidas por `Auth`: dashboard, financeiro, indicação, conta, login, site e documentos PDF/XML de NFS-e. Recursos operacionais continuam bloqueados. Trial e pré-trial mantêm as regras anteriores quando não existe assinatura comercial ativa.

No worker, D+7 retorna bloqueio temporário `financeiro_inadimplente_d7`, preservando o item para retomada após regularização e impedindo novo consumo/envio enquanto suspenso.

## Vencimento e pagamento

`processarVencimentos()` altera somente a cobrança de `pendente` para `vencido` e mantém `CLI_StatusPagamento` como estado derivado de compatibilidade. A assinatura não é marcada como vencida.

Após `PAYMENT_CONFIRMED` ou `PAYMENT_RECEIVED`, a cobrança passa a `pago`. Na avaliação seguinte, deixa de existir obrigação bloqueante e tanto a interface quanto o worker são liberados naturalmente, sem intervenção administrativa.

## Observabilidade

Cada negativa por D+7 registra em `storage/logs/financeiro-acesso.log` apenas:

- cliente;
- cobrança responsável;
- vencimento financeiro;
- dias de atraso;
- regra aplicada.

Nenhum dado pessoal ou segredo do gateway é registrado.

Para evitar repetição por chamadas do Auth e do worker, o evento é limitado a um registro por cliente, cobrança e data. A deduplicação usa um índice diário no mesmo diretório, protegido por bloqueio de arquivo, e portanto é compartilhada entre processos PHP sem depender de memória ou banco de dados.
