<?php
$perguntasFrequentes = [
    'Posso continuar usando meu WhatsApp Business no celular?' => 'Quando o número for elegível e essa opção for apresentada pela Meta, ele pode ser conectado ao Disparador.net e continuar sendo usado no aplicativo WhatsApp Business.',
    'Preciso trocar meu número?' => 'Não necessariamente. Você pode conectar um número novo ou, quando elegível, utilizar o número que sua empresa já usa no WhatsApp Business.',
    'Posso conectar um número novo?' => 'Sim. O processo de conexão permite cadastrar um novo número para operar pela plataforma oficial.',
    'Minha equipe pode atender pelo mesmo número?' => 'Sim. A central de conversas permite organizar o atendimento da equipe pelos números conectados ao Disparador.net.',
    'Meus contatos do WhatsApp Business podem aparecer no Disparador?' => 'Quando a Meta disponibiliza esses dados na conexão pelo modo Coexistence, os contatos do WhatsApp Business podem ser sincronizados automaticamente com o Disparador.net. A disponibilidade depende dos dados e eventos fornecidos pela Meta.',
    'Preciso deixar um celular ou computador ligado?' => 'O Disparador.net opera em nuvem. Números elegíveis que continuam no WhatsApp Business podem seguir usando o aplicativo, sem que ele seja o responsável por manter a plataforma conectada.',
    'Posso enviar campanhas para meus clientes?' => 'Sim, para contatos que autorizaram a comunicação, utilizando templates aprovados e respeitando as políticas aplicáveis da Meta.',
    'Existe período de teste?' => 'Sim. O teste grátis é de até 7 dias ou 200 mensagens, o que ocorrer primeiro, e começa após a validação da primeira conexão.',
    'O Disparador.net utiliza a API Oficial do WhatsApp?' => 'Sim. A integração utiliza a WhatsApp Business Platform, e a conta e o número passam pelos processos de conexão e validação da Meta.',
    'Quando começa o período de avaliação?' => 'O período de avaliação começa somente após a validação da primeira conexão do WhatsApp Business.',
    'Preciso contratar um plano antes de conectar o primeiro número?' => 'Não. O cliente elegível ao pré-trial pode conectar o primeiro número para iniciar a avaliação.',
    'Quem define os limites de envio?' => 'Os limites de envio são definidos e administrados pela Meta conforme os critérios aplicáveis à conta e ao número.',
    'O plano do Disparador aumenta automaticamente meu limite na Meta?' => 'Não. O limite do plano do Disparador.net e as faixas administradas pela Meta são capacidades diferentes.',
    'A Meta cobra pelas mensagens?' => 'A Meta cobra determinadas mensagens entregues pela WhatsApp Business Platform. A partir de 1º de outubro de 2026, cada número comercial terá uma franquia mensal de 1.000 mensagens de Serviço sem tarifa da Meta; a cobrança da Meta começa na 1.001ª mensagem de Serviço do mês. Templates de Utilidade enviados dentro da janela de 24 horas também passam a ser cobrados por mensagem e não utilizam essa franquia. Categoria, mercado do destinatário e faixas de volume podem alterar o valor. Essas tarifas são independentes da mensalidade e da franquia do Disparador.net.',
    'Posso usar qualquer mensagem em uma campanha?' => 'Mensagens iniciadas pela empresa normalmente dependem de templates aprovados e do cumprimento das políticas aplicáveis da Meta.',
    'O teste grátis possui limite?' => 'Sim. O teste grátis é de até 7 dias ou 200 mensagens, o que ocorrer primeiro.'
];

