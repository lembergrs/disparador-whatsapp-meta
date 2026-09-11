-- Cria uma lista automática para contatos sincronizados pelo WhatsApp Business
-- e corrige os contatos já existentes que ficaram sem vínculo de lista.
-- Migração idempotente.

INSERT INTO listas_contatos (
    CLI_ID,
    LST_Nome,
    LST_Descricao
)
SELECT DISTINCT
    c.CLI_ID,
    'Contatos do WhatsApp',
    'Contatos sincronizados automaticamente pelo WhatsApp Business (Coexistence).'
FROM contatos c
LEFT JOIN listas_contatos l
    ON l.CLI_ID = c.CLI_ID
    AND l.LST_Nome = 'Contatos do WhatsApp'
    AND l.LST_Ativo = 'S'
WHERE c.CON_Ativo = 'S'
AND JSON_VALID(c.CON_DadosJson) = 1
AND JSON_UNQUOTE(JSON_EXTRACT(c.CON_DadosJson, '$.origem')) = 'whatsapp_business_app'
AND l.LST_ID IS NULL;

INSERT IGNORE INTO lista_contatos_itens (
    LST_ID,
    CON_ID
)
SELECT
    (
        SELECT MIN(l2.LST_ID)
        FROM listas_contatos l2
        WHERE l2.CLI_ID = c.CLI_ID
        AND l2.LST_Nome = 'Contatos do WhatsApp'
        AND l2.LST_Ativo = 'S'
    ) AS LST_ID,
    c.CON_ID
FROM contatos c
WHERE c.CON_Ativo = 'S'
AND JSON_VALID(c.CON_DadosJson) = 1
AND JSON_UNQUOTE(JSON_EXTRACT(c.CON_DadosJson, '$.origem')) = 'whatsapp_business_app'
AND EXISTS (
    SELECT 1
    FROM listas_contatos l3
    WHERE l3.CLI_ID = c.CLI_ID
    AND l3.LST_Nome = 'Contatos do WhatsApp'
    AND l3.LST_Ativo = 'S'
);
