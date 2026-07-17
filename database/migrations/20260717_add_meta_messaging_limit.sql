-- Adiciona o limite de conversas iniciadas pela empresa informado pela Meta.
-- Não representa limite comercial, saldo de mensagens ou franquia do plano Disparador.
SET @col_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'meta_contas'
      AND COLUMN_NAME = 'MTA_MessagingLimit'
);

SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE meta_contas ADD COLUMN MTA_MessagingLimit VARCHAR(100) NULL COMMENT ''Limite de conversas iniciadas pela empresa informado pela Meta.'' AFTER MTA_OperationalStatus',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
