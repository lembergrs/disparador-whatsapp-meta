# Domínio do Programa de Indicação

## Escopo

Esta entrega cria somente a fundação do domínio. Não cria telas, rotas, integração com Asaas, aplicação de desconto, reserva financeira, scheduler dos sete dias, notificações, Analytics ou regulamento público.

## Entidades

- `indicacao_campanhas`: configuração e histórico de campanhas; apenas uma campanha pública ativa.
- `indicacao_codigos`: código estável por cliente/campanha, com normalização e estados próprios.
- `indicacoes`: vínculo imutável entre indicador e indicado, com percentual congelado no cadastro.
- `indicacao_creditos`: ciclo de vida do benefício e percentual histórico; não contém `COB_ID`, `ASS_ID` nem valores monetários.
- `indicacao_auditoria`: trilha append-only das mutações relevantes.

## Reservas financeiras

Reservas financeiras não pertencem a `indicacao_creditos`. A sprint financeira criará uma estrutura própria, conceitualmente `indicacao_credito_reservas`, para permitir múltiplas reservas históricas por crédito e no máximo uma reserva ativa, preservando cobrança, assinatura, base monetária, mensalidade equivalente, desconto calculado e datas da tentativa.

## Código de indicação

A geração utiliza `CodigoIndicacaoGeneratorInterface` e `CodigoIndicacaoPadraoGenerator`. O formato inicial é `PREFIXO-SUFIXO`. O sufixo tem cinco caracteres do alfabeto `ABCDEFGHJKLMNPQRSTUVWXYZ23456789` e usa `random_bytes()` com rejeição para reduzir viés. IDs internos, timestamps, `rand()`, `mt_rand()` e `uniqid()` não são usados.

A normalização fica centralizada em `CodigoIndicacaoNormalizer` e é reutilizada para gravação e consulta.

## Snapshots

O percentual da campanha é copiado para `IND_PercentualSnapshot` ao criar a indicação. O crédito copia esse valor para `ICR_Percentual`. Alterações futuras na campanha não reescrevem história.

A primeira campanha comercial prevista é de 15%, mas esta entrega não cria seed; o percentual permanece configurável.

## Estados

Código: `nao_liberado`, `ativo`, `suspenso`, `cancelado`.

Indicação: `cadastrada`, `aguardando_pagamento`, `pagamento_confirmado`, `em_confirmacao`, `aprovada`, `cancelada`, `fraude`, `inelegivel`.

Crédito: `pendente`, `em_confirmacao`, `liberado`, `bloqueado`, `reservado`, `utilizado`, `cancelado`, `expirado`.

As transições são validadas em `IndicacaoStatusTransitionService`; updates de status usam o estado anterior como condição para reduzir regressões e corridas.

## Concorrência e constraints

O banco é a última barreira de integridade: código normalizado único, um código por cliente/campanha, um indicador por indicado, um crédito por indicação e coluna gerada/unique para uma campanha pública ativa. Operações compostas usam transação e prepared statements.

## Auditoria

Mutações relevantes devem registrar auditoria dentro do mesmo ciclo transacional. `IndicacaoAuditoriaService` aplica whitelist para dados adicionais e sanitiza termos sensíveis. O model de auditoria expõe inclusão e leitura, sem update/delete.

## FIFO

`IndicacaoCredito::listarFIFO()` ordena créditos liberados por `ICR_LiberadoEm` e `ICR_ID`. A aplicação financeira efetiva do FIFO será implementada em sprint posterior.

## Fora do escopo desta entrega

- cadastro via `ref`;
- benefício inicial de 50%;
- confirmação do primeiro pagamento;
- tarefa de sete dias;
- reservas financeiras e créditos por ciclo;
- Asaas;
- interface cliente/admin;
- notificações e templates Meta;
- Analytics;
- cron/worker adicional;
- seed da campanha inicial.
