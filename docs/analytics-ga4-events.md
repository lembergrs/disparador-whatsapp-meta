# Eventos GA4 do funil de aquisição

## Arquitetura auditada

O projeto carrega apenas o Google Tag Manager pela partial global `google_tag_manager.php`. Essa partial disponibiliza `window.Disparador.analytics.push(evento, parametros)`, que encaminha eventos ao `dataLayer`, e mantém um único listener delegado para elementos com `data-analytics-event`. Não há instalação direta de `gtag.js` nem chamadas a `gtag()` nas telas.

Sucessos confirmados no backend usam `AnalyticsService`: a service aceita somente eventos e parâmetros em uma lista permitida, deduplica registros equivalentes na sessão e entrega a fila uma única vez na próxima renderização HTML. Eventos já existentes antes desta ampliação eram cadastro concluído, login, início do trial, conexão Meta, início do Embedded Signup, CTAs públicos, WhatsApp institucional e visualização de artigo. Faltavam contexto da home e preços, nomenclatura uniforme do funil, primeira campanha e uma estratégia segura para `purchase`.

## Catálogo

| Evento | Momento do disparo | Origem | Parâmetros | Condição de sucesso | Conversão sugerida |
|---|---|---|---|---|---|
| `view_home` | `DOMContentLoaded` da landing principal | site público | `page_type: home`, `source_area: public_site` | uma vez por carregamento | Não |
| `view_pricing` | 35% da seção de planos visível | site público | `page_type: home`, `section: pricing` | `IntersectionObserver`, uma vez por carregamento | Não |
| `select_trial` | clique em CTA que leva ao cadastro | header, hero, preços, CTA final ou blog | `cta_location`, `destination_type`, `plan_name` somente em plano | listener delegado; não altera navegação | Secundária |
| `sign_up_start` | primeira alteração real no formulário público | cadastro | `form_name: public_registration`, `source_area: public_site` | listeners removidos após o primeiro evento | Não |
| `sign_up` | após commit de cliente e usuário | backend do cadastro | `method: public_form`, `account_type: client` | somente criação confirmada | Principal |
| `login` | após autenticação e criação da sessão | backend do login | `method: password` | somente senha válida | Complementar |
| `start_trial` | preenchimento inédito de `CLI_DataLiberacao` | backend da conexão Meta | `trial_duration_days: 7`, `trial_message_limit: 200`, `trigger: meta_connection` | `UPDATE` persistido com `rowCount() > 0` | Principal |
| `connect_meta` | primeira transição persistida para conectado | configuração | `connection_type: embedded_signup`, `first_connection: true`, `source_area: configuration` | conta operacional completa e atualização confirmada | Secundária |
| `first_campaign_created` | após salvar campanha, variáveis, fila e total | campanhas | `campaign_type: scheduled`, `first_campaign: true` | total de campanhas do cliente igual a um | Secundária |
| `purchase` | ainda não emitido | financeiro | `transaction_id`, `value`, `currency: BRL`, `items` | exige entrega associada ao navegador depois de pagamento confirmado | Financeira, quando ativado |

Campanhas representam o fluxo agendado persistido em `campanhas`. O disparo manual usa uma fila operacional distinta e não é considerado `first_campaign_created`.

## Purchase

O Asaas confirma pagamentos automaticamente pelo webhook e o workflow só aplica o estado `pago` para `PAYMENT_RECEIVED` ou `PAYMENT_CONFIRMED`. Entretanto, o webhook é servidor a servidor e não compartilha a sessão do navegador do cliente. Emitir `purchase` pela fila de sessão do webhook atribuiria o evento à sessão errada; por isso nenhum `purchase` é simulado nesta entrega.

`AnalyticsService::prepararPurchase()` prepara e valida o payload permitido. A integração futura deve persistir um marcador idempotente associado à cobrança paga e consumi-lo em uma página autenticada do cliente, ou adotar uma integração server-side oficialmente aprovada. Eventos pendentes, recusados, vencidos, cancelados e reembolsados não podem gerar `purchase`.

## Privacidade e deduplicação

- A política do backend descarta qualquer parâmetro fora da lista permitida.
- Nenhum evento envia nome de cliente, e-mail, telefone, CPF/CNPJ, endereço, conteúdo, contato, WABA ID, Phone Number ID, token ou credencial.
- Eventos backend são deduplicados por hash e consumidos uma vez.
- Conexão Meta exige transição de estado; trial exige atualização inédita; primeira campanha exige contagem igual a um; preços desconectam o observer após o primeiro disparo.

## Configuração no GA4 e Google Ads

1. Publique no GTM uma tag de evento GA4 que leia o nome e os parâmetros enviados ao `dataLayer`, sem adicionar outra tag base.
2. No GA4, marque `sign_up` e/ou `start_trial` como eventos principais.
3. Mantenha `select_trial`, `connect_meta` e `first_campaign_created` como conversões secundárias para diagnóstico do funil.
4. Ative `purchase` como conversão financeira somente depois de concluir o mecanismo idempotente descrito acima.
5. Vincule GA4 e Google Ads e importe apenas os eventos principais desejados, respeitando a janela de atribuição definida pela operação.
