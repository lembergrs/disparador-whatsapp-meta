# NFS-e — Auditoria da parametrização fiscal no Disparador

## Decisões

- O Disparador continua sem alterar o contrato da API RL2 NFS-e nesta etapa: `codigoTributacaoNacional` ainda não é enviado no payload.
- Código tributário e descrição são obrigatórios para iniciar uma emissão manual, mesmo com compatibilidade temporária da API, para evitar nova emissão fiscal com parametrização incompleta.
- A descrição `descServico` vem da configuração/snapshot e não recebe sufixo automático.
- Foi adotado snapshot fiscal dedicado por emissão para preservar histórico quando o `.env` mudar.

## Problemas encontrados

### Alto — ausência de snapshot fiscal dedicado

O retorno sanitizado não garantia, de forma imutável e consultável, o código tributário e a descrição preparados em todos os cenários de erro, timeout ou retentativa. Foi criada migration complementar `20260716_add_nfse_fiscal_snapshot.sql` com campos nullable para registros históricos.

### Médio — retentativa poderia ler configuração atual

Sem snapshot, uma emissão em erro temporário poderia usar descrição/código diferentes após alteração do `.env`. O `NfsePayloadBuilder` agora reutiliza `NFE_CodigoTributacaoNacional` e `NFE_DescricaoServicoSnapshot` quando existirem.

## Correções realizadas

- Snapshot fiscal persistido antes da transição para processamento, antes da reserva de `numDPS`, antes de ler certificado e antes de chamada HTTP.
- `NfsePayloadBuilder` usa snapshot existente; se não houver, usa configuração atual validada.
- Bloqueio por configuração incompleta permanece antes de processamento, DPS e HTTP.
- Testes foram ampliados para migration, snapshot, bloqueio e ausência de hardcode ativo.

## Compatibilidade temporária

Até a API RL2 NFS-e aceitar o novo campo de código tributário, o Disparador mantém o código apenas no snapshot interno e no TODO do builder. A descrição continua no campo `descServico`, já existente no contrato atual.

## Regra de imutabilidade

- Emissão sem processamento iniciado pode capturar a configuração atual.
- Após snapshot gravado, erro temporário e retentativa reutilizam o snapshot original.
- Emissões `processando`, `reconciliacao_pendente`, `emitida` e `cancelada` não recebem novos valores do `.env`.
- Registros históricos anteriores à migration permanecem com campos nullable.

## Roteiro futuro para rl2-nfse

Quando a API aceitar `codigoTributacaoNacional`, o TODO do `NfsePayloadBuilder` deve ser removido e o valor do snapshot deve ser enviado em `dadosNota`, mantendo a descrição inalterada e sem concatenação automática.

## Rollback

Reverter o commit de código e, se a migration tiver sido aplicada, executar o rollback manual documentado na própria migration para remover `NFE_CodigoTributacaoNacional` e `NFE_DescricaoServicoSnapshot` após backup.
