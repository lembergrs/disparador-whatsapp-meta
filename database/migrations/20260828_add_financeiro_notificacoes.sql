-- Vincula a central existente às cobranças e permite recuperar reservas interrompidas.
ALTER TABLE notificacoes
    ADD COLUMN COB_ID INT UNSIGNED NULL AFTER CLI_ID,
    ADD COLUMN NOT_DataReferencia DATE NULL AFTER NOT_Dados,
    ADD COLUMN NOT_ReservadaEm DATETIME NULL AFTER NOT_DataReferencia,
    MODIFY COLUMN NOT_Status ENUM('pendente','processando','enviada','entregue','erro_temporario','erro_definitivo','lida','ignorada') NOT NULL DEFAULT 'pendente',
    ADD KEY idx_notificacoes_cobranca_evento_canal (COB_ID, NOT_Tipo, NOT_Canal),
    ADD KEY idx_notificacoes_status_reserva (NOT_Status, NOT_ReservadaEm),
    ADD KEY idx_notificacoes_financeiro (COB_ID, NOT_Status, NOT_DataReferencia);
