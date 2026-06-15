ALTER TABLE conversas
    ADD COLUMN CON_Responsavel_USU_ID INT NULL AFTER CLI_ID,
    ADD INDEX idx_conversas_responsavel (CON_Responsavel_USU_ID);
