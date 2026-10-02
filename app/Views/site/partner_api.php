<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>Partner API v1 — Documentação de Integração | Disparador.net</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="<?= ASSET_URL; ?>/css/style.css?v=14">
    <style>
        body{background:#f6f8fa;color:#263238}.doc-wrap{max-width:1100px}.doc-card{border:0;border-radius:14px;box-shadow:0 4px 22px rgba(0,0,0,.07)}
        .doc-nav{position:sticky;top:20px}.doc-nav a{display:block;padding:.35rem 0;color:#52606d}.doc-nav a:hover{color:#198754;text-decoration:none}
        pre{background:#17212b;color:#f1f5f9;padding:1rem;border-radius:8px;white-space:pre-wrap;word-break:break-word}code{color:#b4235a}
        pre code{color:inherit}.badge-soft{background:#e8f5ee;color:#167443}.endpoint{font-family:monospace;font-size:.95rem}.section-anchor{scroll-margin-top:20px}
        @media(max-width:767.98px){.doc-nav{position:static}}
    </style>
</head>
<body>
<nav class="navbar navbar-light bg-white shadow-sm">
    <div class="container doc-wrap">
        <a class="navbar-brand" href="<?= BASE_URL; ?>/"><img class="site-logo" src="<?= ASSET_URL; ?>/img/logo-disparador.png" alt="Disparador.net" width="1136" height="247"></a>
        <span class="badge badge-soft px-3 py-2">Partner API v1</span>
    </div>
</nav>

<header class="py-5 bg-white border-bottom">
    <div class="container doc-wrap">
        <p class="text-success font-weight-bold mb-2">DOCUMENTAÇÃO PARA INTEGRADORES</p>
        <h1 class="display-4 font-weight-bold">Partner API v1</h1>
        <p class="lead text-muted mb-2">Integre seu sistema ao WhatsApp através do Disparador.net sem acesso direto às credenciais da Meta.</p>
        <p class="text-muted mb-0">Base URL: <code>https://disparador.net/index.php?url=api/v1</code></p>
    </div>
</header>

<main class="container doc-wrap py-5">
<div class="row">
<aside class="col-md-3 mb-4">
    <div class="doc-nav card doc-card p-3">
        <strong class="mb-2">Nesta página</strong>
        <a href="#acesso">1. Acesso</a><a href="#status">2. Validar acesso</a><a href="#templates">3. Templates</a>
        <a href="#mensagens">4. Mensagens</a><a href="#midia">5. Mídia</a><a href="#webhooks">6. Webhooks</a>
        <a href="#recebida">7. Mídia recebida</a><a href="#limites">8. Idempotência e limites</a>
        <a href="#erros">9. Erros</a><a href="#checklist">10. Homologação</a>
    </div>
</aside>
<article class="col-md-9">
<div class="card doc-card"><div class="card-body p-4 p-lg-5">

<section id="acesso" class="section-anchor mb-5"><h2>1. Dados de acesso</h2>
<p>Para iniciar a homologação, o integrador recebe por canal seguro uma API key Partner, os <code>client_id</code> e <code>channel_id</code> autorizados e, quando webhooks forem habilitados, o segredo individual de assinatura.</p>
<div class="alert alert-warning"><strong>Importante:</strong> API key e segredo de webhook são credenciais distintas. Não os inclua em tickets, documentação, repositórios ou logs.</div>
<p>Todos os endpoints protegidos usam <code>Authorization: Bearer &lt;API_KEY&gt;</code>. Credenciais da Meta nunca são fornecidas ao integrador.</p></section>

<section id="status" class="section-anchor mb-5"><h2>2. Validar acesso</h2>
<p><span class="badge badge-success">GET</span> <code class="endpoint">/status</code></p>
<pre><code>curl -sS "https://disparador.net/index.php?url=api/v1/status" \
  -H "Authorization: Bearer $DISPARADOR_API_KEY" \
  -H "Accept: application/json"</code></pre>
<p>HTTP 200 retorna o Partner e somente os canais autorizados àquela credencial. Guarde <code>client_id</code> e <code>channel_id</code>.</p></section>

<section id="templates" class="section-anchor mb-5"><h2>3. Consultar templates</h2>
<p><span class="badge badge-success">GET</span> <code class="endpoint">/templates?client_id=123&amp;channel_id=456</code></p>
<pre><code>curl -sS "https://disparador.net/index.php?url=api/v1/templates&amp;client_id=123&amp;channel_id=456" \
  -H "Authorization: Bearer $DISPARADOR_API_KEY" \
  -H "Accept: application/json"</code></pre>
<p>Somente templates ativos e aprovados do cliente/canal solicitado são retornados. Use o campo <code>id</code> retornado como <code>template.id</code> no envio.</p></section>

<section id="mensagens" class="section-anchor mb-5"><h2>4. Enviar mensagem</h2>
<p>Todo <code>POST /messages</code> exige <code>Idempotency-Key</code> de 8 a 120 caracteres, usando letras, números, ponto, hífen, sublinhado ou dois-pontos. Gere uma chave para cada intenção de envio.</p>
<h3 class="h5 mt-4">Texto livre</h3><p>Exige janela de atendimento de 24 horas aberta.</p>
<pre><code>curl -sS -X POST "https://disparador.net/index.php?url=api/v1/messages" \
  -H "Authorization: Bearer $DISPARADOR_API_KEY" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: msg-20261002-000001" \
  --data '{"client_id":123,"channel_id":456,"to":"5541999999999","type":"text","text":{"body":"Olá! Como posso ajudar?"}}'</code></pre>
<h3 class="h5 mt-4">Template</h3><p>Fora da janela de 24 horas, utilize um template aprovado.</p>
<pre><code>{
  "client_id": 123,
  "channel_id": 456,
  "to": "5541999999999",
  "type": "template",
  "template": {"id": 789, "variables": {"1": "Maria"}}
}</code></pre>
<p>Uma requisição aceita retorna HTTP 202 com <code>message_id</code>, <code>local_message_id</code>, <code>status: accepted</code> e <code>type</code>.</p></section>

<section id="midia" class="section-anchor mb-5"><h2>5. Enviar mídia</h2>
<p>Imagem, PDF e áudio usam duas etapas. Primeiro faça upload:</p>
<pre><code>curl -sS -X POST "https://disparador.net/index.php?url=api/v1/media" \
  -H "Authorization: Bearer $DISPARADOR_API_KEY" \
  -F "client_id=123" -F "channel_id=456" -F "type=image" -F "file=@foto.jpg"</code></pre>
<p>O HTTP 201 retorna um <code>media_id</code>. Depois utilize esse identificador em <code>POST /messages</code>.</p>
<pre><code>{
  "client_id":123, "channel_id":456, "to":"5541999999999",
  "type":"image", "image":{"media_id":"MEDIA_ID","caption":"Foto"}
}</code></pre>
<div class="table-responsive"><table class="table table-bordered"><thead class="thead-light"><tr><th>Tipo</th><th>Formatos</th><th>Limite</th></tr></thead><tbody>
<tr><td>Imagem</td><td>JPG, JPEG, PNG, WEBP</td><td>5 MB</td></tr>
<tr><td>Documento</td><td>PDF</td><td>10 MB</td></tr>
<tr><td>Áudio</td><td>AAC, AMR, MP3, M4A, OGG</td><td>16 MB</td></tr>
</tbody></table></div><p>Mídia livre também exige janela de atendimento de 24 horas.</p></section>

<section id="webhooks" class="section-anchor mb-5"><h2>6. Receber eventos por webhook</h2>
<p>Eventos disponíveis: <code>message.received</code>, <code>message.sent</code>, <code>message.delivered</code>, <code>message.read</code>, <code>message.failed</code> e <code>message.reaction</code>.</p>
<p>O endpoint deve ser HTTPS público na porta 443. Localhost, endereços privados/reservados e credenciais embutidas na URL não são aceitos. Redirects HTTP não são seguidos.</p>
<pre><code>X-Disparador-Event-Id: evt_...
X-Disparador-Timestamp: 1790956800
X-Disparador-Signature: sha256=&lt;hex&gt;</code></pre>
<p>A assinatura é <code>HMAC-SHA256(WEBHOOK_SECRET, TIMESTAMP + "." + RAW_HTTP_BODY)</code>. Compare-a em tempo constante, valide a idade do timestamp e persista o <code>event_id</code> para ignorar duplicidades.</p>
<p>Responda com HTTP 2xx somente depois de aceitar/persistir o evento. Respostas fora de 2xx são consideradas falha e podem ser reenviadas. Há até 6 tentativas com backoff.</p>
<h3 class="h5 mt-4">Coexistence e intervenção humana</h3>
<p>Ações manuais no WhatsApp Business App podem gerar <code>message.sent</code> ou <code>message.reaction</code> com <code>{"source":"business_app","human":true}</code>. Use esses campos para pausar automações ou sincronizar a conversa.</p></section>

<section id="recebida" class="section-anchor mb-5"><h2>7. Mídia recebida</h2>
<p>Eventos recebidos de áudio, imagem ou documento podem incluir <code>data.media.download_url</code>. A URL pertence ao Disparador.net e deve ser acessada com a mesma API key.</p>
<pre><code>curl -sS "$DOWNLOAD_URL" \
  -H "Authorization: Bearer $DISPARADOR_API_KEY" \
  -o arquivo</code></pre>
<p>A URL não expõe token nem URL temporária da Meta.</p></section>

<section id="limites" class="section-anchor mb-5"><h2>8. Idempotência, limites e retries</h2>
<p>Mesma <code>Idempotency-Key</code> + mesmo corpo reproduz a resposta armazenada; a mesma chave com corpo diferente retorna HTTP 409. Uma requisição ainda em processamento também retorna HTTP 409.</p>
<div class="table-responsive"><table class="table table-bordered"><thead class="thead-light"><tr><th>Operação</th><th>Limite / 60 s</th></tr></thead><tbody>
<tr><td>POST /messages</td><td>60</td></tr><tr><td>POST /media</td><td>20</td></tr><tr><td>Leituras</td><td>120</td></tr>
</tbody></table></div>
<p>Em HTTP 429, respeite <code>Retry-After</code>. Respostas autenticadas informam <code>X-RateLimit-Limit</code>, <code>X-RateLimit-Remaining</code> e <code>X-RateLimit-Reset</code>.</p>
<div class="alert alert-light border">Em timeout ou falha transitória 5xx de <code>POST /messages</code>, repita usando a mesma <code>Idempotency-Key</code> e o mesmo corpo. A idempotência reduz reenvios acidentais, mas não deve ser interpretada como garantia absoluta de exactly-once em falhas excepcionais de infraestrutura.</div></section>

<section id="erros" class="section-anchor mb-5"><h2>9. Tratamento de erros</h2>
<pre><code>{"error":{"code":"validation_error","message":"..."}}</code></pre>
<p>Códigos relevantes incluem <code>unauthorized</code>, <code>invalid_json</code>, <code>invalid_idempotency_key</code>, <code>validation_error</code>, <code>unsupported_message_type</code>, <code>channel_not_authorized</code>, <code>channel_not_ready</code>, <code>customer_care_window_closed</code>, <code>template_not_available</code>, <code>meta_send_failed</code>, <code>media_upload_failed</code>, <code>media_not_found</code>, <code>media_unavailable</code>, <code>rate_limit_exceeded</code>, <code>rate_limit_unavailable</code>, <code>payload_too_large</code>, <code>idempotency_conflict</code>, <code>request_in_progress</code>, <code>method_not_allowed</code> e <code>internal_error</code>.</p>
<p>Não faça retry cego em 4xx. Para 429, aguarde <code>Retry-After</code>.</p></section>

<section id="checklist" class="section-anchor mb-0"><h2>10. Checklist de homologação</h2>
<ol>
<li><code>GET /status</code> retorna 200 e o canal esperado.</li><li><code>GET /templates</code> retorna os templates aprovados.</li>
<li>Template é enviado e eventos de status chegam ao webhook.</li><li>Após uma mensagem recebida abrir a janela, texto livre é enviado.</li>
<li>Áudio, imagem e PDF são testados nos sentidos necessários.</li><li>HMAC é validado sobre o corpo bruto.</li>
<li>Duplicidade de <code>event_id</code> é ignorada.</li><li>Retry de <code>POST /messages</code> reutiliza a mesma Idempotency-Key.</li>
<li>HTTP 429 respeita <code>Retry-After</code>.</li><li><code>source=business_app</code> e <code>human=true</code> são tratados quando Coexistence estiver ativo.</li>
</ol>
<div class="alert alert-secondary mt-4 mb-0"><strong>Fora do contrato atual:</strong> vídeo e localização ainda não fazem parte da Partner API v1.</div></section>

</div></div>
<p class="small text-muted text-center mt-4">Partner API v1 · Disparador.net · RL2 Net · Documentação sujeita a evolução compatível com a versão da API.</p>
</article>
</div>
</main>
</body></html>
