-- Finalização do Embedded Signup: metadados operacionais e unicidade lógica.
-- Aplicar após 20260708_add_embedded_signup_meta_fields.sql na hospedagem compartilhada.

ALTER TABLE meta_contas
    ADD COLUMN MTA_QualityRating VARCHAR(50) NULL AFTER MTA_DisplayName,
    ADD COLUMN MTA_CodeVerificationStatus VARCHAR(50) NULL AFTER MTA_QualityRating,
    ADD COLUMN MTA_NameStatus VARCHAR(50) NULL AFTER MTA_CodeVerificationStatus,
    ADD COLUMN MTA_OperationalStatus VARCHAR(50) NULL AFTER MTA_NameStatus;

-- Índice não único por compatibilidade com bases que podem conter duplicidades históricas.
-- Após deduplicação operacional, recomenda-se promover para UNIQUE(CLI_ID, MTA_WabaId, MTA_PhoneNumberId).
CREATE INDEX idx_meta_contas_cliente_waba_phone_status
    ON meta_contas (CLI_ID, MTA_WabaId, MTA_PhoneNumberId, MTA_Ativo);
