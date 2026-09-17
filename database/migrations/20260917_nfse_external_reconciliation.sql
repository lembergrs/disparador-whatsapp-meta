-- Protege a reconciliação de NFS-e emitida externamente contra chave duplicada.
-- MariaDB permite múltiplos NULL em índice UNIQUE, preservando tentativas ainda sem nota autorizada.
-- O índice simples anterior se torna redundante após a criação do UNIQUE.
ALTER TABLE nfse_emissoes
    DROP INDEX idx_nfse_chave_acesso,
    ADD UNIQUE KEY uk_nfse_chave_acesso_unica (NFE_ChaveAcesso);
