# Análise técnica: processamento imediato do Disparo Manual

## Diagnóstico da arquitetura atual

O Disparo Manual já opera como fila: `criarLoteAjax` valida CSRF, template do cliente e destinos, normaliza variáveis, cria um registro em `disparo_manual_lotes` e grava itens em `disparo_manual_itens` com status `pendente`. A tela apenas consulta `statusLoteAjax` por polling a cada 7 segundos. O processamento real ocorre em `worker.php`, que roda por cron, marca lotes pendentes como `processando`, busca itens `pendente` de qualquer lote, envia via Meta, grava `disparos`, `conversa_mensagens`, `consumo_mensal` e uso do plano, e recalcula o lote.

A tabela de lotes guarda cliente, conta Meta, template, totais e status. A tabela de itens guarda cliente, número, variáveis JSON, status, message id, erro, retorno e datas. Não há campo específico de lock, token de execução, dono da execução ou expiração de lock.

O webhook da Meta já é compatível com o Disparo Manual: ao receber status, atualiza `conversa_mensagens`, `disparos`, `fila_envio` e `disparo_manual_itens` pelo `MessageId`. Portanto, o caminho de confirmação `sent/delivered/read/failed` deve ser preservado desde que o endpoint imediato grave o mesmo `DMI_MessageId` e os mesmos registros auxiliares que o worker grava.

Campanhas agendadas usam `campanhas` e `fila_envio`, e continuam no fluxo do worker/cron. A proposta de endpoint imediato deve filtrar somente `disparo_manual_lotes`/`disparo_manual_itens` e não tocar em `fila_envio`.

## Respostas objetivas

1. **É seguro criar endpoint `processarLoteAjax`?** Sim, desde que ele seja um invólucro fino, autenticado e limitado, chamando a mesma rotina interna de processamento do worker para um único lote manual e um bloco pequeno. Não é seguro duplicar a lógica do worker no controller nem deixar o endpoint processar lote sem validação de cliente/status/limite.

2. **Validações necessárias:** usuário logado via `Auth::usuario`; CSRF obrigatório; `CLI_ID` da sessão obrigatório; `lote_id` inteiro positivo; lote deve existir e pertencer ao `CLI_ID`; lote deve estar em status `pendente` ou `processando`; template e conta Meta do lote devem continuar válidos para o cliente; limite por execução deve ser fixo no servidor; método POST; resposta JSON; rate-limit simples por sessão/lote; e nunca aceitar `CLI_ID`, `MTA_ID`, `TMP_ID` ou limite vindos do frontend.

3. **Como evitar duplicidade entre cron e AJAX?** A seleção e a reserva de itens precisam ser atômicas. O padrão mínimo é `UPDATE ... SET DMI_Status = 'processando' WHERE DMI_ID = ? AND DMI_Status = 'pendente'` e só enviar se `rowCount() === 1`. Melhor ainda é reservar o bloco em transação com `SELECT ... FOR UPDATE SKIP LOCKED` quando disponível, ou com atualização atômica por status e reconsulta dos IDs reservados. O código atual faz o update condicionado, mas não checa o resultado antes de enviar; isso ainda permite duplicidade se dois processos selecionarem o mesmo item antes de um deles mudar o status.

4. **Existe lock/status processando suficiente hoje?** Parcialmente. O status `processando` existe, mas não é lock suficiente porque não há verificação de sucesso da reserva por item antes do envio, nem expiração para itens presos.

5. **Precisa de campo novo para lock?** Não é obrigatório para uma primeira versão se a reserva atômica com `rowCount()` for implementada corretamente. É recomendado adicionar campos de robustez em uma segunda etapa ou na mesma aprovação: `DMI_ProcessadoPor`, `DMI_LockToken` e `DMI_LockExpiraEm` ou, no mínimo, `DMI_DataProcessamentoInicio`. Isso permitiria destravar itens presos por queda de processo.

