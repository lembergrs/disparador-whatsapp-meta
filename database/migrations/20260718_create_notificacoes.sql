CREATE TABLE IF NOT EXISTS notificacoes (
    NOT_ID INT UNSIGNED NOT NULL AUTO_INCREMENT,
    CLI_ID INT UNSIGNED NULL,
    NOT_Tipo VARCHAR(60) NOT NULL,
    NOT_Canal VARCHAR(30) NOT NULL,
    NOT_Assunto VARCHAR(190) NULL,
    NOT_Destino VARCHAR(190) NULL,
    NOT_Status ENUM('pendente','processando','enviada','erro_temporario','erro_definitivo','lida') NOT NULL DEFAULT 'pendente',
    NOT_DataEnvio DATETIME NULL,
    NOT_DataLeitura DATETIME NULL,
    NOT_Tentativas INT UNSIGNED NOT NULL DEFAULT 0,
    NOT_Erro VARCHAR(255) NULL,
    NOT_Dados JSON NULL,
    NOT_CriadoEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    NOT_AtualizadoEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (NOT_ID),
    KEY idx_notificacoes_cliente_tipo (CLI_ID, NOT_Tipo),
    KEY idx_notificacoes_status_canal (NOT_Status, NOT_Canal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
