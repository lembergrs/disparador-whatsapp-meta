-- Aplicar manualmente no banco de produção antes da publicação do código.
-- Migration idempotente e não destrutiva.

CREATE TABLE IF NOT EXISTS configuracao_whatsapp_site (
    CWS_ID TINYINT UNSIGNED NOT NULL,
    CWS_Ativo ENUM('S','N') NOT NULL DEFAULT 'N',
    MTA_ID INT NULL,
    CWS_Mensagem VARCHAR(500) NOT NULL DEFAULT 'Olá! Gostaria de conhecer melhor o Disparador.net.',
    CWS_AtualizadoEm TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (CWS_ID),
    KEY idx_cws_meta_conta (MTA_ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'clientes' AND column_name = 'CLI_OrigemCadastro'),
    'SELECT 1',
    'ALTER TABLE clientes ADD COLUMN CLI_OrigemCadastro VARCHAR(50) NULL AFTER CLI_Observacoes'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'clientes' AND index_name = 'idx_clientes_origem_cadastro'),
    'SELECT 1',
    'CREATE INDEX idx_clientes_origem_cadastro ON clientes (CLI_OrigemCadastro)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'clientes' AND column_name = 'CLI_OrigemCadastroOutro'),
    'SELECT 1',
    'ALTER TABLE clientes ADD COLUMN CLI_OrigemCadastroOutro VARCHAR(150) NULL AFTER CLI_OrigemCadastro'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
