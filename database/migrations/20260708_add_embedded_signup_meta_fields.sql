-- Campos opcionais retornados no Embedded Signup da Meta.
-- Mantém compatibilidade: a aplicação verifica a existência das colunas antes de usá-las.

ALTER TABLE meta_contas
    ADD COLUMN MTA_BusinessId VARCHAR(100) NULL AFTER MTA_WabaId,
    ADD COLUMN MTA_DisplayName VARCHAR(255) NULL AFTER MTA_NumeroTelefone;

CREATE INDEX idx_meta_contas_cliente_waba_phone
    ON meta_contas (CLI_ID, MTA_WabaId, MTA_PhoneNumberId);
