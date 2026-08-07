-- Infraestrutura genérica de tarefas agendadas. Não migra filas existentes.
CREATE TABLE IF NOT EXISTS tarefas_agendadas (
    TAG_ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    TAG_Tipo VARCHAR(80) NOT NULL,
    TAG_Status ENUM('pendente','processando','concluida','falha','cancelada') NOT NULL DEFAULT 'pendente',
    TAG_Prioridade SMALLINT UNSIGNED NOT NULL DEFAULT 100,
    TAG_ExecutarEm DATETIME NOT NULL,
    TAG_Payload JSON NOT NULL,
    TAG_ChaveIdempotencia VARCHAR(190) NULL,
    TAG_Tentativas SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    TAG_MaxTentativas SMALLINT UNSIGNED NOT NULL DEFAULT 3,
    TAG_ProximaTentativaEm DATETIME NULL,
    TAG_ReservadaEm DATETIME NULL,
    TAG_WorkerId VARCHAR(100) NULL,
    TAG_IniciadaEm DATETIME NULL,
    TAG_FinalizadaEm DATETIME NULL,
    TAG_UltimoErro VARCHAR(500) NULL,
    TAG_CriadaEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    TAG_AtualizadaEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (TAG_ID),
    UNIQUE KEY uk_tarefas_agendadas_idempotencia (TAG_ChaveIdempotencia),
    KEY idx_tarefas_agendadas_elegiveis (TAG_Status, TAG_ExecutarEm, TAG_ProximaTentativaEm, TAG_Prioridade, TAG_ID),
    KEY idx_tarefas_agendadas_lease (TAG_Status, TAG_ReservadaEm)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
