-- Etapa 1 NFS-e: fundação local de dados, idempotência, sequência DPS e cadastro fiscal.
-- Aplicação manual sugerida:
--   mysql "$DB_NAME" < database/migrations/20260715_create_nfse_foundation.sql
-- Rollback manual (somente se necessário e após backup):
--   DROP TABLE nfse_emissoes;
--   DROP TABLE nfse_dps_sequencias;
--   ALTER TABLE clientes DROP COLUMN CLI_NFSe_CNPJ, DROP COLUMN CLI_NFSe_RazaoSocial,
--       DROP COLUMN CLI_NFSe_CEP, DROP COLUMN CLI_NFSe_Logradouro, DROP COLUMN CLI_NFSe_Numero,
--       DROP COLUMN CLI_NFSe_Complemento, DROP COLUMN CLI_NFSe_Bairro, DROP COLUMN CLI_NFSe_Municipio,
--       DROP COLUMN CLI_NFSe_UF, DROP COLUMN CLI_NFSe_CodigoIBGE,
--       DROP COLUMN CLI_NFSe_Telefone, DROP COLUMN CLI_NFSe_Email;
-- Observação: não armazena API_AUTH_TOKEN, Authorization, certificado PFX/Base64 ou senha do certificado.

CREATE TABLE nfse_dps_sequencias (
    NDS_ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    NDS_PrestadorCnpj VARCHAR(14) NOT NULL,
    NDS_Ambiente VARCHAR(30) NOT NULL DEFAULT 'production',
    NDS_Serie VARCHAR(20) NOT NULL,
    NDS_ProximoNumero BIGINT UNSIGNED NOT NULL DEFAULT 1,
    NDS_DataCriacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    NDS_DataAtualizacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (NDS_ID),
    UNIQUE KEY uk_nfse_dps_contexto (NDS_PrestadorCnpj, NDS_Ambiente, NDS_Serie)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE nfse_emissoes (
    NFE_ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    CLI_ID INT NOT NULL,
    COB_ID INT NULL,
    NFE_ReferenciaPagamento VARCHAR(191) NULL,
    NFE_Status VARCHAR(40) NOT NULL DEFAULT 'pendente_dados',
    NFE_IdempotencyKey VARCHAR(191) NOT NULL,
    NFE_PrestadorCnpj VARCHAR(14) NULL,
    NFE_Ambiente VARCHAR(30) NOT NULL DEFAULT 'production',
    NFE_NumDps VARCHAR(100) NULL,
    NFE_IdDps VARCHAR(191) NULL,
    NFE_ChaveDps VARCHAR(191) NULL,
    NFE_ChaveAcesso VARCHAR(191) NULL,
    NFE_RequestIdEmissao VARCHAR(191) NULL,
    NFE_RequestIdConsulta VARCHAR(191) NULL,
    NFE_RequestIdCancelamento VARCHAR(191) NULL,
    NFE_NumeroNota VARCHAR(100) NULL,
    NFE_Serie VARCHAR(20) NULL,
    NFE_Competencia DATE NULL,
    NFE_DataEmissao DATETIME NULL,
    NFE_ValorFiscal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    NFE_DescricaoServico TEXT NULL,
    NFE_XmlStoragePath VARCHAR(500) NULL,
    NFE_XmlSha256 CHAR(64) NULL,
    NFE_PdfStoragePath VARCHAR(500) NULL,
    NFE_PdfSha256 CHAR(64) NULL,
    NFE_Tentativas INT UNSIGNED NOT NULL DEFAULT 0,
    NFE_WorkerId VARCHAR(100) NULL,
    NFE_DataReserva DATETIME NULL,
    NFE_ProximaTentativa DATETIME NULL,
    NFE_UltimoErroTipo VARCHAR(60) NULL,
    NFE_UltimoErroCodigo VARCHAR(100) NULL,
    NFE_UltimoErroMensagem TEXT NULL,
    NFE_RetornoSanitizado MEDIUMTEXT NULL,
    NFE_DataCriacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    NFE_DataAtualizacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    NFE_DataCancelamento DATETIME NULL,
    PRIMARY KEY (NFE_ID),
    UNIQUE KEY uk_nfse_cobranca (COB_ID),
    UNIQUE KEY uk_nfse_idempotency (NFE_IdempotencyKey),
    UNIQUE KEY uk_nfse_numdps_contexto (NFE_PrestadorCnpj, NFE_Ambiente, NFE_Serie, NFE_NumDps),
    KEY idx_nfse_cliente_status (CLI_ID, NFE_Status),
    KEY idx_nfse_status_proxima (NFE_Status, NFE_ProximaTentativa),
    KEY idx_nfse_chave_acesso (NFE_ChaveAcesso),
    KEY idx_nfse_chave_dps (NFE_ChaveDps)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE clientes
    ADD COLUMN CLI_NFSe_CNPJ VARCHAR(14) NULL AFTER CLI_CPF_CNPJ,
    ADD COLUMN CLI_NFSe_RazaoSocial VARCHAR(150) NULL AFTER CLI_RazaoSocial,
    ADD COLUMN CLI_NFSe_CEP VARCHAR(8) NULL AFTER CLI_Telefone,
    ADD COLUMN CLI_NFSe_Logradouro VARCHAR(150) NULL AFTER CLI_NFSe_CEP,
    ADD COLUMN CLI_NFSe_Numero VARCHAR(20) NULL AFTER CLI_NFSe_Logradouro,
    ADD COLUMN CLI_NFSe_Complemento VARCHAR(100) NULL AFTER CLI_NFSe_Numero,
    ADD COLUMN CLI_NFSe_Bairro VARCHAR(100) NULL AFTER CLI_NFSe_Complemento,
    ADD COLUMN CLI_NFSe_Municipio VARCHAR(100) NULL AFTER CLI_NFSe_Bairro,
    ADD COLUMN CLI_NFSe_UF CHAR(2) NULL AFTER CLI_NFSe_Municipio,
    ADD COLUMN CLI_NFSe_CodigoIBGE VARCHAR(7) NULL AFTER CLI_NFSe_UF,
    ADD COLUMN CLI_NFSe_Telefone VARCHAR(20) NULL AFTER CLI_NFSe_CodigoIBGE,
    ADD COLUMN CLI_NFSe_Email VARCHAR(150) NULL AFTER CLI_NFSe_Telefone;

CREATE INDEX idx_clientes_nfse_cnpj
    ON clientes (CLI_NFSe_CNPJ);
