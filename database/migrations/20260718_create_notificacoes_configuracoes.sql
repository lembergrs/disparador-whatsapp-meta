CREATE TABLE IF NOT EXISTS notificacoes_configuracoes (
    NOC_ID INT UNSIGNED NOT NULL AUTO_INCREMENT,
    NOC_Evento VARCHAR(60) NOT NULL,
    NOC_Canal VARCHAR(30) NOT NULL,
    NOC_Ativo ENUM('S','N') NOT NULL DEFAULT 'S',
    NOC_CriadoEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    NOC_AtualizadoEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (NOC_ID),
    UNIQUE KEY uk_notificacoes_config_evento_canal (NOC_Evento, NOC_Canal),
    KEY idx_notificacoes_config_evento (NOC_Evento),
    KEY idx_notificacoes_config_canal (NOC_Canal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
