-- Notificações transacionais: estrutura inicial para e-mails de boas-vindas e futuros eventos transacionais.
CREATE TABLE IF NOT EXISTS notificacoes_transacionais (
    NOT_ID INT UNSIGNED NOT NULL AUTO_INCREMENT,
    CLI_ID INT UNSIGNED NOT NULL,
    USU_ID INT UNSIGNED NULL,
    COB_ID INT UNSIGNED NULL,
    NOT_Tipo VARCHAR(50) NOT NULL,
    NOT_Canal VARCHAR(30) NOT NULL,
    NOT_Destinatario VARCHAR(190) NOT NULL,
    NOT_Assunto VARCHAR(190) NOT NULL,
    NOT_Status ENUM('pendente','processando','enviado','erro_temporario','erro_definitivo') NOT NULL DEFAULT 'pendente',
    NOT_Tentativas INT UNSIGNED NOT NULL DEFAULT 0,
    NOT_UltimoErro VARCHAR(255) NULL,
    NOT_DataCriacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    NOT_DataEnvio DATETIME NULL,
    NOT_ChaveIdempotencia VARCHAR(190) NOT NULL,
    PRIMARY KEY (NOT_ID),
    UNIQUE KEY uk_notificacoes_chave_idempotencia (NOT_ChaveIdempotencia),
    KEY idx_notificacoes_cliente_tipo (CLI_ID, NOT_Tipo, NOT_Canal),
    KEY idx_notificacoes_status (NOT_Status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
