ALTER TABLE conversa_mensagens
    ADD COLUMN MSG_MediaId VARCHAR(255) NULL AFTER MSG_Tipo,
    ADD COLUMN MSG_MediaMimeType VARCHAR(150) NULL AFTER MSG_MediaId,
    ADD COLUMN MSG_MediaNome VARCHAR(255) NULL AFTER MSG_MediaMimeType,
    ADD COLUMN MSG_MediaSha256 VARCHAR(255) NULL AFTER MSG_MediaNome,
    ADD COLUMN MSG_ReacaoMessageId VARCHAR(255) NULL AFTER MSG_MediaSha256,
    ADD COLUMN MSG_ReacaoEmoji VARCHAR(32) NULL AFTER MSG_ReacaoMessageId;

CREATE INDEX idx_conversa_mensagens_reacao_message_id
    ON conversa_mensagens (MSG_ReacaoMessageId);
