-- Recuperação de senha: tokens temporários, armazenando apenas hash SHA-256 do token enviado por e-mail.
CREATE TABLE IF NOT EXISTS recuperacoes_senha (
    RSE_ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    RSE_USU_ID BIGINT UNSIGNED NOT NULL,
    RSE_TokenHash CHAR(64) NOT NULL,
    RSE_ExpiraEm DATETIME NOT NULL,
    RSE_UtilizadoEm DATETIME NULL,
    RSE_InvalidadoEm DATETIME NULL,
    RSE_IP VARCHAR(45) NULL,
    RSE_UserAgent VARCHAR(255) NULL,
    RSE_CriadoEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (RSE_ID),
    KEY idx_recuperacoes_token_hash (RSE_TokenHash),
    KEY idx_recuperacoes_usuario (RSE_USU_ID),
    KEY idx_recuperacoes_expira_em (RSE_ExpiraEm)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
