# Conversões essenciais de cadastro no GA4

## Instrumentação existente

O projeto carrega um único container do Google Tag Manager pela partial `app/Views/partials/google_tag_manager.php`. Não existe instalação direta de `gtag.js` nem chamada direta a `gtag()`. Eventos frontend passam por `window.Disparador.analytics.push()` e eventos confirmados no backend usam `AnalyticsService`, cuja fila de sessão é consumida uma única vez na renderização seguinte. O Measurement ID público permanece configurado no ambiente e associado ao GA4 pelo container GTM; esta feature não instala outra tag.

## Eventos desta etapa

`inicio_cadastro` representa a primeira interação real (`input` ou `change`) com o formulário público. Visualizar ou recarregar a página não dispara o evento. Um marcador em `sessionStorage` impede nova emissão após reload ou retorno ao formulário na mesma aba. O evento legado `sign_up_start` permanece no mesmo ponto para não interromper relatórios existentes.

`cadastro_concluido` é enfileirado somente depois do `commit` que cria cliente e usuário. Validações, duplicidade e falhas de banco seguem para o tratamento de erro antes desse ponto e não emitem a conversão. A fila backend é consumida e removida no próximo HTML, portanto atualizar a tela seguinte não repete o evento. O evento legado `sign_up` permanece ativo.

Os dois novos eventos são enviados sem parâmetros. Nenhum nome, telefone, e-mail, CPF/CNPJ, token, identificador Meta, mensagem ou dado financeiro é enviado.

## UTM e atribuição

Os CTAs navegam no mesmo domínio e na mesma sessão do browser. Parâmetros como `utm_source=linkedin`, `utm_medium=organic_social` e `utm_campaign=coexistence` são coletados pelo GA4 na entrada e a atribuição da sessão continua disponível durante a navegação para o formulário e o redirect de sucesso. Não foi encontrada evidência de perda de sessão que justificasse persistir UTMs no banco ou criar atribuição própria.

## Diagnóstico de `/index.php`

A aplicação usa `index.php?url=...` como front controller em vários links e redirects. Por isso, sem normalização de page path no GTM/GA4, diversas rotas podem aparecer agregadas como `/index.php`, embora representem telas diferentes. O código renderiza apenas uma partial GTM por documento e não foi encontrada duplicação da tag base. Volume adicional pode vir de reloads, testes e navegação pelo Tag Assistant. Alterar pageviews ou URLs está fora desta branch.

Referências como `tagassistant.google.com` normalmente indicam sessões de Preview/Debug do GTM. Tráfego técnico da Hostinger pode vir de monitoramento, health checks, painel ou testes de infraestrutura. Deve-se confirmar IPs e user agents nos logs antes de criar filtros. Tráfego interno conhecido pode ser definido e excluído no GA4; sessões de desenvolvimento devem usar DebugView ou uma propriedade separada. Não foi implementado filtro arriscado no código.

## Validação e configuração manual

Após o deploy, validar `inicio_cadastro` e `cadastro_concluido` no Preview do GTM, `dataLayer`, DebugView e Tempo Real do GA4. O container deve encaminhar os nomes exatamente como recebidos. Marcar `cadastro_concluido` como evento principal/conversão. Manter `inicio_cadastro` como evento de funil nesta etapa. Nenhuma tag ou campanha do Google Ads foi alterada; uma eventual importação da conversão deve ser avaliada depois.

Eventos futuros, fora deste escopo: conexão Meta, início de trial e assinatura concluída.
