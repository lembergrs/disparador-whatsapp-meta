-- Snapshot auditável da composição do valor enviado ao provedor financeiro.
-- Valores monetários do motor de indicação permanecem em centavos inteiros.
ALTER TABLE cobrancas
    ADD COLUMN COB_ValorBaseCentavos BIGINT UNSIGNED NULL AFTER COB_Valor,
    ADD COLUMN COB_DescontoInicialCentavos BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER COB_ValorBaseCentavos,
    ADD COLUMN COB_DescontoIndicacaoCentavos BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER COB_DescontoInicialCentavos,
    ADD COLUMN COB_AdicionaisCentavos BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER COB_DescontoIndicacaoCentavos,
    ADD COLUMN COB_Ciclo ENUM('mensal','trimestral','semestral','anual') NULL AFTER COB_AdicionaisCentavos;

-- Rollback manual:
-- ALTER TABLE cobrancas DROP COLUMN COB_Ciclo, DROP COLUMN COB_AdicionaisCentavos,
-- DROP COLUMN COB_DescontoIndicacaoCentavos, DROP COLUMN COB_DescontoInicialCentavos,
-- DROP COLUMN COB_ValorBaseCentavos;
