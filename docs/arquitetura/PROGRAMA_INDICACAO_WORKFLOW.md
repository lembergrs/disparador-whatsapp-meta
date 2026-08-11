# Workflow de criação da indicação

## Papel

`Services\Indicacao\IndicacaoWorkflowService` é a entrada interna da Sprint 2A. Ele coordena Models e Services da fundação de domínio; não replica SQL, geração de código, normalização, máquina de estados ou auditoria. Controllers futuros deverão receber a entrada HTTP, aplicar autenticação/CSRF quando cabível e chamar `registrarIndicacao()`, sem acessar Models diretamente.

## Integração de cadastro e primeiro pagamento

O cadastro público aceita `?ref=` somente como entrada transitória. O
`SiteController` valida o valor por `validarCodigo()`, guarda exclusivamente
`ICD_CodigoNormalizado` na sessão de cadastro e, depois de criar o cliente na
mesma transação, delega a criação do vínculo a `registrarIndicacao(..., 'link')`.
O parâmetro bruto não é persistido nem contém identificadores internos. Código
ausente, inválido ou que se torne inelegível não impede o cadastro comum e não
cria indicação.

`FinanceiroWorkflowService` chama `IndicacaoPrimeiroPagamentoService` somente
quando a cobrança recém-confirmada é a primeira paga do cliente. Esse ponto é
comum ao webhook do Asaas e ao lançamento manual. O serviço cria ou reutiliza o
código da campanha pública ativa e o ativa apenas a partir de `nao_liberado`.
Para um cliente indicado em `aguardando_pagamento`, ele delega a
`IndicacaoElegibilidadeService::confirmarPrimeiroPagamento()`, que preserva a
janela de sete dias e o agendamento transacional existentes.

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

A consulta prévia fornece mensagem antecipada, mas a unicidade do indicado no banco continua sendo a barreira final para duas requisições disputando o mesmo cliente. Uma violação unique é convertida em erro de domínio sem SQL bruto. Updates de estado continuam condicionados ao estado anterior. O uso concorrente de um mesmo código é permitido para indicados diferentes; o código não é consumível nem regenerado por este fluxo.

Campanha e percentual ficam congelados na indicação. Alterar ou inativar a campanha depois do commit, ou criar campanha posterior, não reescreve `ICP_ID` ou `IND_PercentualSnapshot`. Alterações cadastrais do indicador também não modificam `ICD_Codigo` nem `ICD_CodigoNormalizado`.

## Auditoria e cancelamento

A criação registra `indicacao_criada`; a passagem a `aguardando_pagamento` registra `status_alterado`. `cancelarIndicacao()` é apenas um caso de uso interno/administrativo e exige motivo, registrando `indicacao_cancelada` na mesma transação. Leituras e validações bem-sucedidas não geram evento permanente.

Os dados adicionais continuam sujeitos à whitelist e sanitização da fundação. O workflow não persiste senha, token, telefone, e-mail, CPF/CNPJ ou payload externo.

## Fora do escopo

Não há dashboard/área de indicação, landing page, regulamento, notificação,
WhatsApp ou Analytics. O benefício inicial de 50%, os descontos posteriores,
reservas e o Scheduler mantêm seus fluxos próprios. Não há migration nova nem
alteração da migration aplicada da Sprint 1.
