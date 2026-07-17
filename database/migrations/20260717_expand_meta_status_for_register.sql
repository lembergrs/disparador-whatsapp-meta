-- Amplia os estados da conta Meta para separar ativos vinculados de número registrado.
-- Não altera dados existentes e não armazena PIN.
SET @col_type := (
    SELECT COLUMN_TYPE
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'meta_contas'
      AND COLUMN_NAME = 'MTA_Status'
    LIMIT 1
);

SET @sql := IF(
    @col_type IS NOT NULL
    AND @col_type NOT LIKE '%pendente_registro%'
    AND @col_type NOT LIKE '%erro_registro%',
    "ALTER TABLE meta_contas MODIFY COLUMN MTA_Status ENUM('conectado','desconectado','requer_acao','erro','pendente_registro','erro_registro') NULL DEFAULT 'desconectado'",
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
