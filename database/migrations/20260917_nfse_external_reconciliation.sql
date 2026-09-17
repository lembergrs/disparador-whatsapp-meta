-- Protege a reconciliação de NFS-e emitida externamente contra chave duplicada.
-- MariaDB permite múltiplos NULL em índice UNIQUE, preservando tentativas ainda sem nota autorizada.
ALTER TABLE nfse_emissoes
    ADD UNIQUE KEY uk_nfse_chave_acesso_unica (NFE_ChaveAcesso);
