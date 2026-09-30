-- Convites self-service dos clientes finais da Partner API.
CREATE TABLE parceiro_convites (
    PCI_ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    PAR_ID INT UNSIGNED NOT NULL,
    PCI_NomeReferencia VARCHAR(150) NULL,
    PCI_TokenHash CHAR(64) NOT NULL,
    PCI_Status ENUM('pendente','aceito','expirado','cancelado') NOT NULL DEFAULT 'pendente',
    PCI_ExpiraEm DATETIME NOT NULL,
    CLI_ID INT NULL,
    PCI_AceitoEm DATETIME NULL,
    PCI_CriadoEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (PCI_ID),
    UNIQUE KEY UK_parceiro_convites_token (PCI_TokenHash),
    KEY IDX_parceiro_convites_partner (PAR_ID,PCI_Status),
    KEY IDX_parceiro_convites_cliente (CLI_ID),
    CONSTRAINT FK_parceiro_convites_partner FOREIGN KEY (PAR_ID) REFERENCES parceiros_api(PAR_ID),
    CONSTRAINT FK_parceiro_convites_cliente FOREIGN KEY (CLI_ID) REFERENCES clientes(CLI_ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
