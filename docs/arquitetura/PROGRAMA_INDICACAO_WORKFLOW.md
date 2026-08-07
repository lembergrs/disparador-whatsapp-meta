# Workflow de criação da indicação

## Papel

`Services\Indicacao\IndicacaoWorkflowService` é a entrada interna da Sprint 2A. Ele coordena Models e Services da fundação de domínio; não replica SQL, geração de código, normalização, máquina de estados ou auditoria. Controllers futuros deverão receber a entrada HTTP, aplicar autenticação/CSRF quando cabível e chamar `registrarIndicacao()`, sem acessar Models diretamente.

## Fluxo de criação

```text
cliente indicado + código + origem
→ normalização central do código
→ código existente e ativo
→ campanha vinculada ativa, pública e vigente
→ dono do código existente
→ indicado existente e diferente do indicador
→ ausência de indicação anterior
→ criação em cadastrada com snapshot
→ transição para aguardando_pagamento
→ auditorias indicacao_criada e status_alterado
→ commit
```

A decisão explícita é preservar a trilha da Sprint 1: a indicação nasce em `cadastrada` e, na mesma transação, avança para `aguardando_pagamento`. Esta sprint não alcança `pagamento_confirmado`.

## Validação do código e campanha

A normalização usa exclusivamente `CodigoIndicacaoNormalizer`. Código vazio, inexistente, `nao_liberado`, `suspenso` ou `cancelado` produz a mesma mensagem genérica para não facilitar enumeração.

A campanha é sempre a apontada por `ICD.ICP_ID`; o workflow não substitui uma campanha vinculada pela campanha pública corrente. No registro, ela precisa estar ativa, pública, já iniciada e não encerrada. O cliente de `ICD.CLI_ID` precisa existir e é o indicador persistido.

## Atomicidade, snapshot e concorrência

O workflow abre uma transação e repete nela as validações críticas, usando locks quando o driver oferece `FOR UPDATE`. `IndicacaoService` cria a indicação, copia `ICP_Percentual` para `IND_PercentualSnapshot`, registra a auditoria e executa a transição inicial usando a transação externa. Qualquer exceção causa rollback integral.

A consulta prévia fornece mensagem antecipada, mas `uk_indicacao_indicado` continua sendo a barreira final para duas requisições disputando o mesmo indicado. Uma violação unique é convertida em erro de domínio sem SQL bruto. Updates de estado continuam condicionados ao estado anterior. O uso concorrente de um mesmo código é permitido para indicados diferentes; o código não é consumível nem regenerado por este fluxo.

Campanha e percentual ficam congelados na indicação. Alterar ou inativar a campanha depois do commit, ou criar campanha posterior, não reescreve `ICP_ID` ou `IND_PercentualSnapshot`. Alterações cadastrais do indicador também não modificam `ICD_Codigo` nem `ICD_CodigoNormalizado`.

## Auditoria e cancelamento

A criação registra `indicacao_criada`; a passagem a `aguardando_pagamento` registra `status_alterado`. `cancelarIndicacao()` é apenas um caso de uso interno/administrativo e exige motivo, registrando `indicacao_cancelada` na mesma transação. Leituras e validações bem-sucedidas não geram evento permanente.

Os dados adicionais continuam sujeitos à whitelist e sanitização da fundação. O workflow não persiste senha, token, telefone, e-mail, CPF/CNPJ ou payload externo.

## Fora do escopo

Não há alteração do cadastro público, View, `?ref=`, Controller, primeiro pagamento, 50% inicial, Financeiro, Asaas, Scheduler, sete dias, crédito, reserva financeira, notificação, WhatsApp, Analytics, área administrativa/cliente, landing page ou regulamento. Não há migration nova nem alteração da migration aplicada da Sprint 1.
