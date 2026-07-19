-- Diagnóstico obrigatório antes da aplicação: qualquer linha retornada deve ser
-- saneada manualmente. Esta migration não remove nem combina dados financeiros.
SELECT ASS_ID, COB_DataVencimento, COB_Tipo, COUNT(*) AS quantidade
FROM cobrancas
WHERE ASS_ID IS NOT NULL
GROUP BY ASS_ID, COB_DataVencimento, COB_Tipo
HAVING COUNT(*) > 1;

SELECT CEV_Provider, CEV_ProviderEventId, COUNT(*) AS quantidade
FROM cobranca_eventos
WHERE CEV_ProviderEventId IS NOT NULL AND CEV_ProviderEventId <> ''
GROUP BY CEV_Provider, CEV_ProviderEventId
HAVING COUNT(*) > 1;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'cobrancas' AND index_name = 'uk_cobrancas_assinatura_competencia_tipo'),
    'SELECT 1',
    'ALTER TABLE cobrancas ADD UNIQUE KEY uk_cobrancas_assinatura_competencia_tipo (ASS_ID, COB_DataVencimento, COB_Tipo)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'cobranca_eventos' AND index_name = 'uk_cobranca_eventos_provider_evento'),
    'SELECT 1',
    'ALTER TABLE cobranca_eventos ADD UNIQUE KEY uk_cobranca_eventos_provider_evento (CEV_Provider, CEV_ProviderEventId)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Rollback manual:
-- ALTER TABLE cobrancas DROP INDEX uk_cobrancas_assinatura_competencia_tipo;
-- ALTER TABLE cobranca_eventos DROP INDEX uk_cobranca_eventos_provider_evento;
