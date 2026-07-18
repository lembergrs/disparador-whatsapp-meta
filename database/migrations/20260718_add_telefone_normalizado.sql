ALTER TABLE contatos
    ADD COLUMN CON_TelefoneNormalizado VARCHAR(20) NULL AFTER CON_Telefone,
    ADD INDEX idx_contatos_cliente_tel_norm (CLI_ID, CON_TelefoneNormalizado);

ALTER TABLE conversas
    ADD COLUMN CVS_NumeroNormalizado VARCHAR(20) NULL AFTER CVS_Numero,
    ADD INDEX idx_conversas_meta_tel_norm (MTA_ID, CVS_NumeroNormalizado),
    ADD INDEX idx_conversas_cliente_tel_norm (CLI_ID, CVS_NumeroNormalizado);

-- Backfill conservador: remove máscaras comuns sem unificar registros automaticamente.
-- A rotina PHP passa a gravar o formato canônico completo nas novas operações.
UPDATE contatos
SET CON_TelefoneNormalizado = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(CON_Telefone, '+', ''), ' ', ''), '-', ''), '(', ''), ')', ''), '.', '')
WHERE CON_TelefoneNormalizado IS NULL;

UPDATE conversas
SET CVS_NumeroNormalizado = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(CVS_Numero, '+', ''), ' ', ''), '-', ''), '(', ''), ')', ''), '.', '')
WHERE CVS_NumeroNormalizado IS NULL;
