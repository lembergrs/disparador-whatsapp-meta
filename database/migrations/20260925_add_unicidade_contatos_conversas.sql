ALTER TABLE contatos
    ADD UNIQUE KEY UK_Contatos_ClienteTelefoneNormalizado (CLI_ID, CON_TelefoneNormalizado);

ALTER TABLE conversas
    ADD COLUMN CVS_ChaveAtiva VARCHAR(255)
    GENERATED ALWAYS AS (
        CASE
            WHEN CVS_Ativo = 'S' AND CVS_NumeroNormalizado IS NOT NULL AND CVS_NumeroNormalizado <> ''
            THEN CONCAT(CLI_ID, ':', MTA_ID, ':', CVS_NumeroNormalizado)
            ELSE NULL
        END
    ) STORED,
    ADD UNIQUE KEY UK_Conversas_ChaveAtiva (CVS_ChaveAtiva);
