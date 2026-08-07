# Fundação de domínio do Programa de Indicação

## Escopo implementado

Esta sprint implementa somente persistência e regras internas de domínio. Não expõe rota, controller, tela, cadastro público ou ação administrativa e não integra financeiro, Asaas, Task Scheduler, notificações ou Analytics.

## Entidades e relacionamentos

- `indicacao_campanhas`: configuração versionada, percentual, vigência e flags ativa/pública. Não há seed; a primeira campanha de 15% será cadastrada operacionalmente em etapa futura.
- `indicacao_codigos`: um código congelado por cliente/campanha, com forma normalizada globalmente única.
- `indicacoes`: vínculo imutável entre indicador e indicado, campanha, código, origem e percentual congelado.
- `indicacao_creditos`: benefício originado por uma indicação, com percentual histórico e estado; não contém `COB_ID`, `ASS_ID` nem valor monetário.
- `indicacao_auditoria`: eventos append-only das mutações do domínio.

Uma indicação gera no máximo um crédito. Um indicado possui no máximo um indicador. As FKs impedem referências órfãs, e índices cobrem vigência de campanha, consulta de códigos, confirmação de indicações e FIFO de créditos.

## Estados

Código: `nao_liberado`, `ativo`, `suspenso`, `cancelado`.

Indicação: `cadastrada`, `aguardando_pagamento`, `pagamento_confirmado`, `em_confirmacao`, `aprovada`, `cancelada`, `fraude`, `inelegivel`.

Crédito: `pendente`, `em_confirmacao`, `liberado`, `bloqueado`, `reservado`, `utilizado`, `cancelado`, `expirado`.

`IndicacaoStatusTransitionService` contém os mapas de transição. Models fazem updates condicionais pelo estado anterior, tornando uma alteração concorrente detectável. Pagamento, espera de sete dias e reserva financeira não são executados nesta sprint.

## Campanha pública e concorrência

A coluna gerada `ICP_PublicaAtiva` vale `1` somente para campanha simultaneamente pública e ativa. O índice único permite várias campanhas históricas, mas torna o banco a barreira final contra duas campanhas públicas ativas. O service também consulta com lock dentro da transação e retorna erro de domínio claro.

Duplicidades de código normalizado, código por cliente/campanha, indicado e crédito por indicação possuem índices únicos. Criações e transições relevantes ocorrem em transação; updates de estado incluem o estado anterior na condição.

## Código de indicação

O formato é `PREFIXO-SUFIXO`. O prefixo deriva, em ordem, de nome fantasia, razão social ou nome, é transliterado e congelado na criação. O sufixo possui cinco caracteres do alfabeto `ABCDEFGHJKLMNPQRSTUVWXYZ23456789` e usa `random_bytes()` com rejeição para evitar viés.

`CodigoIndicacaoNormalizer` é a única normalização usada na gravação e consulta: trim, uppercase e remoção de caracteres fora de `A-Z0-9`. Colisões são repetidas até um limite controlado; a constraint única continua sendo a proteção concorrente final.

## Snapshots e auditoria

Ao criar a indicação, `IND_PercentualSnapshot` copia o percentual da campanha. Ao criar o crédito, `ICR_Percentual` copia o snapshot da indicação. Alterações posteriores na campanha não reescrevem história.

Cada criação e mutação relevante grava auditoria na mesma transação. O serviço aceita apenas campos adicionais explicitamente permitidos e sanitiza motivo, removendo tokens, senhas, secrets, authorization e payloads. O Model de auditoria oferece somente inclusão e leitura, sem métodos de update/delete.

## Reserva financeira futura

`indicacao_credito_reservas` não existe nesta sprint. Ela será criada na sprint financeira e armazenará cobrança, assinatura, base monetária, mensalidade equivalente, desconto e datas de reserva/uso/liberação. A separação permitirá uma reserva ativa e várias reservas históricas por crédito sem apagar tentativas anteriores.

## Fora do escopo

Não foram implementados primeiro pagamento, desconto inicial de 50%, cálculo de créditos por ciclo, Asaas, processamento de sete dias, scheduler, cron, worker, notificações, Meta, Analytics, landing page, regulamento, interface do cliente ou administrativa, controller, rota ou campo `ref`.
