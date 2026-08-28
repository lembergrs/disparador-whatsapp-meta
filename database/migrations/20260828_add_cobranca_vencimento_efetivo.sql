-- Separa a competência contratual do prazo efetivamente concedido para pagamento.
-- COB_DataVencimento permanece sendo a competência e a chave do calendário recorrente.
ALTER TABLE cobrancas
    ADD COLUMN COB_DataVencimentoEfetivo DATE NULL AFTER COB_DataVencimento,
    ADD KEY idx_cobrancas_status_vencimento_efetivo (COB_Status, COB_DataVencimentoEfetivo);

-- Registros anteriores permanecem NULL e usam COB_DataVencimento por fallback.
-- Rollback manual:
-- ALTER TABLE cobrancas DROP INDEX idx_cobrancas_status_vencimento_efetivo,
--     DROP COLUMN COB_DataVencimentoEfetivo;
