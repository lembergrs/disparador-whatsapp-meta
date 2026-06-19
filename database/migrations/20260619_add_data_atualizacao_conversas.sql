ALTER TABLE conversas
    ADD COLUMN CVS_DataAtualizacao DATETIME NULL AFTER CVS_DataUltimaMensagem,
    ADD INDEX idx_conversas_data_atualizacao (CVS_DataAtualizacao);

UPDATE conversas
SET CVS_DataAtualizacao = COALESCE(CVS_DataUltimaMensagem, NOW())
WHERE CVS_DataAtualizacao IS NULL;
