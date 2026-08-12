-- Origem mínima para diferenciar mensagens da Cloud API, Business App e futuro histórico.
-- A deduplicação permanece transacional na aplicação porque dados históricos podem conter wamids duplicados.

ALTER TABLE conversa_mensagens
    ADD COLUMN MSG_Origem ENUM('api', 'business_app', 'history') NULL AFTER MSG_Direcao;
