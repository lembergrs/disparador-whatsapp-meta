-- Classificação dos cadastros envolvidos na Partner API.
-- O vínculo autorizativo continua em parceiro_clientes; estes campos identificam claramente o papel do cadastro no Disparador.

ALTER TABLE clientes
    ADD COLUMN CLI_TipoConta ENUM('cliente','cliente_partner','cliente_partner_vinculado')
        NOT NULL DEFAULT 'cliente' AFTER CLI_ID;

ALTER TABLE parceiros_api
    ADD COLUMN CLI_ID INT NOT NULL AFTER PAR_ID,
    ADD UNIQUE KEY UK_parceiros_api_cliente (CLI_ID),
    ADD CONSTRAINT FK_parceiros_api_cliente
        FOREIGN KEY (CLI_ID) REFERENCES clientes (CLI_ID);

-- O perfil de login do parceiro será tratado em migration própria junto da tela/permissões.
-- Não alteramos USU_Nivel aqui para não reescrever a definição atual da coluna sem necessidade.
