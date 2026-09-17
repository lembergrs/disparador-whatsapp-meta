ALTER TABLE consumo_mensal
    ADD COLUMN CMS_MensagensAvaliacao INT NOT NULL DEFAULT 0 AFTER CMS_Mensagens,
    ADD COLUMN CMS_InicioPlanoPago DATETIME NULL AFTER CMS_MensagensAvaliacao;

-- Clientes que já eram pagantes antes desta migração não devem ter o consumo
-- atual reinterpretado como avaliação. A primeira data de pagamento disponível
-- apenas marca que a transição já ocorreu; CMS_Mensagens continua inalterado.
UPDATE consumo_mensal cm
INNER JOIN clientes cli ON cli.CLI_ID = cm.CLI_ID
LEFT JOIN (
    SELECT CLI_ID, MIN(COB_DataPagamento) AS PrimeiroPagamento
    FROM cobrancas
    WHERE COB_Status = 'pago'
      AND COB_DataPagamento IS NOT NULL
    GROUP BY CLI_ID
) pagos ON pagos.CLI_ID = cm.CLI_ID
SET cm.CMS_InicioPlanoPago = COALESCE(pagos.PrimeiroPagamento, NOW())
WHERE cli.CLI_StatusPagamento = 'pago'
  AND cm.CMS_InicioPlanoPago IS NULL;
