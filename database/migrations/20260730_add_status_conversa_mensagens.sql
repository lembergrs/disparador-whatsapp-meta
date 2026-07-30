-- Finalidade: persistir confirmações reais sent/delivered/read/failed da Meta nas mensagens de conversa.
-- Branch: feature/status-mensagens-conversas | Data: 2026-07-30.
-- Execução manual após backup; não executar automaticamente em produção.
-- Preserva MSG_DataMensagem (entrada na fila) e os dados existentes.

ALTER TABLE conversa_mensagens
    ADD COLUMN IF NOT EXISTS MSG_EnviadaEm DATETIME NULL AFTER MSG_DataMensagem,
    ADD COLUMN IF NOT EXISTS MSG_EntregueEm DATETIME NULL AFTER MSG_EnviadaEm,
    ADD COLUMN IF NOT EXISTS MSG_LidaEm DATETIME NULL AFTER MSG_EntregueEm,
    ADD COLUMN IF NOT EXISTS MSG_FalhouEm DATETIME NULL AFTER MSG_LidaEm,
    ADD COLUMN IF NOT EXISTS MSG_CodigoErro VARCHAR(50) NULL AFTER MSG_FalhouEm,
    ADD COLUMN IF NOT EXISTS MSG_MensagemErro VARCHAR(500) NULL AFTER MSG_CodigoErro,
    ADD COLUMN IF NOT EXISTS MSG_AtualizadoEm DATETIME NULL AFTER MSG_MensagemErro;

-- MSG_MetaMessageId já existe e é a chave de correlação usada pelo webhook.
CREATE INDEX IF NOT EXISTS idx_conversa_mensagens_meta_message_id
    ON conversa_mensagens (MSG_MetaMessageId);
