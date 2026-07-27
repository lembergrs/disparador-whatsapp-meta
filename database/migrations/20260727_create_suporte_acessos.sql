-- Aplicação manual. Não executar automaticamente em produção.
CREATE TABLE IF NOT EXISTS suporte_acessos (
    SUA_ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    USU_Admin_ID INT NOT NULL,
    CLI_ID INT NOT NULL,
    USU_Cliente_ID INT NOT NULL,
    SUA_DataInicio DATETIME NOT NULL,
    SUA_DataFim DATETIME NULL,
    SUA_IP VARCHAR(45) NULL,
    SUA_UserAgent VARCHAR(500) NULL,
    SUA_MotivoEncerramento ENUM('retorno_normal','logout','sessao_expirada','outro') NULL,
    PRIMARY KEY (SUA_ID),
    KEY idx_suporte_acessos_admin (USU_Admin_ID),
    KEY idx_suporte_acessos_cliente (CLI_ID),
    KEY idx_suporte_acessos_inicio (SUA_DataInicio),
    KEY idx_suporte_acessos_abertos (SUA_DataFim)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
