-- Compatibiliza o Embedded Signup com estados operacionais adicionais.
-- Preserva registros existentes e apenas amplia o ENUM de MTA_Status.

ALTER TABLE meta_contas
MODIFY COLUMN MTA_Status
ENUM('conectado','desconectado','requer_acao','erro')
NULL DEFAULT 'desconectado';
