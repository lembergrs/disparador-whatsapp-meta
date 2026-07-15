-- Etapa 2 Worker: reserva persistente, retry/backoff e campos mínimos de reconciliação.
-- Aplicação manual sugerida:
--   mysql "$DB_NAME" < database/migrations/20260714_add_worker_retry_fields.sql
-- Rollback manual (somente se necessário e após backup): remover os índices abaixo e depois as colunas adicionadas.
-- Observação: FIL_Tentativas e FIL_MessageId já são usados pelo código existente e não são recriados aqui.

ALTER TABLE fila_envio
    ADD COLUMN FIL_WorkerId VARCHAR(100) NULL AFTER FIL_Retorno,
    ADD COLUMN FIL_DataReserva DATETIME NULL AFTER FIL_WorkerId,
    ADD COLUMN FIL_DataAtualizacao DATETIME NULL AFTER FIL_DataReserva,
    ADD COLUMN FIL_ProximaTentativa DATETIME NULL AFTER FIL_DataAtualizacao,
    ADD COLUMN FIL_UltimoErroTipo VARCHAR(30) NULL AFTER FIL_ProximaTentativa,
    ADD COLUMN FIL_UltimoErroCodigo VARCHAR(100) NULL AFTER FIL_UltimoErroTipo;

CREATE INDEX idx_fila_envio_status_proxima
    ON fila_envio (FIL_Status, FIL_ProximaTentativa);

CREATE INDEX idx_fila_envio_status_reserva
    ON fila_envio (FIL_Status, FIL_DataReserva);

ALTER TABLE disparo_manual_itens
    ADD COLUMN DMI_WorkerId VARCHAR(100) NULL AFTER DMI_Retorno,
    ADD COLUMN DMI_DataReserva DATETIME NULL AFTER DMI_WorkerId,
    ADD COLUMN DMI_ProximaTentativa DATETIME NULL AFTER DMI_DataReserva,
    ADD COLUMN DMI_UltimoErroTipo VARCHAR(30) NULL AFTER DMI_ProximaTentativa,
    ADD COLUMN DMI_UltimoErroCodigo VARCHAR(100) NULL AFTER DMI_UltimoErroTipo,
    ADD COLUMN DMI_Tentativas INT NOT NULL DEFAULT 0 AFTER DMI_UltimoErroCodigo;

CREATE INDEX idx_dmi_status_proxima
    ON disparo_manual_itens (DMI_Status, DMI_ProximaTentativa);

CREATE INDEX idx_dmi_status_reserva
    ON disparo_manual_itens (DMI_Status, DMI_DataReserva);