6. **Dá para reaproveitar a mesma função interna do worker?** Sim, e é a melhor recomendação. A lógica de envio manual deve sair de `worker.php` para um service compartilhado, por exemplo `app/Services/DisparoManualQueueService.php`, com método `processarLote($clienteId, $loteId, $limite, $origem)` e método de fallback `processarPendentes($limite)`. O worker chamaria o service sem `loteId`; o endpoint chamaria com `CLI_ID` e `loteId`.

7. **Como evitar voltar ao problema de muitas conexões?** O AJAX deve ser sequencial, nunca paralelo: só chamar o próximo bloco depois que o anterior terminar e depois de uma pausa. O backend deve impor limite fixo e pequeno, timeout curto por request, idempotência por item, e recusar/processar zero se o lote não tiver pendentes. O frontend deve manter uma flag `processandoBloco` para impedir chamadas simultâneas.

8. **Tamanho ideal do bloco em Hostinger compartilhada:** começar com 5 itens por request. Se ficar estável, permitir 10 como teto. Evitar 20+ via AJAX, porque cada item pode fazer chamada externa à Meta, gravações em várias tabelas e atualização de consumo/plano.

9. **Intervalo recomendado entre AJAX:** 1,5 a 3 segundos após cada bloco. Recomendação inicial: 2 segundos, com backoff para 5 a 10 segundos em erro HTTP/timeout. O polling de status pode continuar em 5 a 7 segundos ou ser chamado após cada bloco.

10. **Como preservar envio com variáveis?** Manter o armazenamento de `DMI_VariaveisJson` e usar exatamente o mesmo decode e chamada `MetaService::enviarTemplate($numero, $templateOuItem, $variaveis)`. Não recalcular variáveis no endpoint; ele deve usar as variáveis congeladas na criação do lote.

11. **Como preservar status pelo webhook?** O processamento imediato deve gravar `DMI_MessageId`, inserir em `disparos` com `DSP_MessageId` e salvar `conversa_mensagens.MSG_MetaMessageId`, como o worker já faz. Assim, o webhook continuará atualizando `conversa_mensagens`, `disparos`, `fila_envio` e `disparo_manual_itens` pelos IDs recebidos da Meta.

12. **Como preservar campanhas existentes?** Não alterar a rotina de campanhas/fila_envio além de eventual extração de código comum que não mude comportamento. O endpoint deve aceitar somente lotes manuais e não processar `campanhas` nem `fila_envio`. O worker deve continuar processando campanhas agendadas e também disparos manuais como fallback.

13. **Como lidar se usuário fechar a aba?** Nenhum problema funcional: os itens ainda pendentes permanecem em `disparo_manual_itens` e o worker/cron continua depois. Itens já reservados como `processando` precisam de política de destravamento se o processo PHP cair; sem campo novo, pode haver item preso.

14. **Como lidar com erro parcial no lote?** Cada item deve finalizar independentemente como `aguardando_confirmacao` ou `erro`. O lote deve ser recalculado após o bloco. Erros em um item não devem interromper os próximos, exceto erro sistêmico/rate limit severo da Meta, quando o endpoint deve parar o bloco e devolver orientação para backoff.

15. **Como impedir usuário processar lote de outro cliente?** Buscar o lote sempre com `DML_ID` + `CLI_ID` da sessão e reservar itens com join/condição por `CLI_ID`. Nunca aceitar `CLI_ID` do request. Retornar 404/erro genérico quando o lote não pertencer ao usuário.

## Riscos do AJAX em blocos

- Concorrência com cron se a reserva atômica não for corrigida.
- Chamadas paralelas acidentais se o frontend não controlar uma execução por vez.
- Timeout em hospedagem compartilhada se o bloco for grande ou a Meta demorar.
- Itens presos em `processando` se o request cair no meio sem lock expirável.
- Duplicação de consumo/plano/conversa se a lógica for copiada e divergir do worker.

## Riscos de deixar apenas cron

- Latência percebida de até 60 segundos para iniciar envios manuais.
- Usuário pode interpretar como falha, clicar novamente e criar novo lote.
- Experiência inferior justamente no fluxo manual, que é esperado como ação imediata.
- O polling fica consultando uma fila parada até o próximo cron, desperdiçando requisições sem progresso.