$faqSchema = [];
foreach($perguntasFrequentes as $pergunta => $resposta){
    $faqSchema[] = [
        '@type' => 'Question',
        'name' => $pergunta,
        'acceptedAnswer' => [
            '@type' => 'Answer',
            'text' => $resposta
        ]
    ];
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <?php $googleTagManagerSection = 'head'; require __DIR__ . '/../partials/google_tag_manager.php'; ?>
    <meta charset="UTF-8">

    <title>Disparador.net | Plataforma Oficial de WhatsApp Business da Meta</title>

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="icon" type="image/x-icon" href="<?= ASSET_URL ?>/img/favicon.ico?v=1">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= ASSET_URL ?>/img/favicon.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= ASSET_URL ?>/img/favicon.png">
    <meta name="theme-color" content="#08a63f">

    <meta
    name="description"
    content="Envie campanhas, notificações e mensagens pela API Oficial do WhatsApp Business da Meta. Gerencie contatos, templates, campanhas e conversas no Disparador.net."
    >
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
    <meta name="author" content="Disparador.net">
    <meta name="application-name" content="Disparador.net">
    <link rel="canonical" href="https://disparador.net/">

    <meta property="og:locale" content="pt_BR">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Disparador.net">
    <meta property="og:title" content="Disparador.net | Plataforma Oficial de WhatsApp Business da Meta">
    <meta
    property="og:description"
    content="Campanhas, notificações, templates e atendimento pela API Oficial do WhatsApp Business da Meta."
    >
    <meta property="og:url" content="https://disparador.net/">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Disparador.net | Plataforma Oficial de WhatsApp Business da Meta">
    <meta name="twitter:description" content="Campanhas, notificações, templates e atendimento pela API Oficial do WhatsApp Business da Meta.">

    <script type="application/ld+json"><?= json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => 'Disparador.net',
        'url' => 'https://disparador.net/',
        'logo' => 'https://disparador.net/public/assets/img/logo-disparador.png'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
    <script type="application/ld+json"><?= json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'SoftwareApplication',
        'name' => 'Disparador.net',
        'applicationCategory' => 'BusinessApplication',
        'operatingSystem' => 'Web',
        'url' => 'https://disparador.net/',
        'description' => 'Plataforma web para campanhas, notificações e atendimento pela API Oficial do WhatsApp Business.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
    <script type="application/ld+json"><?= json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => $faqSchema
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>

    <link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css"
    >

    <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"
    >

    <link
    rel="stylesheet"
    href="<?= ASSET_URL; ?>/css/style.css?v=13"
    >

    <style>
    .site-planos-header-acoes {
        display: flex;
        justify-content: center;
        gap: 0.5rem;
        margin-top: 1rem;
    }

    .site-planos-carousel {
        display: flex;
        flex-wrap: nowrap;
        gap: 1.5rem;
        overflow-x: auto;
        scroll-behavior: smooth;
        scroll-snap-type: x mandatory;
        padding: 0.25rem 0 1rem;
        scrollbar-width: thin;
    }

    .site-plano-carousel-item {
        flex: 0 0 calc((100% - 3rem) / 3);
        min-width: 280px;
        scroll-snap-align: start;
    }

    .site-planos-carousel-controle {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .site-planos-carousel-controle:disabled {
        opacity: 0.4;
        cursor: not-allowed;
    }

    .site-valor-primeiro-pagamento {
        font-size: 2.125rem;
        line-height: 1;
    }

    @media (max-width: 991.98px) {
        .site-plano-carousel-item {
            flex-basis: calc((100% - 1.5rem) / 2);
        }
    }

    @media (max-width: 575.98px) {
        .site-plano-carousel-item {
            flex-basis: 100%;
            min-width: 100%;
        }

        .site-valor-primeiro-pagamento {
            font-size: 1.875rem;
        }
    }
    
/* Hero com capturas reais completas: composição em profundidade sem distorcer o conteúdo. */
.site-showcase-stage-full{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.8fr) minmax(0,1fr);gap:0;align-items:center;max-width:1160px;margin:3rem auto 2.2rem;isolation:isolate}
.site-showcase-stage-full .site-showcase-browser-full{overflow:hidden;background:#fff;border:1px solid #d9e5ed;border-radius:13px;box-shadow:0 20px 45px rgba(16,54,80,.17)}
.site-showcase-stage-full .site-showcase-browser-full img{display:block;width:100%;height:auto;aspect-ratio:auto;object-fit:contain}
.site-showcase-stage-full .site-showcase-main{position:relative;z-index:3;transform:scale(1.12)}
.site-showcase-stage-full .site-showcase-main .site-showcase-browser-full{box-shadow:0 26px 55px rgba(16,54,80,.23)}
.site-showcase-stage-full .site-showcase-side{position:relative;z-index:1;min-width:0}
.site-showcase-stage-full .site-showcase-side-left{transform:perspective(950px) rotateY(9deg) rotate(-3deg) translateX(12px)}
.site-showcase-stage-full .site-showcase-side-right{transform:perspective(950px) rotateY(-9deg) rotate(3deg) translateX(-12px)}
.site-showcase-stage-full .site-showcase-side-label{margin:0 0 1rem;font-size:.92rem;font-weight:750}
.site-showcase-stage-full .site-showcase-browserbar{height:24px}
.site-showcase-stage-full .site-showcase-dot{width:7px;height:7px}
@media(max-width:991.98px){.site-showcase-stage-full{grid-template-columns:minmax(0,1fr);max-width:690px;gap:1.6rem;margin-top:2.2rem}.site-showcase-stage-full .site-showcase-main{grid-row:1;transform:none}.site-showcase-stage-full .site-showcase-side{transform:none;max-width:100%;display:block}.site-showcase-stage-full .site-showcase-side-label{text-align:left}.site-showcase-stage-full .site-showcase-side-left{grid-row:2}.site-showcase-stage-full .site-showcase-side-right{grid-row:3}}
@media(max-width:575.98px){.site-showcase-stage-full{gap:1.2rem}.site-showcase-stage-full .site-showcase-browser-full{border-radius:9px}.site-showcase-stage-full .site-showcase-browserbar{height:20px}}
</style>
</head>

<body>
<?php $googleTagManagerSection = 'body'; require __DIR__ . '/../partials/google_tag_manager.php'; ?>
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm fixed-top site-navbar">

    <div class="container">

        <a class="navbar-brand d-flex align-items-center" href="<?= BASE_URL; ?>/index.php?url=site">
            <img
            src="<?= ASSET_URL; ?>/img/logo-disparador.png"
            alt="Disparador.net"
            width="1136"
            height="247"
            class="site-logo"
            >
        </a>

        <button
        class="navbar-toggler"
        type="button"
        data-toggle="collapse"
        data-target="#menuSite"
        aria-controls="menuSite"
        aria-expanded="false"
        aria-label="Abrir menu de navegação"
        >
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="menuSite">

            <ul class="navbar-nav ml-auto align-items-lg-center site-main-nav">

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="menuProduto" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Produto</a>
                    <div class="dropdown-menu site-nav-dropdown" aria-labelledby="menuProduto">
                        <a class="dropdown-item" href="#como-funciona">Como funciona</a>
                        <a class="dropdown-item" href="#recursos">Campanhas pelo WhatsApp</a>
                        <a class="dropdown-item" href="#recursos">Gestão de contatos</a>
                        <a class="dropdown-item" href="#recursos">Atendimento e Conversas</a>
                        <a class="dropdown-item" href="#como-funciona">API Oficial do WhatsApp</a>
                    </div>
                </li>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="menuRecursos" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Recursos</a>
                    <div class="dropdown-menu site-nav-dropdown" aria-labelledby="menuRecursos">
                        <a class="dropdown-item" href="#faixas-meta">Faixas da Meta</a>
                        <a class="dropdown-item" href="#faq">FAQ</a>
                    </div>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="#planos">Planos</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_URL; ?>/blog">Blog</a>
                </li>

                <li class="nav-item site-nav-action">
                    <a
                    class="btn btn-outline-success site-nav-button"
                    href="<?= BASE_URL; ?>/index.php?url=login"
                    >
                        Entrar
                    </a>
                </li>

                <li class="nav-item site-nav-action">
                    <a
                    class="btn btn-success ml-lg-2 site-btn-main"
                    data-analytics-event="select_trial"
                    data-analytics-location="header"
                    data-analytics-destination="registration"
                    href="<?= BASE_URL; ?>/index.php?url=site/cadastro"
                    >
                        Começar teste grátis
                    </a>
                </li>

            </ul>

        </div>

    </div>

</nav>

<style>
/* Landing hero: apresentação do produto. Escopo isolado para não afetar o painel. */
.site-showcase-real-shot{display:block;width:100%;height:auto;object-fit:contain}
.site-showcase-browser:has(> .site-showcase-real-shot) > .site-showcase-browserbar,
.site-showcase-browser:has(> .site-showcase-real-shot) > .site-showcase-app,
.site-showcase-browser:has(> .site-showcase-real-shot) > .site-showcase-mini{display:none}
.site-hero-showcase{position:relative;overflow:hidden;padding:6.5rem 0 4rem;background:radial-gradient(ellipse at 12% 68%,rgba(13,181,86,.16),transparent 40%),radial-gradient(ellipse at 88% 70%,rgba(0,113,216,.17),transparent 42%),linear-gradient(180deg,#fff 0%,#f6fffb 100%);color:#102238}
.site-hero-showcase .site-showcase-inner{max-width:1160px;margin:auto;text-align:center}
.site-showcase-eyebrow{display:inline-flex;align-items:center;gap:.5rem;border-radius:50px;background:#e0f8ec;padding:.55rem 1rem;color:#12362c;font-weight:700;font-size:.86rem}
.site-showcase-heading{font-weight:800;letter-spacing:-.035em;font-size:clamp(2.1rem,4.4vw,3.75rem);line-height:1.23;margin:1.35rem auto 1rem;max-width:1050px}
.site-showcase-heading span{display:block;color:#079e49;background:linear-gradient(90deg,#08a640,#0077d9);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;padding-bottom:.16em;margin-bottom:-.16em}
.site-showcase-description{max-width:780px;margin:0 auto 1.4rem;font-size:1.14rem;line-height:1.65;color:#4e647d}
.site-showcase-actions{display:flex;justify-content:center;flex-wrap:wrap;gap:.85rem}
.site-showcase-actions .btn{border-radius:10px;padding:.85rem 1.55rem;font-weight:700}
.site-showcase-trust{display:flex;justify-content:center;gap:1rem 2rem;flex-wrap:wrap;margin:1.3rem auto 2.3rem;font-size:.9rem;color:#53657b}
.site-showcase-trust i{color:#09a34c;margin-right:.35rem}
.site-showcase-stage{position:relative;display:grid;grid-template-columns:minmax(0,1fr) minmax(0,2.25fr) minmax(0,1fr);align-items:center;gap:1rem;margin:0 auto 2rem}
.site-showcase-browser{min-width:0;text-align:left;border:1px solid #d9e5ed;border-radius:16px;background:#fff;box-shadow:0 22px 55px rgba(14,49,70,.17);overflow:hidden}
.site-showcase-browser.is-side{transform:perspective(900px) rotateY(7deg);box-shadow:0 12px 35px rgba(14,49,70,.12)}
.site-showcase-browser.is-right{transform:perspective(900px) rotateY(-7deg)}
.site-showcase-browserbar{height:29px;background:#f5f8fa;border-bottom:1px solid #e7edf2;display:flex;align-items:center;gap:5px;padding:0 12px}
.site-showcase-dot{width:8px;height:8px;border-radius:50%;background:#ff6a68}.site-showcase-dot:nth-child(2){background:#f6c34b}.site-showcase-dot:nth-child(3){background:#39c774}
.site-showcase-app{display:grid;grid-template-columns:105px minmax(0,1fr);min-height:280px;font-size:.7rem}
.site-showcase-sidebar{background:#303840;color:#fff;padding:12px 8px}.site-showcase-sidebar strong{display:block;color:#fff;font-size:.72rem;margin-bottom:13px}.site-showcase-sidebar span{display:block;padding:7px 5px;color:#e5edf5}.site-showcase-sidebar span.active{background:#0876dc;border-radius:4px;color:#fff}
.site-showcase-content{padding:13px;min-width:0;background:#f5f7fa}.site-showcase-content h3{font-size:1.03rem;font-weight:750;margin:0 0 12px}
.site-showcase-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:7px}.site-showcase-stat{border-radius:6px;color:white;padding:11px 7px;min-width:0}.site-showcase-stat b{display:block;font-size:1.2rem}.site-showcase-stat small{font-size:.61rem}.site-showcase-stat:nth-child(1){background:#1b9eb2}.site-showcase-stat:nth-child(2){background:#df3546}.site-showcase-stat:nth-child(3){background:#23a445}.site-showcase-stat:nth-child(4){background:#f2b700;color:#15283b}
.site-showcase-panels{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin-top:10px}.site-showcase-panel{border:1px solid #e3e7eb;background:white;border-radius:7px;padding:11px 8px;min-height:90px}.site-showcase-panel strong{display:block;font-size:.72rem;margin-bottom:9px}.site-showcase-panel span{display:block;color:#64748b;margin-top:6px}
.site-showcase-mini{padding:11px;background:#f7f9fb;min-height:165px}.site-showcase-mini h3{font-size:.9rem;font-weight:700}.site-showcase-line{height:15px;background:white;border:1px solid #e2e8ef;margin-top:6px;border-radius:3px;padding:2px 5px;color:#54647b;font-size:.57rem}
.site-showcase-side-label{font-weight:750;font-size:.86rem;color:#123c4d;margin-bottom:.8rem}
.site-showcase-bottom{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem;background:rgba(255,255,255,.92);border:1px solid #eaf0f1;border-radius:17px;box-shadow:0 8px 35px rgba(20,62,72,.07);padding:1.3rem;text-align:left}
.site-showcase-benefit{display:flex;gap:.85rem;align-items:flex-start}.site-showcase-benefit i{color:#0aa54b;font-size:1.5rem}.site-showcase-benefit strong{display:block;font-size:.94rem}.site-showcase-benefit p{margin:.25rem 0 0;font-size:.82rem;color:#617287}
@media(max-width:991.98px){.site-hero-showcase{padding-top:5rem}.site-showcase-stage{grid-template-columns:minmax(0,1fr);max-width:680px}.site-showcase-browser.is-side{display:none}.site-showcase-bottom{grid-template-columns:1fr}.site-showcase-heading{max-width:700px}}
@media(max-width:575.98px){.site-hero-showcase{padding:4rem 0 2.5rem}.site-showcase-heading{font-size:2rem}.site-showcase-description{font-size:1rem}.site-showcase-actions .btn{width:100%;margin:0!important}.site-showcase-trust{gap:.6rem 1rem;font-size:.8rem}.site-showcase-app{grid-template-columns:72px minmax(0,1fr);min-height:235px;font-size:.55rem}.site-showcase-sidebar{padding:8px 4px}.site-showcase-sidebar strong{font-size:.57rem}.site-showcase-sidebar span{padding:6px 2px}.site-showcase-content{padding:8px}.site-showcase-content h3{font-size:.85rem}.site-showcase-stat{padding:8px 3px}.site-showcase-stat b{font-size:.9rem}.site-showcase-stat small{font-size:.47rem}.site-showcase-panels{gap:4px}.site-showcase-panel{padding:6px 4px}.site-showcase-panel strong{font-size:.55rem}.site-showcase-panel span{font-size:.5rem}}
@media(prefers-reduced-motion:reduce){.site-hero-showcase *{scroll-behavior:auto!important}}
</style>
<section class="site-hero-showcase" aria-labelledby="site-showcase-title">
  <div class="container site-showcase-inner">
    <span class="site-showcase-eyebrow"><i class="fab fa-whatsapp" aria-hidden="true"></i> API Oficial do WhatsApp Business</span>
    <h1 class="site-showcase-heading" id="site-showcase-title">Seu WhatsApp já conecta você aos clientes.<span>O Disparador leva sua operação mais longe.</span></h1>
    <p class="site-showcase-description">Crie campanhas oficiais, organize contatos e centralize o atendimento em uma plataforma desenvolvida para empresas que querem crescer.</p>
    <div class="site-showcase-actions">
      <a href="<?= BASE_URL; ?>/index.php?url=site/cadastro" class="btn btn-success btn-lg site-btn-main" data-analytics-event="select_trial" data-analytics-location="hero" data-analytics-destination="registration"><i class="fas fa-rocket mr-2" aria-hidden="true"></i>Começar teste grátis</a>
      <a href="#recursos" class="btn btn-outline-primary btn-lg"><i class="fas fa-play mr-2" aria-hidden="true"></i>Conhecer a plataforma</a>
    </div>
    <div class="site-showcase-trust">
      <span><i class="fas fa-check-circle" aria-hidden="true"></i>Até 7 dias ou 200 mensagens</span>
      <span><i class="fas fa-check-circle" aria-hidden="true"></i>Sem mensalidade durante o teste</span>
      <span><i class="fas fa-cloud" aria-hidden="true"></i>Operação em nuvem</span>
    </div>
    <div class="site-showcase-stage site-showcase-stage-full" aria-label="Capturas reais das telas de Campanhas, Dashboard e Listas de Contatos do Disparador.net, com dados ocultados">
      <div class="site-showcase-side site-showcase-side-left">
        <div class="site-showcase-side-label"><i class="fas fa-bullhorn text-success mr-2" aria-hidden="true"></i>Campanhas organizadas</div>
        <div class="site-showcase-browser site-showcase-browser-full" aria-label="Tela de Campanhas do Disparador.net">
          <div class="site-showcase-browserbar" aria-hidden="true"><i class="site-showcase-dot"></i><i class="site-showcase-dot"></i><i class="site-showcase-dot"></i></div>
          <img src="<?= ASSET_URL; ?>/img/landing/campanhas-completo.webp" alt="Tela completa de Campanhas, com menu lateral e tabela de campanhas" width="1272" height="752" loading="eager" decoding="async">
        </div>
      </div>
      <div class="site-showcase-main">
        <div class="site-showcase-browser site-showcase-browser-full" aria-label="Dashboard do Disparador.net">
          <div class="site-showcase-browserbar" aria-hidden="true"><i class="site-showcase-dot"></i><i class="site-showcase-dot"></i><i class="site-showcase-dot"></i></div>
          <img src="<?= ASSET_URL; ?>/img/landing/dashboard-completo.webp" alt="Dashboard completo do Disparador.net, incluindo menu lateral, indicadores e ações rápidas" width="1269" height="756" loading="eager" fetchpriority="high" decoding="async">
        </div>
      </div>
      <div class="site-showcase-side site-showcase-side-right">
        <div class="site-showcase-side-label"><i class="fas fa-address-book text-success mr-2" aria-hidden="true"></i>Contatos em listas</div>
        <div class="site-showcase-browser site-showcase-browser-full" aria-label="Tela de Listas de Contatos do Disparador.net">
          <div class="site-showcase-browserbar" aria-hidden="true"><i class="site-showcase-dot"></i><i class="site-showcase-dot"></i><i class="site-showcase-dot"></i></div>
          <img src="<?= ASSET_URL; ?>/img/landing/listas-completo.webp" alt="Tela completa de Listas de Contatos, com menu lateral, listagem e ações de gerenciamento" width="1450" height="663" loading="eager" decoding="async">
        </div>
      </div>
    </div>
    <div class="site-showcase-bottom">
      <div class="site-showcase-benefit"><i class="fab fa-whatsapp" aria-hidden="true"></i><div><strong>Continue usando o WhatsApp Business</strong><p>Quando elegível, conecte o mesmo número e continue usando o aplicativo no celular.</p></div></div>
      <div class="site-showcase-benefit"><i class="fas fa-shield-alt" aria-hidden="true"></i><div><strong>Mensagens pela API Oficial</strong><p>Envios com templates aprovados e conforme as políticas da Meta.</p></div></div>
      <div class="site-showcase-benefit"><i class="fas fa-users" aria-hidden="true"></i><div><strong>Atendimento em equipe</strong><p>Organize suas conversas em uma central de atendimento.</p></div></div>
    </div>
    <p class="small text-muted mt-3 mb-0">Para realizar envios, é necessário configurar uma forma de pagamento na Meta. As tarifas da Meta são cobradas separadamente da mensalidade do Disparador.net.</p>
  </div>
</section>

<section class="py-5 bg-white border-top border-bottom" id="whatsapp-business">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-7 mb-4 mb-lg-0">
                <span class="badge badge-success mb-3">WhatsApp Business + Disparador.net</span>
                <h2 class="site-section-title">Conecte seu WhatsApp Business ao Disparador.net</h2>
                <p class="lead text-muted">
                    Use o mesmo número que sua empresa já utiliza para conversar com clientes e tenha também campanhas, atendimento em equipe, contatos e templates no Disparador.net.
                </p>
                <p class="text-muted">
                    Com a integração oficial da Meta, números elegíveis podem ser conectados ao Disparador e continuar funcionando no aplicativo WhatsApp Business.
                </p>
                <a href="<?= BASE_URL; ?>/whatsapp-business" class="btn btn-success site-btn-main" data-analytics-event="whatsapp_business">
                    Saiba como funciona
                </a>
            </div>
            <div class="col-lg-5">
                <div class="card site-feature-card h-100">
                    <div class="card-body p-4">
                        <p><i class="fas fa-mobile-alt text-success mr-2"></i> Continue utilizando o WhatsApp Business no celular.</p>
                        <p><i class="fas fa-random text-success mr-2"></i> Use o mesmo número no Disparador.net.</p>
                        <p><i class="fas fa-address-book text-success mr-2"></i> <strong>Seus contatos do WhatsApp Business podem ser sincronizados automaticamente com o Disparador.net.</strong></p>
                        <p><i class="fas fa-users text-success mr-2"></i> Centralize o atendimento da sua equipe.</p>
                        <p class="mb-0"><i class="fas fa-bullhorn text-success mr-2"></i> Envie campanhas pela plataforma oficial da Meta.</p>
                    </div>
                </div>
                <p class="text-muted small mt-2 mb-0">A sincronização depende dos contatos e eventos disponibilizados pela Meta para o número conectado.</p>
            </div>
        </div>
    </div>
</section>

<?php if(false){ ?>
<section class="py-5 bg-white border-top border-bottom">

    <div class="container">

        <div class="text-center mb-4">

            <span class="badge badge-success mb-3">
                Infraestrutura Oficial
            </span>

            <h2 class="site-section-title">
                Integrado à Plataforma Oficial da Meta
            </h2>

            <p class="text-muted mx-auto" style="max-width: 750px;">

                O Disparador.net utiliza a API Oficial do WhatsApp Business Platform para campanhas, atendimento e templates oficiais, com opções de conexão apresentadas pela Meta conforme a elegibilidade de cada número.

            </p>

        </div>

        <div class="row justify-content-center align-items-center">

            <div class="col-md-8">

                <div class="card site-card-feature">

                    <div class="card-body p-4">

                        <div class="row text-center">

                            <div class="col-md-6 mb-4 mb-md-0">

                                <img
                                src="<?= ASSET_URL; ?>/img/whatsapp-business.png"
                                alt="WhatsApp Business"
                                width="60"
                                height="60"
                                loading="lazy"
                                style="height:60px;"
                                >

                                <h5 class="mt-3 mb-2">
                                    WhatsApp Business Platform
                                </h5>

                                <small class="text-muted">
                                    API oficial para campanhas, atendimento multiatendente e templates aprovados pela Meta.
                                </small>

                            </div>

                            <div class="col-md-6">

                                <img
                                src="<?= ASSET_URL; ?>/img/meta-logo.png"
                                alt="Meta"
                                width="109"
                                height="60"
                                loading="lazy"
                                style="height:60px;"
                                >

                                <h5 class="mt-3 mb-2">
                                    Plataforma Meta
                                </h5>

                                <small class="text-muted">
                                    Infraestrutura oficial para operar campanhas e atendimento em um ambiente mais seguro.
                                </small>

                            </div>

                        </div>

                        <hr>

                        <div class="row text-center">

                            <div class="col-md-3 col-6 mb-3">

                                <i class="fas fa-check-circle text-success"></i>

                                <div class="small mt-2">
                                    API Oficial
                                </div>

                            </div>

                            <div class="col-md-3 col-6 mb-3">

                                <i class="fas fa-check-circle text-success"></i>

                                <div class="small mt-2">
                                    Templates oficiais
                                </div>

                            </div>

                            <div class="col-md-3 col-6 mb-3">

                                <i class="fas fa-check-circle text-success"></i>

                                <div class="small mt-2">
                                    Operação em nuvem
                                </div>

                            </div>

                            <div class="col-md-3 col-6 mb-3">

                                <i class="fas fa-check-circle text-success"></i>

                                <div class="small mt-2">
                                    Ambiente mais seguro
                                </div>

                            </div>

                        </div>

                    </div>

                </div>

                <p class="text-center text-muted small mt-3 mb-0">

                    Meta, WhatsApp e seus respectivos logotipos são marcas de seus proprietários.
                    Operação pela API Oficial da Meta, reduzindo riscos de automações não autorizadas.

                </p>

            </div>

        </div>

    </div>

</section>

<?php } ?>
<section id="recursos" class="py-5">

    <div class="container">

        <div class="text-center mb-5">

            <h2 class="site-section-title">
                Recursos para vender, atender e organizar melhor
            </h2>

            <p class="text-muted">
                Uma plataforma simples para empresas que querem usar o WhatsApp de forma profissional.
            </p>

        </div>

        <div class="row">

            <?php
            $recursos = [
                [
                    'icon' => 'fas fa-bullhorn',
                    'titulo' => 'Alcance seus clientes',
                    'texto' => 'Alcance seus clientes com campanhas usando templates oficiais aprovados pela Meta.'
                ],
                [
                    'icon' => 'fas fa-file-alt',
                    'titulo' => 'Use mensagens oficiais',
                    'texto' => 'Crie, sincronize e utilize modelos aprovados para iniciar conversas com segurança.'
                ],
                [
                    'icon' => 'fas fa-list',
                    'titulo' => 'Organize seus contatos',
                    'texto' => 'Importe contatos, organize públicos e segmente campanhas por listas.'
                ],
                [
                    'icon' => 'fas fa-comments',
                    'titulo' => 'Atenda em equipe',
                    'texto' => 'Atenda mensagens recebidas em uma central simples, organizada e multiatendente.'
                ],
                [
                    'icon' => 'fas fa-tags',
                    'titulo' => 'Etiquetas e filtros',
                    'texto' => 'Classifique conversas por status, prioridade, assunto ou etapa do atendimento.'
                ],
                [
                    'icon' => 'fab fa-whatsapp',
                    'titulo' => 'Múltiplos números',
                    'texto' => 'Conecte mais de um número WhatsApp conforme o plano contratado e a operação da empresa.'
                ],
            ];
            ?>

            <?php foreach($recursos as $recurso){ ?>

                <div class="col-md-4 mb-4">

                    <div class="card h-100 site-card-feature">

                        <div class="card-body p-4">

                            <div class="site-feature-icon mb-3">
                                <i class="<?= $recurso['icon']; ?>"></i>
                            </div>

                            <h5 class="font-weight-bold">
                                <?= $recurso['titulo']; ?>
                            </h5>

                            <p class="text-muted mb-0">
                                <?= $recurso['texto']; ?>
                            </p>

                        </div>

                    </div>

                </div>

            <?php } ?>

        </div>

    </div>

</section>

<section id="comparacao" class="py-5 bg-light"><div class="container"><div class="text-center mb-5"><h2 class="site-section-title">Do WhatsApp da empresa para uma operação profissional</h2><p class="text-muted">O Disparador.net complementa a rotina do WhatsApp Business com organização para sua equipe.</p></div><div class="row justify-content-center"><div class="col-md-5 mb-4"><div class="card h-100"><div class="card-body"><h3 class="h4"><i class="fab fa-whatsapp text-success mr-2"></i>WhatsApp Business</h3><ul class="site-check-list"><li>Atendimento individual</li><li>Contatos no celular</li><li>Comunicação manual</li><li>Mensagens comuns</li><li>Gestão pelo aplicativo</li></ul></div></div></div><div class="col-md-5 mb-4"><div class="card h-100 border-success"><div class="card-body"><h3 class="h4"><i class="fas fa-layer-group text-success mr-2"></i>Com Disparador.net</h3><ul class="site-check-list"><li>Atendimento em equipe</li><li>Listas organizadas</li><li>Campanhas</li><li>Templates oficiais</li><li>Gestão pelo navegador</li><li>Mesmo número + Disparador.net, quando elegível</li></ul></div></div></div></div></div></section>

<section id="integracao-oficial" class="py-5 bg-white border-top border-bottom"><div class="container"><div class="row align-items-center"><div class="col-lg-7"><span class="badge badge-success mb-3">Confiança para sua operação</span><h2 class="site-section-title">Integrado à plataforma oficial da Meta</h2><p class="lead text-muted">Campanhas, atendimento e templates funcionam pela infraestrutura oficial do WhatsApp Business.</p><p class="text-muted">A conexão passa pelos processos apresentados pela Meta e respeita as políticas aplicáveis à conta, ao número e às mensagens.</p></div><div class="col-lg-5"><div class="card site-card-feature"><div class="card-body p-4"><p><i class="fas fa-shield-alt text-success mr-2"></i> Integração oficial</p><p><i class="fas fa-cloud text-success mr-2"></i> Operação em nuvem</p><p class="mb-0"><i class="fas fa-file-alt text-success mr-2"></i> Templates aprovados pela Meta</p></div></div></div></div></div></section>

<section id="como-funciona" class="py-5 bg-light">

    <div class="container">

        <div class="text-center mb-5">

            <h2 class="site-section-title">
                Como funciona
            </h2>

            <p class="text-muted">
                Um fluxo simples para começar a usar campanhas e atendimento em uma plataforma oficial.
            </p>

        </div>

        <div class="row text-center">

            <div class="col-md-3 mb-4">
                <div class="site-step">1</div>
                <h5 class="font-weight-bold">Solicite seu acesso</h5>
                <p class="text-muted">Cadastre sua empresa e acesse o painel do Disparador.net.</p>
            </div>

            <div class="col-md-3 mb-4">
                <div class="site-step">2</div>
                <h5 class="font-weight-bold">Conecte seu WhatsApp</h5>
                <p class="text-muted">Conecte um novo número ou, quando elegível, use o número que já utiliza no WhatsApp Business.</p>
            </div>

            <div class="col-md-3 mb-4">
                <div class="site-step">3</div>
                <h5 class="font-weight-bold">Importe seus contatos</h5>
                <p class="text-muted">Crie listas e organize sua base de clientes.</p>
            </div>

            <div class="col-md-3 mb-4">
                <div class="site-step">4</div>
                <h5 class="font-weight-bold">Venda e atenda mais</h5>
                <p class="text-muted">Envie campanhas e acompanhe as conversas em uma única central.</p>
            </div>

        </div>

    </div>

</section>

<section id="para-quem" class="py-5 bg-white"><div class="container"><div class="text-center mb-5"><h2 class="site-section-title">Feito para empresas que usam o WhatsApp todos os dias</h2><p class="text-muted">Organize a comunicação que já faz parte da rotina da sua empresa.</p></div><div class="row">
<?php foreach([['Comércio e varejo','Divulgue novidades e organize o atendimento aos clientes.'],['Prestadores de serviços','Mantenha contatos e conversas acessíveis para sua equipe.'],['Distribuidores e representantes','Segmente listas e envie comunicações oficiais.'],['Clínicas e escritórios','Centralize o atendimento administrativo da empresa.'],['Pequenas e médias empresas','Profissionalize campanhas e conversas sem complicar a operação.']] as $segmento){ ?><div class="col-md-4 mb-4"><div class="card h-100 site-card-feature"><div class="card-body"><h3 class="h5 font-weight-bold"><?= $segmento[0]; ?></h3><p class="text-muted mb-0"><?= $segmento[1]; ?></p></div></div></div><?php } ?>
</div></div></section>

<?php require __DIR__ . '/partials/planos.php'; ?>

<?php if(!empty($campanhaIndicacaoPublica['disponivel'])){ ?>
<?php $percentualIndicacao = rtrim(rtrim(number_format((float) $campanhaIndicacaoPublica['percentual'], 2, ',', '.'), '0'), ','); ?>
<section id="programa-indicacao" class="py-5 bg-white border-bottom">

    <div class="container">

        <div class="text-center mb-5">

            <span class="badge badge-success mb-3">Programa de indicação</span>

            <h2 class="site-section-title">Indique e Ganhe</h2>

            <p class="text-muted mx-auto" style="max-width: 720px;">
                Clientes Disparador.net podem indicar outras empresas e economizar nas próximas mensalidades quando a indicação for confirmada pelas regras do programa.
            </p>

            <p class="text-muted mx-auto mb-0" style="max-width: 720px;">
                <strong>Comece economizando e continue economizando.</strong><br>
                Todo novo cliente tem <strong>50% de desconto na primeira mensalidade</strong>. Depois, como cliente, você pode indicar novas empresas e receber <strong><?= htmlspecialchars($percentualIndicacao, ENT_QUOTES, 'UTF-8'); ?>% de desconto em mensalidades futuras elegíveis</strong> por indicação confirmada, conforme as condições do programa.
            </p>

        </div>

        <div class="row text-center mb-4">

            <div class="col-md-3 mb-4 mb-md-0">
                <div class="site-step">1</div>
                <h3 class="h5 font-weight-bold">Seja cliente</h3>
                <p class="text-muted mb-0">Crie sua conta e conheça a plataforma do Disparador.net.</p>
            </div>

            <div class="col-md-3 mb-4 mb-md-0">
                <div class="site-step">2</div>
                <h3 class="h5 font-weight-bold">Receba seu código</h3>
                <p class="text-muted mb-0">Após a ativação e a confirmação do pagamento exigido, seu código e link ficam disponíveis.</p>
            </div>

            <div class="col-md-3 mb-4 mb-md-0">
                <div class="site-step">3</div>
                <h3 class="h5 font-weight-bold">Compartilhe</h3>
                <p class="text-muted mb-0">Envie o link ou o código para a empresa que deseja indicar.</p>
            </div>

            <div class="col-md-3">
                <div class="site-step">4</div>
                <h3 class="h5 font-weight-bold">O indicado faz o cadastro</h3>
                <p class="text-muted mb-0">A empresa indicada acessa o cadastro pelo link ou informa o código de indicação manualmente.</p>
            </div>

        </div>

        <p class="text-center text-muted mb-4">Depois que a indicação for confirmada conforme as regras do programa, você recebe o crédito de <?= htmlspecialchars($percentualIndicacao, ENT_QUOTES, 'UTF-8'); ?>% para mensalidades futuras elegíveis.</p>

        <div class="row justify-content-center">

            <div class="col-lg-5 mb-4 mb-lg-0">
                <div class="card h-100 site-card-feature">
                    <div class="card-body p-4">
                        <div class="site-feature-icon mb-3"><i class="fas fa-gift"></i></div>
                        <h3 class="h5 font-weight-bold">Para novos clientes</h3>
                        <p class="font-weight-bold mb-2">50% de desconto na primeira mensalidade para novos clientes.</p>
                        <p class="text-muted mb-0">Este benefício é válido com ou sem indicação.</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card h-100 site-card-feature">
                    <div class="card-body p-4">
                        <div class="site-feature-icon mb-3"><i class="fas fa-share-alt"></i></div>
                        <h3 class="h5 font-weight-bold">Para quem indica</h3>
                        <p class="font-weight-bold mb-2">Quando uma indicação elegível é confirmada conforme as regras do programa, quem indicou recebe um crédito de <?= htmlspecialchars($percentualIndicacao, ENT_QUOTES, 'UTF-8'); ?>% de desconto em mensalidades futuras elegíveis.</p>
                        <p class="text-muted mb-0">O crédito é aplicado de acordo com as condições vigentes do programa.</p>
                    </div>
                </div>
            </div>

        </div>

        <div class="text-center mt-4">
            <a
            href="<?= BASE_URL; ?>/index.php?url=site/cadastro"
            class="btn btn-success site-btn-main"
            data-analytics-event="select_trial"
            data-analytics-location="referral_program"
            data-analytics-destination="registration"
            >
                Criar minha conta
            </a>
        </div>

    </div>

</section>
<?php } ?>

<?php if(!empty($depoimentosPublicados)){ ?>
<section id="depoimentos" class="py-5 bg-light"><div class="container"><div class="text-center mb-5"><span class="badge badge-success mb-3">Experiências reais</span><h2 class="site-section-title">O que nossos clientes dizem</h2></div><div class="row">
<?php foreach($depoimentosPublicados as $depoimento){ ?><div class="col-md-6 col-lg-4 mb-4"><article class="card h-100 site-card-feature"><div class="card-body p-4"><i class="fas fa-quote-left text-success mb-3"></i><p><?= nl2br(htmlspecialchars($depoimento['DEP_Depoimento'], ENT_QUOTES, 'UTF-8')); ?></p><footer><strong><?= htmlspecialchars($depoimento['DEP_NomeExibido'], ENT_QUOTES, 'UTF-8'); ?></strong><br><span class="text-muted"><?= htmlspecialchars($depoimento['DEP_Cargo'] ? $depoimento['DEP_Cargo'].' — '.$depoimento['DEP_Empresa'] : $depoimento['DEP_Empresa'], ENT_QUOTES, 'UTF-8'); ?></span></footer></div></article></div><?php } ?>
</div></div></section>
<?php } ?>

<?php if(false){ ?>
<section id="faixas-meta-legado" class="py-5 site-meta-tiers">

    <div class="container">

        <div class="text-center mb-5">
            <span class="badge badge-success mb-3">Capacidade de envio</span>
            <h2 class="site-section-title">Faixas de envio da Meta</h2>
            <p class="text-muted mx-auto site-section-lead">
                A Meta define quantas conversas iniciadas pela empresa cada número pode abrir em uma janela móvel de 24 horas. Conforme o uso e a qualidade do número evoluem, o limite pode aumentar.
            </p>
        </div>

        <div class="table-responsive site-meta-tiers-table">
            <table class="table table-bordered bg-white mb-0">
                <caption class="sr-only">Faixas de conversas iniciadas pela empresa em uma janela móvel de 24 horas</caption>
                <thead class="thead-light">
                    <tr>
                        <th scope="col">Faixa</th>
                        <th scope="col">Limite em 24 horas</th>
                        <th scope="col">Explicação</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><th scope="row">Inicial</th><td>250</td><td>Limite inicial que pode ser aplicado a números ou empresas que ainda não atingiram os requisitos para níveis superiores.</td></tr>
                    <tr><th scope="row">Nível 1</th><td>1.000</td><td>Primeiro nível ampliado para envio de mensagens iniciadas pela empresa.</td></tr>
                    <tr><th scope="row">Nível 2</th><td>10.000</td><td>Faixa destinada a operações com maior histórico de utilização e qualidade.</td></tr>
                    <tr><th scope="row">Nível 3</th><td>100.000</td><td>Faixa de alto volume para números elegíveis.</td></tr>
                    <tr><th scope="row">Nível máximo</th><td>Ilimitado</td><td>Maior capacidade disponível, condicionada às regras e à avaliação da Meta.</td></tr>
                </tbody>
            </table>
        </div>

        <div class="alert alert-light border mt-4" role="note">
            <p><strong>Os limites são definidos e administrados pela Meta</strong> e podem variar conforme a situação da conta, verificação da empresa, qualidade do número, histórico de uso e políticas vigentes. O Disparador.net não controla nem garante a concessão ou o aumento dessas faixas.</p>
            <p>A evolução não depende apenas da contratação de um plano. A Meta considera fatores como a qualidade do número, o status da conexão, a verificação da empresa e o volume de uso elegível.</p>
            <p class="mb-0"><strong>Os limites de envio da Meta são diferentes da quantidade de mensagens incluída no plano contratado no Disparador.net.</strong></p>
        </div>

        <p class="text-muted small">
            As faixas acima representam limites de envio e não preços. A cobrança da WhatsApp Business Platform segue as tarifas da Meta por mensagem entregue, considerando a categoria da mensagem — Marketing, Utilidade, Autenticação ou Serviço — e o país do destinatário. Os valores podem mudar conforme as políticas vigentes.
        </p>

        <div class="row mt-4">
            <div class="col-md-4 mb-4"><div class="card h-100 site-card-feature"><div class="card-body"><h3 class="h5 font-weight-bold">Qualidade do número</h3><p class="text-muted mb-0">Bloqueios, denúncias e baixo engajamento podem afetar a qualidade do número e sua capacidade de envio.</p></div></div></div>
            <div class="col-md-4 mb-4"><div class="card h-100 site-card-feature"><div class="card-body"><h3 class="h5 font-weight-bold">Evolução administrada pela Meta</h3><p class="text-muted mb-0">A Meta avalia os requisitos da conta e do número para disponibilizar níveis superiores.</p></div></div></div>
            <div class="col-md-4 mb-4"><div class="card h-100 site-card-feature"><div class="card-body"><h3 class="h5 font-weight-bold">API Oficial</h3><p class="text-muted mb-0">O Disparador.net realiza a integração por meio da API Oficial do WhatsApp Business, respeitando templates, webhooks e políticas da plataforma.</p></div></div></div>
        </div>

    </div>

</section>
<?php } ?>

<section id="faixas-meta" class="py-5 site-meta-tiers"><div class="container"><div class="row justify-content-center"><div class="col-lg-9 text-center"><span class="badge badge-success mb-3">Transparência</span><h2 class="site-section-title">Envie pela infraestrutura oficial do WhatsApp</h2><p class="lead text-muted">Os limites de envio são administrados pela Meta e podem evoluir conforme a utilização, a qualidade do número e a elegibilidade da empresa.</p><p class="text-muted">Esses limites são diferentes da franquia incluída no plano do Disparador.net.</p><a class="btn btn-outline-success" href="<?= BASE_URL; ?>/limites-whatsapp" data-analytics-event="limites_whatsapp">Entenda os limites de envio</a></div></div></div></section>

<?php if(false){ ?>
<section id="planos-legado" class="py-5">

    <div class="container">

        <div class="text-center mb-5">

            <h2 class="site-section-title">
                Planos para cada fase da sua empresa
            </h2>

            <p class="text-muted">
                Comece simples e aumente conforme sua operação crescer.
            </p>

        </div>

        <?php
        $planos = is_array($planos ?? null) ? $planos : [];
        $coresPermitidas = [
            'primary',
            'secondary',
            'success',
            'danger',
            'warning',
            'info',
            'light',
            'dark'
        ];

        $formatarQuantidade = function($quantidade, $singular, $plural){
            $quantidade = (int) $quantidade;
            $texto = $quantidade === 1 ? $singular : $plural;

            return number_format($quantidade, 0, ',', '.') . ' ' . $texto;
        };
        ?>

        <?php if(!empty($planos)){ ?>

            <div class="site-planos-header-acoes" aria-label="Navegação dos planos">
                <button type="button" class="btn btn-outline-success site-planos-carousel-controle" id="sitePlanosAnterior" aria-label="Plano anterior">
                    <i class="fas fa-chevron-left"></i>
                </button>

                <button type="button" class="btn btn-outline-success site-planos-carousel-controle" id="sitePlanosProximo" aria-label="Próximo plano">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>

            <div class="site-planos-carousel mt-4" id="sitePlanosCarousel">

                <?php foreach($planos as $plano){ ?>

                    <?php
                    $corPlano = in_array($plano['PLA_Cor'] ?? '', $coresPermitidas, true)
                        ? $plano['PLA_Cor']
                        : 'primary';

                    $valorMensal = \Models\Plano::valorPorCiclo($plano, 'mensal');
                    $ofertaMensal = $ofertasPublicasPlanos[(int) $plano['PLA_ID']]['mensal'] ?? [];
                    $valorPrimeiroPagamento = ((int) ($ofertaMensal['primeira_cobranca_centavos'] ?? 0)) / 100;
                    $valorDesconto = ((int) ($ofertaMensal['desconto_centavos'] ?? 0)) / 100;
                    ?>

                    <div class="site-plano-carousel-item">

                        <div class="card border-<?= $corPlano; ?> h-100">

                            <div class="card-body p-4 text-center">

                                <span class="badge badge-<?= $corPlano; ?> mb-3">
                                    <?= htmlspecialchars($plano['PLA_Nome']); ?>
                                </span>

                                <p class="text-success font-weight-bold mb-1">
                                    <span class="site-valor-primeiro-pagamento">R$ <?= number_format($valorPrimeiroPagamento, 2, ',', '.'); ?></span><span> na primeira cobrança mensal</span>
                                </p>

                                <p class="text-muted mb-1">
                                    Economia de R$ <?= number_format($valorDesconto, 2, ',', '.'); ?>: 50% da primeira mensalidade
                                </p>

                                <p class="mb-3">
                                    Renovação: <strong>R$ <?= number_format($valorMensal, 2, ',', '.'); ?>/mês</strong>
                                </p>

                                <p class="text-muted">
                                    <?= $formatarQuantidade($plano['PLA_LimiteNumeros'] ?? 0, 'número WhatsApp', 'números WhatsApp'); ?>
                                </p>

                                <hr>

                                <p>
                                    <i class="fas fa-users text-success"></i>
                                    <?= $formatarQuantidade($plano['PLA_LimiteUsuarios'] ?? 0, 'usuário', 'usuários'); ?>
                                </p>

                                <p>
                                    <i class="fas fa-paper-plane text-primary"></i>
                                    <?= $formatarQuantidade($plano['PLA_LimiteMensagens'] ?? 0, 'mensagem/mês', 'mensagens/mês'); ?>
                                </p>

                                <p>
                                    <i class="fas fa-check text-success"></i>
                                    Campanhas, listas, templates e conversas
                                </p>

                                <a
                                href="<?= BASE_URL; ?>/index.php?url=site/cadastro"
                                class="btn btn-outline-success btn-block"
                                data-analytics-event="select_trial"
                                data-analytics-location="pricing"
                                data-analytics-destination="registration"
                                data-analytics-plan="<?= htmlspecialchars($plano['PLA_Nome'], ENT_QUOTES, 'UTF-8'); ?>"
                                >
                        Começar teste grátis
                                </a>

                            </div>

                        </div>

                    </div>

                <?php } ?>

            </div>

        <?php }else{ ?>

            <div class="alert alert-light border text-center mb-0">
                Os planos estão sendo atualizados. Solicite acesso para receber uma proposta adequada à sua operação.
            </div>

        <?php } ?>

        <p class="text-center text-muted mt-3 mb-0">
            Valores e limites podem ser ajustados conforme a necessidade da operação.
        </p>
        <div class="alert alert-light border text-center mt-3 mb-0" role="note">
            <strong>A franquia de mensagens corresponde ao uso do Disparador.net.</strong>
            Tarifas da WhatsApp Business Platform cobradas pela Meta não estão incluídas na mensalidade e seguem a política vigente da Meta.
        </div>
        <div class="row justify-content-center">
            <div class="col-lg-9 text-center">
                <p class="text-center text-muted mt-3 mb-0">
                    <h2 class="site-section-title">Seu negócio nunca para</h2>
                    Todos os planos incluem uma franquia de mensagens.<br />
                    Caso ela seja ultrapassada, o envio continua normalmente e apenas as mensagens excedentes são cobradas conforme o consumo.
                </p>
            </div>
        </div>
    </div>

</section>

<?php } ?>
<?php if(false){ ?>
<section class="py-5 bg-light" id="custos-meta-legado">

    <div class="container">

        <div class="row justify-content-center">

            <div class="col-lg-9 text-center">
                <span class="badge badge-success mb-3">Tarifas da Meta</span>
                <h2 class="site-section-title">Limites de envio e cobrança são diferentes</h2>
                <p class="text-muted">
                    O Disparador.net cobra pela utilização de sua plataforma, com mensalidade e franquia conforme o plano contratado. Separadamente, a Meta cobra pelo uso da WhatsApp Business Platform segundo regras e tarifas próprias, que podem variar conforme a categoria da mensagem, o mercado do destinatário, a política vigente e eventuais faixas de volume.
                </p>
                <p class="text-muted small">
                    As tarifas da Meta não estão incluídas na mensalidade nem na franquia do Disparador.net. O Disparador.net não revende créditos da Meta e não define essas regras ou tarifas.
                </p>
                <div class="alert alert-light border text-left mt-4 mb-0" role="note">
                    <h3 class="h5 font-weight-bold">Atualização na política de cobrança da Meta a partir de 1º de outubro de 2026</h3>
                    <p>
                        Até 30 de setembro de 2026, mensagens de Serviço enviadas pela empresa durante a janela de atendimento de 24 horas e templates de Utilidade enviados nessa janela têm tratamento gratuito segundo a política atual aplicável.
                    </p>
                    <p>
                        A partir de 1º de outubro de 2026, cada número comercial terá uma franquia mensal de 1.000 mensagens de Serviço sem tarifa da Meta; a cobrança começa na 1.001ª mensagem de Serviço do mês. Essa franquia vale somente para Serviço e não se acumula para o mês seguinte. Templates de Utilidade enviados dentro ou fora da janela de atendimento passam a ser cobrados por mensagem e não utilizam essa franquia. Mensagens de Marketing e Autenticação continuam sujeitas às respectivas tarifas.
                    </p>
                    <p class="mb-0">
                        A alteração é definida pela Meta e não representa aumento da mensalidade do Disparador.net. A janela gratuita de 72 horas iniciada por anúncio ou botão elegível continua sujeita às condições oficiais da Meta. Tarifas-base, descontos por volume, franquias e outras exceções devem ser conferidos na documentação vigente.
                    </p>
                </div>
            </div>

        </div>

    </div>

</section>
<?php } ?>

<section class="py-5 bg-light" id="custos-meta"><div class="container"><div class="row justify-content-center"><div class="col-lg-9 text-center"><span class="badge badge-success mb-3">Cobrança transparente</span><h2 class="site-section-title">Mensalidade do Disparador + tarifas oficiais da Meta</h2><p class="lead text-muted">O plano cobre o uso da plataforma Disparador.net. As tarifas cobradas pela Meta pelo uso do WhatsApp Business Platform são separadas e seguem a política oficial vigente.</p><a class="btn btn-outline-success" href="<?= BASE_URL; ?>/precos-whatsapp-meta" data-analytics-event="precos_whatsapp_meta">Entenda como funciona a cobrança</a></div></div></div></section>

<section id="faq" class="py-5">

    <div class="container">

        <div class="text-center mb-5">

            <h2 class="site-section-title">
                Perguntas frequentes
            </h2>

        </div>

        <div class="row justify-content-center">

            <div class="col-md-8">


                <?php foreach($perguntasFrequentes as $pergunta => $resposta){ ?>
                    <div class="site-faq-item">
                        <h3 class="h5"><?= htmlspecialchars($pergunta); ?></h3>
                        <p class="text-muted">
                            <?= htmlspecialchars($resposta); ?>
                        </p>
                    </div>
                <?php } ?>

            </div>

        </div>

    </div>

</section>


<section class="py-5 bg-light">

    <div class="container">

        <div class="row justify-content-center">

            <div class="col-md-8 text-center">

                <span class="badge badge-success mb-3">
                    Credibilidade
                </span>

                <h2 class="site-section-title">
                    Uma plataforma da RL2 Net
                </h2>

                <p class="text-muted mb-0">
                    O Disparador.net é desenvolvido e mantido pela RL2 Net, empresa com operação em Curitiba/PR e foco em soluções digitais para pequenas e médias empresas.
                </p>

            </div>

        </div>

    </div>

</section>

<section class="site-final-cta">

    <div class="container text-center">

        <h2 class="font-weight-bold">
            Pronto para profissionalizar seu WhatsApp?
        </h2>

        <p class="lead">
            Cadastre sua empresa e comece a organizar campanhas, contatos e conversas pela API Oficial do WhatsApp Business. O teste começa após a conexão válida do primeiro número e dura até 7 dias ou 200 mensagens, o que ocorrer primeiro.
        </p>

        <a
        href="<?= BASE_URL; ?>/index.php?url=site/cadastro"
        class="btn btn-light btn-lg"
        data-analytics-event="select_trial"
        data-analytics-location="final_cta"
        data-analytics-destination="registration"
        >
            Começar teste grátis
        </a>

    </div>

</section>

<footer class="py-4 bg-dark text-white">

    <div class="container">

        <div class="row align-items-center">

            <div class="col-md-6 text-center text-md-left mb-2 mb-md-0">

                © 2026 RL2 Net - Todos os direitos reservados.<br>
                Disparador.net é uma plataforma da RL2 Net.<br>
                Contato: contato@disparador.net<br>
                <small>WhatsApp e Meta são marcas comerciais de seus respectivos proprietários. O Disparador.net é uma plataforma independente que utiliza a API oficial do WhatsApp Business.</small>

            </div>

            <div class="col-md-6 text-center text-md-right">

                <a class="text-white mr-3" href="<?= BASE_URL; ?>/index.php?url=site/politicaPrivacidade">
                    Política de Privacidade
                </a>

                <a class="text-white mr-3" href="<?= BASE_URL; ?>/index.php?url=site/termosUso">
                    Termos de Uso
                </a>

                <a class="text-white" href="<?= BASE_URL; ?>/index.php?url=site/politicaCancelamento">
                    Política de Cancelamento e Reembolso
                </a>

            </div>

        </div>

    </div>

</footer>

<?php $analyticsWhatsappLocation = 'landing'; require __DIR__ . '/partials/whatsapp_button.php'; ?>

<script>

document.addEventListener('DOMContentLoaded', function(){

    window.Disparador.analytics.push('view_home', {
        page_type: 'home',
        source_area: 'public_site'
    });

    const secaoPlanos = document.getElementById('planos');
    let planosVisualizados = false;

    function registrarVisualizacaoPlanos()
    {
        if(planosVisualizados){ return; }
        planosVisualizados = true;
        window.Disparador.analytics.push('view_pricing', {
            page_type: 'home',
            section: 'pricing'
        });
    }

    if(secaoPlanos && 'IntersectionObserver' in window){
        const observadorPlanos = new IntersectionObserver(function(entradas, observador){
            if(entradas.some(function(entrada){ return entrada.isIntersecting; })){
                registrarVisualizacaoPlanos();
                observador.disconnect();
            }
        }, {threshold: 0.35});
        observadorPlanos.observe(secaoPlanos);
    }

    const sitePlanosCarousel =
        document.getElementById('sitePlanosCarousel');

    const sitePlanosAnterior =
        document.getElementById('sitePlanosAnterior');

    const sitePlanosProximo =
        document.getElementById('sitePlanosProximo');

    function atualizarControlesPlanosSite()
    {
        if(!sitePlanosCarousel || !sitePlanosAnterior || !sitePlanosProximo){
            return;
        }

        const maxScroll =
            sitePlanosCarousel.scrollWidth - sitePlanosCarousel.clientWidth;

        const deveExibirControles =
            maxScroll > 2;

        sitePlanosAnterior.style.display =
            deveExibirControles ? 'inline-flex' : 'none';

        sitePlanosProximo.style.display =
            deveExibirControles ? 'inline-flex' : 'none';

        sitePlanosAnterior.disabled =
            sitePlanosCarousel.scrollLeft <= 2;

        sitePlanosProximo.disabled =
            sitePlanosCarousel.scrollLeft >= (maxScroll - 2);
    }

    function rolarPlanosSite(direcao)
    {
        if(!sitePlanosCarousel){
            return;
        }

        const item =
            sitePlanosCarousel.querySelector('.site-plano-carousel-item');

        const deslocamento =
            item
                ? item.getBoundingClientRect().width + 24
                : sitePlanosCarousel.clientWidth;

        sitePlanosCarousel.scrollBy({
            left: direcao * deslocamento,
            behavior: 'smooth'
        });
    }

    if(sitePlanosAnterior){
        sitePlanosAnterior.addEventListener('click', function(){
            rolarPlanosSite(-1);
        });
    }

    if(sitePlanosProximo){
        sitePlanosProximo.addEventListener('click', function(){
            rolarPlanosSite(1);
        });
    }

    if(sitePlanosCarousel){
        sitePlanosCarousel.addEventListener('scroll', function(){
            window.requestAnimationFrame(atualizarControlesPlanosSite);
        });
    }

    window.addEventListener('resize', atualizarControlesPlanosSite);
    atualizarControlesPlanosSite();


});

</script>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
