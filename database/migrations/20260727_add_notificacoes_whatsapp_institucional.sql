-- Finalidade: complementar a Central existente para WhatsApp institucional e idempotência.
-- Branch: feature/notificacoes-onboarding-whatsapp | Data: 2026-07-27.
-- Ordem: executar após as migrations de notificações de 2026-07-18 e antes de ativar WhatsApp.
-- Fazer backup antes da execução manual. Não executar automaticamente em produção.
-- Depois: configurar credenciais institucionais e ativar somente os eventos desejados no painel.

ALTER TABLE notificacoes
    ADD COLUMN NOT_ChaveIdempotencia VARCHAR(190) NULL AFTER NOT_Dados,
    ADD COLUMN NOT_Template VARCHAR(190) NULL AFTER NOT_ChaveIdempotencia,
    ADD COLUMN NOT_ProviderMessageId VARCHAR(190) NULL AFTER NOT_Template,
    ADD COLUMN NOT_CodigoErro VARCHAR(100) NULL AFTER NOT_ProviderMessageId,
    ADD UNIQUE KEY uk_notificacoes_chave_idempotencia (NOT_ChaveIdempotencia),
    ADD KEY idx_notificacoes_pendentes_evento (NOT_Canal, NOT_Tipo, NOT_Status, NOT_Tentativas);
