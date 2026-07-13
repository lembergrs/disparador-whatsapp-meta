-- Tentativas temporárias do Embedded Signup fora de $_SESSION.
-- Evita lock de sessão entre o callback OAuth e o POST assíncrono do FINISH.

CREATE TABLE IF NOT EXISTS meta_embedded_signup_attempts (
    state_hash CHAR(64) NOT NULL,
    cliente_id INT NOT NULL,
    request_id VARCHAR(80) NOT NULL,
    finish_json JSON NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    PRIMARY KEY (state_hash),
    INDEX idx_meta_embedded_signup_cliente (cliente_id, expires_at, used_at),
    INDEX idx_meta_embedded_signup_request (request_id)
);
