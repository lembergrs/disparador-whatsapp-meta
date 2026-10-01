-- Configuração multi-tenant dos webhooks da Partner API.
-- O segredo de assinatura não é persistido em texto puro: ele é derivado de uma chave mestra do ambiente + salt por Partner.

ALTER TABLE parceiros_api
    ADD COLUMN PAR_WebhookAtivo ENUM('S','N') NOT NULL DEFAULT 'N' AFTER PAR_WebhookUrl,
    ADD COLUMN PAR_WebhookEventos LONGTEXT NULL AFTER PAR_WebhookAtivo,
    ADD COLUMN PAR_WebhookSecretSalt CHAR(64) NULL AFTER PAR_WebhookEventos;

UPDATE parceiros_api
SET PAR_WebhookEventos='["message.received","message.sent","message.delivered","message.read","message.failed","message.reaction"]'
WHERE PAR_WebhookEventos IS NULL;
