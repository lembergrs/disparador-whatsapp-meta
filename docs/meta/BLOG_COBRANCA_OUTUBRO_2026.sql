-- PROPOSTA PARA REVISÃO. NÃO EXECUTAR DIRETAMENTE EM PRODUÇÃO.
-- Artigo local identificado em 02/09/2026:
-- ID 2 / quanto-custa-api-oficial-whatsapp-business
-- Faça backup e valide o conteúdo antes de aplicar em um banco autorizado.

START TRANSACTION;

SELECT ART_ID, ART_Status, ART_Titulo, ART_Slug, ART_Resumo,
       ART_MetaTitle, ART_MetaDescription
FROM artigos
WHERE ART_ID = 2
  AND ART_Slug = 'quanto-custa-api-oficial-whatsapp-business';

UPDATE artigos
SET ART_Conteudo = REPLACE(
    REPLACE(
        REPLACE(
            REPLACE(
                REPLACE(
                    REPLACE(
                        ART_Conteudo,
                        '<h2><br></h2>',
                        ''
                    ),
                    'mensagens elegíveis entregues',
                    'mensagens entregues conforme as regras aplicáveis'
                ),
                '<p>Em 2026, a cobrança da Meta é feita principalmente por <strong>mensagem de template entregue</strong>. Isso significa que não basta considerar apenas o número de contatos ou de campanhas: é necessário entender quais mensagens são cobradas, quais podem ser gratuitas e quais outros serviços fazem parte da estrutura.</p>',
                '<p>Em 2026, a Meta utiliza cobrança por <strong>mensagem entregue, conforme a categoria e as regras aplicáveis</strong>. A partir de 1º de outubro, mensagens de Serviço também passam a integrar esse modelo. Por isso, não basta considerar apenas o número de contatos ou campanhas: é necessário avaliar a categoria, o mercado do destinatário, eventuais faixas de volume, franquias e janelas gratuitas aplicáveis.</p>'
            ),
            '<p>Mensagens de utilidade enviadas dentro de uma janela de atendimento aberta pelo cliente podem não gerar cobrança da Meta.</p>',
            '<p>Até 30 de setembro de 2026, mensagens de Utilidade enviadas dentro de uma janela de atendimento aberta pelo cliente podem não gerar cobrança da Meta. A partir de 1º de outubro de 2026, mensagens de Utilidade passam a ser cobradas por mensagem tanto dentro quanto fora dessa janela, conforme a tarifa e eventuais faixas de volume aplicáveis.</p>'
        ),
        '<p>A partir de 1º de outubro de 2026, a Meta passará a cobrar também pelas mensagens de Serviço entregues durante a janela de atendimento de 24 horas, conforme as tarifas aplicáveis ao mercado do destinatário.</p>',
        '<p>A partir de 1º de outubro de 2026, cada número comercial terá uma franquia mensal de <strong>1.000 mensagens de Serviço sem tarifa da Meta</strong>. A cobrança da Meta começa na 1.001ª mensagem de Serviço do mês. A franquia vale somente para Serviço, é renovada mensalmente, não se acumula e não se aplica a templates de Utilidade.</p>'
    ),
    '<p>Durante uma janela aberta pelo cliente, a empresa pode responder com maior flexibilidade e, em determinadas situações, sem tarifas da Meta.</p>',
    '<p>Durante uma janela aberta pelo cliente, a empresa pode responder com maior flexibilidade. Até 30 de setembro de 2026, mensagens de Serviço e templates de Utilidade enviados nas condições aplicáveis podem ser gratuitos. A partir de 1º de outubro de 2026, essas mensagens passam a seguir o modelo de cobrança por mensagem, observadas as franquias, tarifas e exceções oficiais, como o Free Entry Point de 72 horas.</p>'
),
ART_AtualizadoEm = CURRENT_TIMESTAMP
WHERE ART_ID = 2
  AND ART_Slug = 'quanto-custa-api-oficial-whatsapp-business';

-- Confira o resultado completo. Troque ROLLBACK por COMMIT somente após aprovação editorial.
SELECT ART_ID, ART_Titulo, ART_Resumo, ART_Conteudo,
       ART_MetaTitle, ART_MetaDescription, ART_AtualizadoEm
FROM artigos
WHERE ART_ID = 2
  AND ART_Slug = 'quanto-custa-api-oficial-whatsapp-business';

ROLLBACK;
