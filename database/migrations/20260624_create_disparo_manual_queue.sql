CREATE TABLE IF NOT EXISTS disparo_manual_lotes (
    DML_ID INT AUTO_INCREMENT PRIMARY KEY,
    CLI_ID INT NOT NULL,
    MTA_ID INT NOT NULL,
    TMP_ID INT NOT NULL,
    DML_Total INT NOT NULL DEFAULT 0,
    DML_TotalEnviados INT NOT NULL DEFAULT 0,
    DML_TotalErros INT NOT NULL DEFAULT 0,
    DML_Status VARCHAR(30) NOT NULL DEFAULT 'pendente',
    DML_DataCadastro DATETIME NOT NULL,
    DML_DataAtualizacao DATETIME NULL,
    DML_DataConclusao DATETIME NULL,
    INDEX idx_dml_cliente (CLI_ID),
    INDEX idx_dml_status (DML_Status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS disparo_manual_itens (
    DMI_ID INT AUTO_INCREMENT PRIMARY KEY,
    DML_ID INT NOT NULL,
    CLI_ID INT NOT NULL,
    DMI_Numero VARCHAR(30) NOT NULL,
    DMI_VariaveisJson JSON NULL,
    DMI_Status VARCHAR(40) NOT NULL DEFAULT 'pendente',
    DMI_MessageId VARCHAR(255) NULL,
    DMI_Erro TEXT NULL,
    DMI_Retorno JSON NULL,
    DMI_DataCadastro DATETIME NOT NULL,
    DMI_DataAtualizacao DATETIME NULL,
    DMI_DataEnvio DATETIME NULL,
    INDEX idx_dmi_lote (DML_ID),
    INDEX idx_dmi_cliente (CLI_ID),
    INDEX idx_dmi_status (DMI_Status),
    INDEX idx_dmi_message (DMI_MessageId),
    CONSTRAINT fk_dmi_lote FOREIGN KEY (DML_ID) REFERENCES disparo_manual_lotes (DML_ID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
