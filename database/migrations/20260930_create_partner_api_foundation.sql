-- Fundação da Partner API do Disparador.net.
-- Chaves são armazenadas somente por hash SHA-256; o segredo em texto puro não é persistido.

CREATE TABLE parceiros_api (
    PAR_ID INT UNSIGNED NOT NULL AUTO_INCREMENT,
    PAR_Nome VARCHAR(150) NOT NULL,
    PAR_Identificador VARCHAR(80) NOT NULL,
    PAR_WebhookUrl VARCHAR(500) NULL,
    PAR_WebhookSecretHash CHAR(64) NULL,
    PAR_Ativo ENUM('S','N') NOT NULL DEFAULT 'S',
    PAR_CriadoEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PAR_AtualizadoEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (PAR_ID),
    UNIQUE KEY UK_parceiros_api_identificador (PAR_Identificador),
    KEY IDX_parceiros_api_ativo (PAR_Ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE parceiro_api_keys (
    PAK_ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    PAR_ID INT UNSIGNED NOT NULL,
    PAK_Nome VARCHAR(100) NOT NULL,
    PAK_Prefixo VARCHAR(20) NOT NULL,
    PAK_Hash CHAR(64) NOT NULL,
    PAK_UltimoUsoEm DATETIME NULL,
    PAK_ExpiraEm DATETIME NULL,
    PAK_RevogadaEm DATETIME NULL,
    PAK_Ativo ENUM('S','N') NOT NULL DEFAULT 'S',
    PAK_CriadoEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (PAK_ID),
    UNIQUE KEY UK_parceiro_api_keys_hash (PAK_Hash),
    KEY IDX_parceiro_api_keys_parceiro (PAR_ID, PAK_Ativo),
    CONSTRAINT FK_parceiro_api_keys_parceiro
        FOREIGN KEY (PAR_ID) REFERENCES parceiros_api (PAR_ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE parceiro_clientes (
    PAC_ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    PAR_ID INT UNSIGNED NOT NULL,
    CLI_ID INT NOT NULL,
    MTA_ID INT NOT NULL,
    PAC_IdentificadorExterno VARCHAR(100) NULL,
    PAC_Ativo ENUM('S','N') NOT NULL DEFAULT 'S',
    PAC_CriadoEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PAC_AtualizadoEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (PAC_ID),
    UNIQUE KEY UK_parceiro_cliente_conta (PAR_ID, CLI_ID, MTA_ID),
    KEY IDX_parceiro_clientes_cliente (CLI_ID, PAC_Ativo),
    KEY IDX_parceiro_clientes_meta (MTA_ID, PAC_Ativo),
    CONSTRAINT FK_parceiro_clientes_parceiro
        FOREIGN KEY (PAR_ID) REFERENCES parceiros_api (PAR_ID),
    CONSTRAINT FK_parceiro_clientes_cliente
        FOREIGN KEY (CLI_ID) REFERENCES clientes (CLI_ID),
    CONSTRAINT FK_parceiro_clientes_meta
        FOREIGN KEY (MTA_ID) REFERENCES meta_contas (MTA_ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
