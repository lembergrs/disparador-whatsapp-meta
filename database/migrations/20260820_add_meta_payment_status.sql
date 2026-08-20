-- Armazena somente a confirmação declaratória do cliente.
-- Valores: pendente_confirmacao, confirmado_cliente ou NULL para legado.
ALTER TABLE meta_contas
    ADD COLUMN IF NOT EXISTS MTA_PagamentoMetaStatus VARCHAR(30) NULL AFTER MTA_UltimaVerificacao,
    ADD COLUMN IF NOT EXISTS MTA_PagamentoMetaConfirmadoEm DATETIME NULL AFTER MTA_PagamentoMetaStatus;
