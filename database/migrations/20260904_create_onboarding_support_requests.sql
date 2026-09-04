-- Aplicação manual. Não executar automaticamente em produção.
CREATE TABLE IF NOT EXISTS onboarding_suporte_solicitacoes (
    OSS_ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    CLI_ID INT NOT NULL,
    MTA_ID INT NULL,
    USU_ID INT NOT NULL,
    USU_Admin_ID INT NULL,
    OSS_Etapa VARCHAR(80) NOT NULL,
    OSS_Assunto VARCHAR(50) NOT NULL,
    OSS_Descricao VARCHAR(1000) NULL,
    OSS_PeriodoPreferido ENUM('manha','tarde','noite','qualquer') NOT NULL DEFAULT 'qualquer',
    OSS_HorarioDetalhe VARCHAR(120) NULL,
    OSS_Status ENUM('aberta','em_atendimento','concluida','cancelada') NOT NULL DEFAULT 'aberta',
    OSS_CriadaEm DATETIME NOT NULL,
    OSS_AtualizadaEm DATETIME NOT NULL,
    OSS_EncerradaEm DATETIME NULL,
    PRIMARY KEY (OSS_ID),
    KEY idx_oss_cliente_status (CLI_ID, OSS_Status),
    KEY idx_oss_conta_status (MTA_ID, OSS_Status),
    KEY idx_oss_status_data (OSS_Status, OSS_CriadaEm)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
