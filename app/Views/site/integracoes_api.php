<?php
$mensagemComercial = 'Olá, tudo bem? Gostaria de conhecer a Partner API do Disparador.net e solicitar uma proposta de integração.';
$whatsappComercial = !empty($whatsappSite['ativo']) && !empty($whatsappSite['telefone'])
    ? 'https://wa.me/' . rawurlencode($whatsappSite['telefone']) . '?text=' . rawurlencode($mensagemComercial)
    : null;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <?php $googleTagManagerSection = 'head'; require __DIR__ . '/../partials/google_tag_manager.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>API Oficial do WhatsApp para Sistemas e Desenvolvedores | Disparador.net</title>
    <meta name="description" content="Integre seu ERP, CRM ou sistema à API do Disparador.net para enviar mensagens pelo WhatsApp Business, consultar templates e receber webhooks. Solicite uma proposta personalizada.">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://disparador.net/site/integracoesApi">
    <link rel="icon" type="image/x-icon" href="<?= ASSET_URL; ?>/img/favicon.ico?v=1">
    <meta name="theme-color" content="#08a63f">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="pt_BR">
    <meta property="og:site_name" content="Disparador.net">
    <meta property="og:title" content="API do WhatsApp para Sistemas e Desenvolvedores | Disparador.net">
    <meta property="og:description" content="Conecte seu sistema ao WhatsApp Business com API Key, templates e webhooks. Propostas personalizadas.">
    <meta property="og:url" content="https://disparador.net/site/integracoesApi">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="<?= ASSET_URL; ?>/css/style.css?v=14">
    <style>
        body{background:#fff;color:#193044;padding-top:74px}
        .api-hero{background:linear-gradient(120deg,#f0fff6,#f5faff);padding:5.5rem 0 4.5rem}
        .api-kicker{letter-spacing:.08em;font-weight:700;color:#12844b;font-size:.85rem}
        .api-hero h1{font-size:clamp(2rem,4.4vw,3.4rem);font-weight:750;line-height:1.15}
        .api-card{border:1px solid #e4ebe8;border-radius:16px;padding:1.6rem;height:100%;background:#fff}
        .api-card .fas{color:#119c54;font-size:1.6rem;margin-bottom:1rem}
        .api-step{border-left:3px solid #159e59;padding-left:1.25rem;margin-bottom:1.8rem}
        .api-cta{background:#173a30;color:#fff;border-radius:20px;padding:2.8rem}
        .api-cta p{color:#dcebe4}
        .api-nav{background:#fff}
        @media(max-width:767px){.api-hero{padding:3.5rem 0}.api-cta{padding:2rem}}
    </style>
</head>
<body>
<?php $googleTagManagerSection = 'body'; require __DIR__ . '/../partials/google_tag_manager.php'; ?>
<nav class="navbar navbar-expand-lg navbar-light fixed-top shadow-sm api-nav">
    <div class="container">
        <a class="navbar-brand" href="<?= BASE_URL; ?>/"><img src="<?= ASSET_URL; ?>/img/logo-disparador.png" alt="Disparador.net" class="site-logo" width="1136" height="247"></a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#apiMenu" aria-controls="apiMenu" aria-expanded="false" aria-label="Abrir menu"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="apiMenu">
            <ul class="navbar-nav ml-auto align-items-lg-center">
                <li class="nav-item"><a class="nav-link" href="#funcionalidades">Funcionalidades</a></li>
                <li class="nav-item"><a class="nav-link" href="#como-funciona">Como funciona</a></li>
                <li class="nav-item"><a class="nav-link" href="#perguntas">Perguntas frequentes</a></li>
                <li class="nav-item ml-lg-3"><a class="btn btn-outline-success" href="<?= BASE_URL; ?>/">Voltar ao site</a></li>
            </ul>
        </div>
    </div>
</nav>
<header class="api-hero">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-9">
                <p class="api-kicker">PARTNER API · INTEGRAÇÕES PARA EMPRESAS</p>
                <h1>Integre o WhatsApp Oficial ao seu sistema com o Disparador.net</h1>
                <p class="lead text-muted mt-4">Adicione comunicação pelo WhatsApp Business ao seu ERP, CRM ou plataforma. Utilize uma API para enviar mensagens, consultar templates aprovados e acompanhar eventos de entrega e recebimento.</p>
                <div class="mt-4">
                    <a class="btn btn-success btn-lg mr-2 mb-2" href="#proposta">Solicitar proposta de integração</a>
                    <a class="btn btn-outline-success btn-lg mb-2" href="<?= BASE_URL; ?>/index.php?url=site/partnerApi">Documentação técnica</a>
                </div>
                <p class="small text-muted mt-3 mb-0">Condições personalizadas conforme o projeto. Integração sujeita à análise e homologação.</p>
            </div>
        </div>
    </div>
</header>
<main>
<section id="funcionalidades" class="py-5">
    <div class="container">
        <h2 class="font-weight-bold mb-2">Recursos para integrar sua operação</h2>
        <p class="text-muted mb-4">Infraestrutura de integração com a WhatsApp Business Platform da Meta.</p>
        <div class="row">
            <div class="col-md-6 col-lg-4 mb-4"><div class="api-card"><i class="fas fa-paper-plane" aria-hidden="true"></i><h3 class="h5">Envio de mensagens</h3><p class="text-muted mb-0">Envie templates aprovados e mensagens de atendimento, respeitando as janelas e políticas da Meta.</p></div></div>
            <div class="col-md-6 col-lg-4 mb-4"><div class="api-card"><i class="fas fa-code" aria-hidden="true"></i><h3 class="h5">API REST e autenticação</h3><p class="text-muted mb-0">Integração HTTP com autenticação por API Key e identificação de clientes e canais autorizados.</p></div></div>
            <div class="col-md-6 col-lg-4 mb-4"><div class="api-card"><i class="fas fa-bell" aria-hidden="true"></i><h3 class="h5">Webhooks de eventos</h3><p class="text-muted mb-0">Receba notificações de mensagens e status com assinatura HMAC para verificação de origem.</p></div></div>
            <div class="col-md-6 col-lg-4 mb-4"><div class="api-card"><i class="fas fa-layer-group" aria-hidden="true"></i><h3 class="h5">Templates aprovados</h3><p class="text-muted mb-0">Consulte os templates disponíveis para cada cliente e canal integrado.</p></div></div>
            <div class="col-md-6 col-lg-4 mb-4"><div class="api-card"><i class="fas fa-users-cog" aria-hidden="true"></i><h3 class="h5">Gestão de clientes</h3><p class="text-muted mb-0">Estrutura Partner com permissões de acesso e separação entre contas de clientes.</p></div></div>
            <div class="col-md-6 col-lg-4 mb-4"><div class="api-card"><i class="fas fa-shield-alt" aria-hidden="true"></i><h3 class="h5">Controle e rastreabilidade</h3><p class="text-muted mb-0">Limites de requisições, idempotência de envios e registros operacionais para integração.</p></div></div>
        </div>
        <p class="small text-muted">Funcionalidades específicas, como vídeo e localização, dependem de validação técnica e homologação para cada cenário.</p>
    </div>
</section>
<section id="como-funciona" class="py-5 bg-light">
    <div class="container">
        <h2 class="font-weight-bold mb-4">Como começar</h2>
        <div class="row">
            <div class="col-md-4"><div class="api-step"><h3 class="h5">1. Conte sobre seu projeto</h3><p class="text-muted">Informe seu tipo de sistema, volume estimado e necessidades de integração.</p></div></div>
            <div class="col-md-4"><div class="api-step"><h3 class="h5">2. Avaliação e proposta</h3><p class="text-muted">Analisamos os requisitos e apresentamos condições comerciais personalizadas.</p></div></div>
            <div class="col-md-4"><div class="api-step"><h3 class="h5">3. Integração e homologação</h3><p class="text-muted">Após alinhamento, configuramos o acesso e orientamos os testes antes do uso em produção.</p></div></div>
        </div>
    </div>
</section>
<section id="perguntas" class="py-5">
    <div class="container">
        <h2 class="font-weight-bold mb-4">Perguntas frequentes</h2>
        <div class="row">
            <div class="col-md-6 mb-4"><h3 class="h5">Preciso ter uma conta na Meta?</h3><p class="text-muted">A operação utiliza contas e canais da WhatsApp Business Platform, sujeitos às regras de elegibilidade e conexão da Meta.</p></div>
            <div class="col-md-6 mb-4"><h3 class="h5">A API possui preço fixo?</h3><p class="text-muted">Não divulgamos um preço único para integrações. A proposta considera o cenário e as necessidades do parceiro.</p></div>
            <div class="col-md-6 mb-4"><h3 class="h5">Posso integrar vários clientes?</h3><p class="text-muted">A estrutura Partner prevê vínculos com clientes e canais autorizados. O escopo será definido na avaliação comercial e técnica.</p></div>
            <div class="col-md-6 mb-4"><h3 class="h5">As tarifas da Meta estão incluídas?</h3><p class="text-muted">As tarifas aplicáveis da Meta são distintas das condições comerciais do Disparador.net e serão esclarecidas na proposta.</p></div>
        </div>
    </div>
</section>
<section id="proposta" class="py-5">
    <div class="container">
        <div class="api-cta text-center">
            <h2 class="font-weight-bold">Vamos conversar sobre sua integração?</h2>
            <p class="lead">Conte o que seu sistema precisa fazer e solicite uma proposta personalizada.</p>
            <?php if($whatsappComercial){ ?>
                <a class="btn btn-success btn-lg" href="<?= htmlspecialchars($whatsappComercial, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" data-analytics-event="click_whatsapp" data-analytics-location="partner_api_commercial"><i class="fab fa-whatsapp" aria-hidden="true"></i> Solicitar proposta pelo WhatsApp</a>
            <?php }else{ ?>
                <a class="btn btn-success btn-lg" href="mailto:contato@disparador.net?subject=Proposta%20Partner%20API">Solicitar proposta por e-mail</a>
            <?php } ?>
            <p class="small mt-3 mb-0">Já é desenvolvedor? <a class="text-white font-weight-bold" href="<?= BASE_URL; ?>/index.php?url=site/partnerApi">Consulte a documentação da API v1</a>.</p>
        </div>
    </div>
</section>
</main>
<footer class="bg-dark text-white py-4"><div class="container"><div class="row"><div class="col-md-8 mb-2">© 2026 RL2 Net · Disparador.net. Plataforma independente que utiliza a API oficial do WhatsApp Business.</div><div class="col-md-4 text-md-right"><a class="text-white" href="<?= BASE_URL; ?>/index.php?url=site/politicaPrivacidade">Política de Privacidade</a></div></div></div></footer>
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