## Melhor recomendação

Aprovar a solução híbrida: manter o worker no cron como autoridade de fallback e campanhas; adicionar endpoint `processarLoteAjax` apenas para acelerar o lote manual recém-criado; extrair a lógica manual do worker para um service compartilhado; corrigir a reserva atômica por item antes de expor processamento concorrente. Implementar inicialmente com 5 itens por bloco, pausa de 2 segundos, sem paralelismo no frontend, e worker ainda executando `processarPendentes` para recuperar abas fechadas.

## Plano de implementação se aprovado

1. Criar service compartilhado para processamento de fila manual.
2. Migrar a lógica manual de `worker.php` para esse service sem alterar contratos de status, consumo, plano, `disparos` e conversas.
3. Ajustar o worker para chamar o service para disparos manuais e manter campanhas/fila_envio como está.
4. Implementar reserva atômica: selecionar candidatos e, antes de enviar cada item, executar `UPDATE ... WHERE DMI_Status = 'pendente'`; se `rowCount() !== 1`, pular.
5. Criar `DisparoController::processarLoteAjax` com login, CSRF, lote do cliente, status permitido e limite fixo.
6. Atualizar `app.js` para, após criar o lote, disparar loop sequencial: processa bloco, consulta status, pausa, repete enquanto houver pendentes.
7. Manter polling como observabilidade e fallback visual, mas sem gerar chamadas de processamento em paralelo.
8. Opcional/recomendado: migration para lock expirável e rotina de reverter `processando` antigo para `pendente` ou `erro_recuperavel`.

## Arquivos que seriam alterados

- `app/Services/DisparoManualQueueService.php` (novo): lógica compartilhada do processamento manual.
- `worker.php`: substituir função manual local por chamada ao service e manter campanhas/fila_envio.
- `app/Controllers/DisparoController.php`: adicionar `processarLoteAjax`.
- `app/Models/DisparoManual.php`: adicionar métodos de busca/reserva/recalculo específicos, se o service não usar SQL direto.
- `public/assets/js/app.js`: loop sequencial de processamento imediato e status.
- `database/migrations/...`: somente se aprovado lock expirável.

## Necessidade de migration

Para a versão mínima segura, a migration não é estritamente necessária se a reserva por `DMI_Status` for atômica e conferida com `rowCount()`. Para produção mais resiliente, recomenda-se migration com campos de lock/expiração para recuperar quedas no meio de request.

## Estratégia para evitar duplicidade

- Nunca enviar item que não tenha sido reservado pelo processo atual.
- Reserva por update condicional em `pendente` e checagem de linhas afetadas.
- Preferir transação curta apenas para reservar IDs, não para chamar a Meta.
- Criar índice útil para `(DML_ID, CLI_ID, DMI_Status, DMI_ID)` se o volume crescer.
- Garantir que `disparos`/`conversa_mensagens` sejam gravados uma única vez por `DMI_ID` reservado.

## Estratégia para preservar worker como fallback

O worker deve continuar rodando por cron e chamando o mesmo service sem restrição de lote/cliente, com limite próprio. O AJAX apenas antecipa blocos de um lote do cliente logado. Se a aba fechar, se houver erro no navegador ou se o endpoint nunca for chamado, o cron encontra os itens `pendente` e processa normalmente.

## Como testar

- **1 número:** criar lote, confirmar que o primeiro bloco processa imediatamente, item vai para `aguardando_confirmacao` ou `erro`, `disparos` e `conversa_mensagens` recebem o mesmo `MessageId`, e webhook atualiza status.
- **10 números:** validar dois blocos de 5, sem chamadas paralelas no navegador, totais do lote corretos e sem duplicidade de `DMI_MessageId`.
- **50 números:** validar tempo total, pausa entre blocos, consumo mensal e controle de plano incrementados por envio aceito, e cron não duplica itens se rodar durante o AJAX.
- **100 números:** validar estabilidade em hospedagem compartilhada, backoff em erro, ausência de timeout por request, e continuidade via worker ao fechar a aba no meio.
