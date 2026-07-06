ALTER TABLE templates_meta
    ADD COLUMN TMP_HeaderTipo VARCHAR(20) NULL AFTER TMP_Status,
    ADD COLUMN TMP_HeaderMidiaModo ENUM('nenhuma','estatica','dinamica') NOT NULL DEFAULT 'nenhuma' AFTER TMP_HeaderTipo,
    ADD COLUMN TMP_HeaderMidiaUrlExemplo VARCHAR(1024) NULL AFTER TMP_HeaderMidiaModo,
    ADD COLUMN TMP_HeaderMidiaHandle VARCHAR(255) NULL AFTER TMP_HeaderMidiaUrlExemplo,
    ADD COLUMN TMP_HeaderDocumentoNome VARCHAR(255) NULL AFTER TMP_HeaderMidiaHandle;

ALTER TABLE disparo_manual_lotes
    ADD COLUMN DML_HeaderMidiaTipo VARCHAR(20) NULL AFTER TMP_ID,
    ADD COLUMN DML_HeaderMidiaId VARCHAR(255) NULL AFTER DML_HeaderMidiaTipo,
    ADD COLUMN DML_HeaderMidiaNome VARCHAR(255) NULL AFTER DML_HeaderMidiaId,
    ADD COLUMN DML_HeaderMidiaMime VARCHAR(120) NULL AFTER DML_HeaderMidiaNome,
    ADD COLUMN DML_HeaderMidiaTamanho INT NULL AFTER DML_HeaderMidiaMime;

ALTER TABLE campanhas
    ADD COLUMN CAM_HeaderMidiaTipo VARCHAR(20) NULL AFTER TMP_ID,
    ADD COLUMN CAM_HeaderMidiaId VARCHAR(255) NULL AFTER CAM_HeaderMidiaTipo,
    ADD COLUMN CAM_HeaderMidiaNome VARCHAR(255) NULL AFTER CAM_HeaderMidiaId,
    ADD COLUMN CAM_HeaderMidiaMime VARCHAR(120) NULL AFTER CAM_HeaderMidiaNome,
    ADD COLUMN CAM_HeaderMidiaTamanho INT NULL AFTER CAM_HeaderMidiaMime;
