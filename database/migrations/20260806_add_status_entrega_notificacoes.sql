-- Permite persistir a progressão real dos webhooks da Meta nas notificações institucionais.
ALTER TABLE notificacoes
    MODIFY COLUMN NOT_Status ENUM('pendente','processando','enviada','entregue','erro_temporario','erro_definitivo','lida') NOT NULL DEFAULT 'pendente',
    ADD COLUMN NOT_DataEntrega DATETIME NULL AFTER NOT_DataEnvio,
    ADD COLUMN NOT_DataErro DATETIME NULL AFTER NOT_DataLeitura,
    ADD KEY idx_notificacoes_provider_message (NOT_ProviderMessageId);
