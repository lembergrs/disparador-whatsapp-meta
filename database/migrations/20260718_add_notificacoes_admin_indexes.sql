ALTER TABLE notificacoes
    ADD KEY idx_notificacoes_criado_em (NOT_CriadoEm),
    ADD KEY idx_notificacoes_tipo (NOT_Tipo),
    ADD KEY idx_notificacoes_canal (NOT_Canal),
    ADD KEY idx_notificacoes_cliente_status_criado (CLI_ID, NOT_Status, NOT_CriadoEm);
