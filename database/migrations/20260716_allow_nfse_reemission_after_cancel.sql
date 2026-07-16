-- Permite manter histórico de NFS-e cancelada e criar nova emissão para a mesma cobrança.
-- Executar uma única vez após validar backup.

ALTER TABLE nfse_emissoes
    ADD COLUMN NFE_EmissaoAtiva TINYINT(1) NULL DEFAULT 1 AFTER NFE_Status;

UPDATE nfse_emissoes
SET NFE_EmissaoAtiva = NULL
WHERE NFE_Status = 'cancelada';

ALTER TABLE nfse_emissoes
    DROP INDEX uk_nfse_cobranca,
    DROP INDEX uk_nfse_idempotency,
    ADD UNIQUE KEY uk_nfse_cobranca_ativa (COB_ID, NFE_EmissaoAtiva),
    ADD UNIQUE KEY uk_nfse_idempotency (NFE_IdempotencyKey);
