# Google Tag Manager e infraestrutura de Analytics

## Instalação

O Google Tag Manager é renderizado pela partial única `app/Views/partials/google_tag_manager.php`:

- a seção `head` inicializa `window.dataLayer`, disponibiliza `Disparador.analytics` e carrega o container de forma assíncrona;
- a seção `body` gera o fallback `<noscript>` imediatamente após a abertura do `<body>`;
- layouts público, Blog, autenticação e master autenticado usam a mesma partial;
- páginas HTML independentes, inclusive o retorno do Embedded Signup, também reutilizam a partial.

Não há instalação direta de `gtag.js`. O Google Analytics 4 deve ser configurado no próprio container do GTM com o Measurement ID indicado pelo ambiente. Esta entrega não cria tags no painel Google, eventos personalizados ou conversões.

## Configuração por ambiente

```dotenv
GOOGLE_TAG_MANAGER_ID=GTM-5BV2SLDR
GOOGLE_ANALYTICS_MEASUREMENT_ID=G-H6JP7C3CHG
```

`GOOGLE_TAG_MANAGER_ID` controla a renderização. Para desabilitar completamente as chamadas ao GTM em um ambiente, defina-o vazio:

```dotenv
GOOGLE_TAG_MANAGER_ID=
```

Com o ID vazio ou inválido, loader e `<noscript>` não são renderizados e a aplicação continua funcionando. `GOOGLE_ANALYTICS_MEASUREMENT_ID` apenas centraliza o identificador para configuração e uso futuro; o PHP não carrega GA4 diretamente.

Após alterar `.env`, reinicie o processo PHP usado pelo ambiente caso ele mantenha configuração em memória. Não há cache adicional específico de Analytics.

## Data Layer para eventos futuros

A interface global fica disponível mesmo quando o GTM está desabilitado:

```javascript
window.Disparador.analytics.push('nome_do_evento', {
    propriedade: 'valor'
});
```

Ela adiciona ao `dataLayer` um objeto equivalente a:

```javascript
{
    event: 'nome_do_evento',
    propriedade: 'valor'
}
```

Código futuro deve usar essa interface, sem chamar `gtag()` ou APIs do Google diretamente. Os exemplos acima são apenas documentação; nenhum evento é disparado nesta branch.

## Validação

Em homologação:

1. confirme no HTML um único loader `gtm.js` dentro do `<head>`;
2. confirme um único iframe `ns.html` imediatamente depois do `<body>`;
3. valide Home, Blog, login, cadastro e uma página autenticada;
4. use o modo Preview do GTM para confirmar o container correto;
5. esvazie temporariamente `GOOGLE_TAG_MANAGER_ID` e confirme que não há requisições a `googletagmanager.com`;
6. no console, confirme que `window.Disparador.analytics.push` existe sem disparar eventos de produção.
