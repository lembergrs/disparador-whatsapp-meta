-- Financeiro Partner API: implantação, assinatura variável e snapshot mensal faturável.
-- Reutiliza clientes/cobrancas/NFS-e existentes; não cria um segundo financeiro.

ALTER TABLE parceiros_api
    ADD COLUMN PAR_StatusCadastro ENUM('pendente','aprovado','inativo') NOT NULL DEFAULT 'pendente' AFTER PAR_Ativo,
    ADD COLUMN PAR_StatusImplantacao ENUM('aguardando_validacao','aguardando_pagamento','paga','homologacao','concluida') NOT NULL DEFAULT 'aguardando_validacao' AFTER PAR_StatusCadastro,
    ADD COLUMN PAR_StatusApi ENUM('bloqueada','homologacao','ativa','suspensa') NOT NULL DEFAULT 'bloqueada' AFTER PAR_StatusImplantacao;

ALTER TABLE parceiro_clientes
    ADD COLUMN PAC_Status ENUM('convidado','cadastrado','onboarding','ativo','suspenso','cancelado') NOT NULL DEFAULT 'cadastrado' AFTER PAC_Ativo,
    ADD COLUMN PAC_FaturavelDesde DATETIME NULL AFTER PAC_Status,
    ADD COLUMN PAC_FaturavelAte DATETIME NULL AFTER PAC_FaturavelDesde;

CREATE TABLE parceiro_planos (
    PPL_ID INT UNSIGNED NOT NULL AUTO_INCREMENT,
    PPL_Nome VARCHAR(100) NOT NULL,
    PPL_MinClientes INT UNSIGNED NOT NULL DEFAULT 1,
    PPL_MaxClientes INT UNSIGNED NULL,
    PPL_Valor DECIMAL(10,2) NOT NULL,
    PPL_Ativo ENUM('S','N') NOT NULL DEFAULT 'S',
    PPL_CriadoEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PPL_AtualizadoEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (PPL_ID),
    KEY IDX_parceiro_planos_faixa (PPL_Ativo,PPL_MinClientes,PPL_MaxClientes)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE parceiro_assinaturas (
    PAS_ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    PAR_ID INT UNSIGNED NOT NULL,
    PPL_ID INT UNSIGNED NULL,
    PAS_Status ENUM('pendente','ativa','suspensa','cancelada') NOT NULL DEFAULT 'pendente',
    PAS_ValorImplantacao DECIMAL(10,2) NOT NULL DEFAULT 0,
    PAS_ValorMensal DECIMAL(10,2) NOT NULL DEFAULT 0,
    PAS_DiaVencimento TINYINT UNSIGNED NOT NULL DEFAULT 10,
    PAS_DataInicio DATE NULL,
    PAS_ProximaCobranca DATE NULL,
    PAS_CriadoEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PAS_AtualizadoEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (PAS_ID),
    KEY IDX_partner_assinatura_parceiro (PAR_ID,PAS_Status),
    CONSTRAINT FK_partner_assinatura_parceiro FOREIGN KEY (PAR_ID) REFERENCES parceiros_api(PAR_ID),
    CONSTRAINT FK_partner_assinatura_plano FOREIGN KEY (PPL_ID) REFERENCES parceiro_planos(PPL_ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE parceiro_faturamento_competencias (
    PFC_ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    PAS_ID BIGINT UNSIGNED NOT NULL,
    PAR_ID INT UNSIGNED NOT NULL,
    PFC_Competencia CHAR(6) NOT NULL,
    PFC_ClientesFaturaveis INT UNSIGNED NOT NULL DEFAULT 0,
    PPL_ID INT UNSIGNED NULL,
    PFC_Valor DECIMAL(10,2) NOT NULL,
    COB_ID INT NULL,
    PFC_Status ENUM('calculada','cobrada','paga','cancelada') NOT NULL DEFAULT 'calculada',
    PFC_CriadoEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PFC_AtualizadoEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (PFC_ID),
    UNIQUE KEY UK_partner_competencia (PAS_ID,PFC_Competencia),
    UNIQUE KEY UK_partner_competencia_cobranca (COB_ID),
    CONSTRAINT FK_partner_comp_assinatura FOREIGN KEY (PAS_ID) REFERENCES parceiro_assinaturas(PAS_ID),
    CONSTRAINT FK_partner_comp_parceiro FOREIGN KEY (PAR_ID) REFERENCES parceiros_api(PAR_ID),
    CONSTRAINT FK_partner_comp_plano FOREIGN KEY (PPL_ID) REFERENCES parceiro_planos(PPL_ID),
    CONSTRAINT FK_partner_comp_cobranca FOREIGN KEY (COB_ID) REFERENCES cobrancas(COB_ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE cobrancas
    ADD COLUMN PAR_ID INT UNSIGNED NULL AFTER CLI_ID,
    ADD COLUMN PAS_ID BIGINT UNSIGNED NULL AFTER PAR_ID,
    ADD COLUMN COB_Origem VARCHAR(30) NULL AFTER COB_Tipo,
    ADD KEY IDX_cobrancas_partner (PAR_ID,PAS_ID,COB_Origem),
    ADD CONSTRAINT FK_cobrancas_partner FOREIGN KEY (PAR_ID) REFERENCES parceiros_api(PAR_ID),
    ADD CONSTRAINT FK_cobrancas_partner_assinatura FOREIGN KEY (PAS_ID) REFERENCES parceiro_assinaturas(PAS_ID);
