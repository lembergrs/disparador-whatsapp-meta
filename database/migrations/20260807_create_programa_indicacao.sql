-- Fundação de domínio do Programa de Indicação. Não cria integração financeira nem dados iniciais.
CREATE TABLE IF NOT EXISTS indicacao_campanhas (
 ICP_ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ICP_Nome VARCHAR(120) NOT NULL, ICP_Descricao TEXT NULL,
 ICP_Percentual DECIMAL(5,2) NOT NULL, ICP_DataInicio DATETIME NOT NULL, ICP_DataFim DATETIME NULL,
 ICP_Ativo CHAR(1) NOT NULL DEFAULT 'N', ICP_Publica CHAR(1) NOT NULL DEFAULT 'N', ICP_RegrasSnapshot JSON NOT NULL,
 ICP_CriadoPor_USU_ID BIGINT UNSIGNED NULL, ICP_CriadoEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 ICP_AtualizadoEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 ICP_PublicaAtiva TINYINT GENERATED ALWAYS AS (CASE WHEN ICP_Ativo='S' AND ICP_Publica='S' THEN 1 ELSE NULL END) STORED,
 PRIMARY KEY (ICP_ID), UNIQUE KEY uk_indicacao_campanha_publica_ativa (ICP_PublicaAtiva),
 KEY idx_indicacao_campanhas_vigencia (ICP_Ativo,ICP_Publica,ICP_DataInicio,ICP_DataFim),
 CONSTRAINT chk_indicacao_campanha_percentual CHECK (ICP_Percentual>0 AND ICP_Percentual<=100),
 CONSTRAINT fk_indicacao_campanha_usuario FOREIGN KEY (ICP_CriadoPor_USU_ID) REFERENCES usuarios (USU_ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS indicacao_codigos (
 ICD_ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, CLI_ID BIGINT UNSIGNED NOT NULL, ICP_ID BIGINT UNSIGNED NOT NULL,
 ICD_Codigo VARCHAR(20) NOT NULL, ICD_CodigoNormalizado VARCHAR(20) NOT NULL,
 ICD_Status ENUM('nao_liberado','ativo','suspenso','cancelado') NOT NULL DEFAULT 'nao_liberado',
 ICD_LiberadoEm DATETIME NULL, ICD_SuspensoEm DATETIME NULL, ICD_CanceladoEm DATETIME NULL,
 ICD_CriadoEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ICD_AtualizadoEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (ICD_ID), UNIQUE KEY uk_indicacao_codigo_normalizado (ICD_CodigoNormalizado),
 UNIQUE KEY uk_indicacao_codigo_cliente_campanha (CLI_ID,ICP_ID), KEY idx_indicacao_codigo_status (ICD_Status,ICP_ID),
 CONSTRAINT fk_indicacao_codigo_cliente FOREIGN KEY (CLI_ID) REFERENCES clientes (CLI_ID),
 CONSTRAINT fk_indicacao_codigo_campanha FOREIGN KEY (ICP_ID) REFERENCES indicacao_campanhas (ICP_ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS indicacoes (
 IND_ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ICD_ID BIGINT UNSIGNED NOT NULL, ICP_ID BIGINT UNSIGNED NOT NULL,
 CLI_Indicador_ID BIGINT UNSIGNED NOT NULL, CLI_Indicado_ID BIGINT UNSIGNED NOT NULL, IND_PercentualSnapshot DECIMAL(5,2) NOT NULL,
 IND_Origem ENUM('link','manual') NOT NULL, IND_Status ENUM('cadastrada','aguardando_pagamento','pagamento_confirmado','em_confirmacao','aprovada','cancelada','fraude','inelegivel') NOT NULL DEFAULT 'cadastrada',
 IND_CadastradaEm DATETIME NOT NULL, IND_PagamentoConfirmadoEm DATETIME NULL, IND_ConfirmacaoAte DATETIME NULL,
 IND_AprovadaEm DATETIME NULL, IND_CanceladaEm DATETIME NULL, IND_FraudeEm DATETIME NULL, IND_InelegivelEm DATETIME NULL,
 IND_Motivo VARCHAR(500) NULL, IND_CriadaEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, IND_AtualizadaEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (IND_ID), UNIQUE KEY uk_indicacao_indicado (CLI_Indicado_ID), UNIQUE KEY uk_indicacao_codigo_indicado (ICD_ID,CLI_Indicado_ID),
 KEY idx_indicacoes_indicador_status (CLI_Indicador_ID,IND_Status,IND_ID), KEY idx_indicacoes_confirmacao (IND_Status,IND_ConfirmacaoAte),
 CONSTRAINT chk_indicacao_percentual CHECK (IND_PercentualSnapshot>0 AND IND_PercentualSnapshot<=100),
 CONSTRAINT chk_indicacao_clientes_distintos CHECK (CLI_Indicador_ID<>CLI_Indicado_ID),
 CONSTRAINT fk_indicacao_codigo FOREIGN KEY (ICD_ID) REFERENCES indicacao_codigos (ICD_ID),
 CONSTRAINT fk_indicacao_campanha FOREIGN KEY (ICP_ID) REFERENCES indicacao_campanhas (ICP_ID),
 CONSTRAINT fk_indicacao_indicador FOREIGN KEY (CLI_Indicador_ID) REFERENCES clientes (CLI_ID),
 CONSTRAINT fk_indicacao_indicado FOREIGN KEY (CLI_Indicado_ID) REFERENCES clientes (CLI_ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS indicacao_creditos (
 ICR_ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, IND_ID BIGINT UNSIGNED NOT NULL, CLI_Indicador_ID BIGINT UNSIGNED NOT NULL, ICP_ID BIGINT UNSIGNED NOT NULL,
 ICR_Percentual DECIMAL(5,2) NOT NULL, ICR_Status ENUM('pendente','em_confirmacao','liberado','bloqueado','reservado','utilizado','cancelado','expirado') NOT NULL DEFAULT 'pendente',
 ICR_LiberadoEm DATETIME NULL, ICR_BloqueadoEm DATETIME NULL, ICR_CanceladoEm DATETIME NULL, ICR_ExpiradoEm DATETIME NULL, ICR_UtilizadoEm DATETIME NULL,
 ICR_CriadoEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ICR_AtualizadoEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (ICR_ID), UNIQUE KEY uk_indicacao_credito_indicacao (IND_ID),
 KEY idx_indicacao_creditos_fifo (CLI_Indicador_ID,ICR_Status,ICR_LiberadoEm,ICR_ID),
 CONSTRAINT chk_indicacao_credito_percentual CHECK (ICR_Percentual>0 AND ICR_Percentual<=100),
 CONSTRAINT fk_indicacao_credito_indicacao FOREIGN KEY (IND_ID) REFERENCES indicacoes (IND_ID),
 CONSTRAINT fk_indicacao_credito_indicador FOREIGN KEY (CLI_Indicador_ID) REFERENCES clientes (CLI_ID),
 CONSTRAINT fk_indicacao_credito_campanha FOREIGN KEY (ICP_ID) REFERENCES indicacao_campanhas (ICP_ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS indicacao_auditoria (
 IAU_ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, IAU_Entidade VARCHAR(50) NOT NULL, IAU_EntidadeID BIGINT UNSIGNED NOT NULL,
 IAU_Acao VARCHAR(80) NOT NULL, IAU_StatusAnterior VARCHAR(40) NULL, IAU_StatusNovo VARCHAR(40) NULL, IAU_Motivo VARCHAR(500) NULL,
 USU_ID BIGINT UNSIGNED NULL, IAU_Correlacao VARCHAR(100) NULL, IAU_Dados JSON NULL, IAU_CriadoEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (IAU_ID), KEY idx_indicacao_auditoria_entidade (IAU_Entidade,IAU_EntidadeID,IAU_ID), KEY idx_indicacao_auditoria_correlacao (IAU_Correlacao),
 CONSTRAINT fk_indicacao_auditoria_usuario FOREIGN KEY (USU_ID) REFERENCES usuarios (USU_ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
