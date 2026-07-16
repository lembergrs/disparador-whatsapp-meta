-- Etapa NFS-e: snapshot fiscal parametrizado por emissão.
-- Aplicação manual sugerida, após backup:
--   mysql "$DB_NAME" < database/migrations/20260716_add_nfse_fiscal_snapshot.sql
-- Rollback manual:
--   ALTER TABLE nfse_emissoes
--       DROP COLUMN NFE_CodigoTributacaoNacional,
--       DROP COLUMN NFE_DescricaoServicoSnapshot;
-- Observação: não armazena token, certificado, senha, Authorization ou payload fiscal integral.

ALTER TABLE nfse_emissoes
    ADD COLUMN NFE_CodigoTributacaoNacional VARCHAR(30) NULL AFTER NFE_Serie,
    ADD COLUMN NFE_DescricaoServicoSnapshot TEXT NULL AFTER NFE_CodigoTributacaoNacional;
