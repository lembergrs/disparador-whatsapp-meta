-- Infraestrutura interna para Embedded Signup tradicional e Coexistence.
-- Coexistence permanece desabilitado por META_COEXISTENCE_ENABLED=false.

ALTER TABLE meta_contas
    ADD COLUMN MTA_OnboardingType ENUM('traditional', 'coexistence') NULL AFTER MTA_OperationalStatus,
    ADD COLUMN MTA_PlatformType VARCHAR(50) NULL AFTER MTA_OnboardingType;

ALTER TABLE meta_embedded_signup_attempts
    ADD COLUMN onboarding_type ENUM('traditional', 'coexistence') NULL AFTER request_id;
