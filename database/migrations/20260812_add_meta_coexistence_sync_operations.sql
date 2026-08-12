-- Estado operacional mínimo da sincronização Coexistence e fila assíncrona de histórico.
-- META_COEXISTENCE_ENABLED permanece false até homologação com número real.

ALTER TABLE meta_contas
    ADD COLUMN MTA_ContactSyncRequestId VARCHAR(100) NULL AFTER MTA_PlatformType,
    ADD COLUMN MTA_ContactSyncStatus VARCHAR(30) NULL AFTER MTA_ContactSyncRequestId,
    ADD COLUMN MTA_HistorySyncRequestId VARCHAR(100) NULL AFTER MTA_ContactSyncStatus,
    ADD COLUMN MTA_HistorySyncStatus VARCHAR(30) NULL AFTER MTA_HistorySyncRequestId,
    ADD COLUMN MTA_HistoryPhase VARCHAR(50) NULL AFTER MTA_HistorySyncStatus,
    ADD COLUMN MTA_HistoryChunkOrder INT NULL AFTER MTA_HistoryPhase,
    ADD COLUMN MTA_HistoryProgress TINYINT UNSIGNED NULL AFTER MTA_HistoryChunkOrder,
    ADD COLUMN MTA_LastSyncEventAt DATETIME NULL AFTER MTA_HistoryProgress,
    ADD COLUMN MTA_DisconnectReason VARCHAR(255) NULL AFTER MTA_LastSyncEventAt,
    ADD COLUMN MTA_DisconnectInitiatedBy VARCHAR(100) NULL AFTER MTA_DisconnectReason,
    ADD COLUMN MTA_LifecycleUpdatedAt DATETIME NULL AFTER MTA_DisconnectInitiatedBy;

CREATE TABLE meta_coexistence_history_jobs (
    MCH_ID BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    MTA_ID INT NOT NULL,
    MCH_DedupeKey CHAR(64) NOT NULL,
    MCH_RequestId VARCHAR(100) NULL,
    MCH_Phase VARCHAR(50) NULL,
    MCH_ChunkOrder INT NULL,
    MCH_Progress TINYINT UNSIGNED NULL,
    MCH_Payload MEDIUMTEXT NOT NULL,
    MCH_Status ENUM('pendente','processando','concluido','erro') NOT NULL DEFAULT 'pendente',
    MCH_Tentativas INT NOT NULL DEFAULT 0,
    MCH_WorkerId VARCHAR(100) NULL,
    MCH_ReservadoEm DATETIME NULL,
    MCH_CriadoEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    MCH_ProcessadoEm DATETIME NULL,
    MCH_UltimoErro VARCHAR(500) NULL,
    CONSTRAINT fk_history_jobs_meta_conta FOREIGN KEY (MTA_ID) REFERENCES meta_contas(MTA_ID),
    UNIQUE KEY uk_history_chunk (MTA_ID, MCH_DedupeKey),
    KEY idx_history_jobs_status (MCH_Status, MCH_CriadoEm)
);
