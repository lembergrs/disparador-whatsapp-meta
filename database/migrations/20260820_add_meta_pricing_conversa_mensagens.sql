-- Persiste os fatos de pricing enviados pela Meta, sem calcular tarifas ou alterar consumo comercial.
-- Todos os campos são opcionais para preservar mensagens históricas e eventos sem pricing.

ALTER TABLE conversa_mensagens
    ADD COLUMN IF NOT EXISTS MSG_MetaCategoria VARCHAR(50) NULL AFTER MSG_MetaMessageId,
    ADD COLUMN IF NOT EXISTS MSG_PricingBillable TINYINT(1) NULL AFTER MSG_MetaCategoria,
    ADD COLUMN IF NOT EXISTS MSG_PricingModel VARCHAR(100) NULL AFTER MSG_PricingBillable,
    ADD COLUMN IF NOT EXISTS MSG_PricingType VARCHAR(100) NULL AFTER MSG_PricingModel,
    ADD COLUMN IF NOT EXISTS MSG_PricingMarket VARCHAR(100) NULL AFTER MSG_PricingType,
    ADD COLUMN IF NOT EXISTS MSG_PricingCurrency VARCHAR(20) NULL AFTER MSG_PricingMarket;
