ALTER TABLE conversa_mensagens
    MODIFY COLUMN MSG_Origem
    ENUM('api','business_app','history','partner_api')
    DEFAULT NULL;
