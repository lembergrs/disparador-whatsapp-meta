# Atualização da cobrança da Meta em outubro de 2026

## Motivo e vigência

Este documento registra a atualização da comunicação pública do Disparador.net sobre a mudança anunciada pela Meta para a cobrança da WhatsApp Business Platform, com vigência em **01/10/2026**. O objetivo é dar transparência e previsibilidade, sem sugerir uma alteração nos preços ou nas regras de uso do Disparador.net.

## Situação anterior e mudança anunciada

Até **30/09/2026**, mensagens de Serviço enviadas pela empresa durante a janela de atendimento de 24 horas e templates de Utilidade enviados nessa janela possuem tratamento gratuito segundo a política atual aplicável.

A partir de **01/10/2026**, conforme a mudança anunciada pela Meta:

- mensagens de Serviço enviadas pela empresa passam a integrar o modelo de cobrança por mensagem, observadas as condições e eventuais franquias oficiais aplicáveis;
- templates de Utilidade enviados dentro da janela de atendimento de 24 horas também passam a ser cobrados;
- mensagens de Marketing e Autenticação continuam sujeitas às respectivas tarifas;
- mensagens recebidas do cliente não são apresentadas como mensagens cobradas pelo Disparador.net; e
- a janela gratuita de 72 horas do Free Entry Point permanece aplicável, quando originada por anúncio que direciona ao WhatsApp ou botão de chamada para ação elegível; e
- outras exceções e janelas gratuitas permanecem sujeitas às regras oficiais vigentes da Meta.

## Impacto na comunicação pública

A landing page foi atualizada nos seguintes pontos:

- observação próxima aos planos para esclarecer que a franquia corresponde ao uso do Disparador.net;
- seção de tarifas para distinguir a mensalidade e a franquia do Disparador das cobranças próprias da Meta;
- aviso visual, discreto e temporal sobre a vigência em 1º de outubro de 2026; e
- resposta da FAQ sobre cobrança de mensagens, com menção a mensagens de Serviço e templates de Utilidade na janela de atendimento de 24 horas.

Os preços, descontos, nomes, franquias e CTAs dos planos não foram alterados.

## Separação das cobranças

A mensalidade remunera a utilização da plataforma Disparador.net, e a quantidade de mensagens exibida no plano representa sua franquia de utilização. As tarifas pelo uso da WhatsApp Business Platform são cobranças independentes, definidas pela Meta conforme sua política vigente. Categoria da mensagem, mercado do destinatário e eventuais faixas de volume podem influenciar os valores.

O Disparador.net não apresenta a franquia como crédito pré-pago da Meta e não comunica que revende créditos da Meta.

## Decisões de escopo

- **Nenhuma tarifa fixa da Meta foi hardcodada.** Em 02/09/2026, a página oficial pública consultada ainda não expunha no HTML o rate card futuro de outubro. Portanto, os valores de R$ 0,0350 para Utilidade, Autenticação e Serviço não foram publicados.
- **A franquia brasileira de 1.000 mensagens de Serviço por mês e por número comercial não pôde ser confirmada na documentação oficial acessível.** A comunicação não afirma cobrança desde a primeira mensagem, mas também não publica essa franquia até que uma fonte oficial seja anexada ou disponibilizada.
- A página oficial confirma o modelo por mensagem entregue, as quatro categorias, faixas de volume para Utilidade e Autenticação e a janela gratuita de 72 horas do Free Entry Point.
- **Nenhum backend foi alterado.** Esta entrega não modifica envio, consumo, banco de dados, cobrança financeira, Scheduler, worker, webhook, Central de Conversas ou campanhas.
- Regras, preços e limites do trial permanecem inalterados.
- O Programa de Indicação permanece integralmente fora do escopo.

## Revisão posterior à vigência

O aviso sobre a mudança tem **caráter temporal** e deverá ser reavaliado depois de **01/10/2026**. Nessa revisão, deve-se conferir a política oficial então vigente e decidir se o aviso continua necessário, deve ser convertido em explicação permanente ou pode ser removido, sem presumir valores fixos para tarifas da Meta.

## Fontes e divergências registradas

- Fonte oficial consultada em 02/09/2026: `https://business.whatsapp.com/products/platform-pricing` (redireciona para `https://whatsappbusiness.com/products/platform-pricing/`).
- A página oficial ainda descreve Serviço e Utilidade na janela de atendimento como gratuitos, coerentemente com a regra vigente até 30/09/2026, e não apresenta no conteúdo acessível a franquia brasileira futura de 1.000 mensagens de Serviço.
- A premissa recebida para esta auditoria informa a franquia mensal de 1.000 mensagens de Serviço por número comercial e tarifa-base de R$ 0,0350 após a franquia. Como esses dois dados não foram confirmados na fonte oficial acessível, eles permanecem pendentes e não foram publicados.
- A tarifa de Marketing não foi presumida nem alterada.
