# Funil de conversão GA4

## Arquitetura

A instrumentação reutiliza exclusivamente a partial global do Google Tag Manager e `window.Disparador.analytics.push(evento, dados)`. Nenhuma tela chama `gtag()`, nenhum script adicional foi instalado e nenhuma tag ou conversão foi criada no GA4/GTM nesta entrega.

Eventos confirmados pelo backend são colocados por `AnalyticsService` em uma fila curta na sessão e consumidos uma única vez pela partial na próxima página HTML. A service aceita somente eventos e parâmetros explicitamente permitidos, evitando inclusão acidental de dados pessoais.

Cliques usam atributos `data-analytics-event` e `data-analytics-location`, atendidos por um único listener delegado global. Fluxos específicos, como formulário e Embedded Signup, usam guardas locais para impedir múltiplos disparos.

## Eventos implementados

| Evento | Momento | Parâmetros |
|---|---|---|
| `view_blog_post` | Abertura de artigo público | `article_slug`, `article_title`, `article_category`, `article_author`, `article_reading_time` |
| `click_start_trial` | Clique em qualquer CTA que abre o cadastro | `location`: `menu`, `hero`, `plans`, `footer`, `blog_menu` ou `blog_cta` |
| `begin_signup` | Primeira interação real com campo do formulário | nenhum |
| `sign_up` | Depois do commit do cliente e usuário | `signup_method: site` |
| `login` | Depois de senha válida e criação da sessão autenticada | `login_method: password` |
| `begin_meta_connection` | Depois que o backend autoriza uma tentativa de Embedded Signup | nenhum |
| `meta_connection_completed` | Primeira transição persistida da conta para `conectado` | nenhum |
| `trial_started` | Quando o update preenche `CLI_DataLiberacao` pela primeira vez | nenhum |
| `click_whatsapp` | Clique no botão institucional | `location`: `landing`, `blog` ou `floating_button` |

O tempo de leitura do Blog é derivado do conteúdo já carregado, considerando 220 palavras por minuto, sem consulta adicional. Preview administrativo não dispara `view_blog_post`.

## Fluxo principal

```text
view_blog_post
  → click_start_trial
  → begin_signup
  → sign_up
  → login
  → begin_meta_connection
  → meta_connection_completed
  → trial_started
```

`click_whatsapp` é paralelo e pode ocorrer nas páginas públicas em que o botão institucional estiver habilitado.

## LGPD

A camada não envia nome de cliente, e-mail, telefone, CPF/CNPJ, conteúdo digitado, mensagem, IDs internos, Meta ID, WABA, Phone Number ID ou token. Título, categoria, autor editorial e slug públicos do artigo são metadados públicos de conteúdo; os demais parâmetros são apenas comportamentais.

## Limitações e operação

- Tags, conversões, públicos e dashboards devem ser configurados separadamente no GTM/GA4.
- Eventos backend dependem da próxima renderização HTML da mesma sessão para chegar ao Data Layer.
- Eventos bloqueados pelo navegador, consentimento, extensões ou configuração do container não são reenviados pela aplicação.
- `search_blog` e `contact_form_submit` não foram incluídos porque esta branch está limitada ao funil solicitado.

Para um evento futuro, inclua-o na política de parâmetros quando for backend ou use a camada global no frontend:

```javascript
window.Disparador.analytics.push('evento_futuro', {
    parametro_comportamental: 'valor'
});
```
