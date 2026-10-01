CREATE TABLE parceiro_api_idempotencias (
    PAI_ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    PAR_ID INT UNSIGNED NOT NULL,
    PAI_Chave VARCHAR(120) NOT NULL,
    PAI_RequestHash CHAR(64) NOT NULL,
    PAI_Status ENUM('processando','concluido') NOT NULL DEFAULT 'processando',
    PAI_HttpStatus SMALLINT UNSIGNED NULL,
    PAI_Resposta LONGTEXT NULL,
    PAI_CriadoEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PAI_AtualizadoEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (PAI_ID),
    UNIQUE KEY uq_partner_idempotencia (PAR_ID, PAI_Chave),
    KEY idx_partner_idempotencia_criado (PAI_CriadoEm),
    CONSTRAINT fk_partner_idempotencia_partner FOREIGN KEY (PAR_ID) REFERENCES parceiros_api (PAR_ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
