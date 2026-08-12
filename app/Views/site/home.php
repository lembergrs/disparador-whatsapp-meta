<?php
$perguntasFrequentes = [
    'O Disparador.net utiliza a API Oficial do WhatsApp?' => 'Sim. A integração utiliza a WhatsApp Business Platform, e a conta e o número passam pelos processos de conexão e validação da Meta.',
    'Quando começa o período de avaliação?' => 'O período de avaliação começa somente após a validação da primeira conexão do WhatsApp Business.',
    'Preciso contratar um plano antes de conectar o primeiro número?' => 'Não. O cliente elegível ao pré-trial pode conectar o primeiro número para iniciar a avaliação.',
    'Quem define os limites de envio?' => 'Os limites de envio são definidos e administrados pela Meta conforme os critérios aplicáveis à conta e ao número.',
    'O plano do Disparador aumenta automaticamente meu limite na Meta?' => 'Não. O limite do plano do Disparador.net e as faixas administradas pela Meta são capacidades diferentes.',
    'A Meta cobra pelas mensagens?' => 'A Meta cobra pelo uso da WhatsApp Business Platform conforme sua política vigente. A cobrança pode variar de acordo com a categoria da mensagem e o mercado do destinatário. A partir de 1º de outubro de 2026, mensagens de Serviço — como respostas enviadas pela empresa durante a janela de atendimento de 24 horas — e templates de Utilidade enviados nessa janela também passam a ser cobrados pela Meta. Esses valores são independentes da mensalidade e da franquia de mensagens do Disparador.net.',
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

<section class="site-hero-v2">

    <div class="container">

        <div class="row align-items-center">

            <div class="col-lg-6">

                <span class="badge badge-success mb-3">
                    API Oficial da Meta • Mais segurança e estabilidade
                </span>

                <h1 class="site-hero-title">
                    Transforme seu WhatsApp em uma plataforma de
                    <span>vendas e atendimento</span>
                </h1>

                <p class="site-hero-text mt-4">
                    Crie campanhas, organize contatos, atenda clientes, use templates oficiais e gerencie múltiplos números em um único sistema conectado à API Oficial da Meta.
                </p>

                <div class="mt-4">

                    <a
                    href="<?= BASE_URL; ?>/index.php?url=site/cadastro"
                    class="btn btn-success btn-lg site-btn-main shadow-sm"
                    data-analytics-event="select_trial"
                    data-analytics-location="hero"
                    data-analytics-destination="registration"
                    >
                        Começar teste grátis
                    </a>

                    <a
                    href="#como-funciona"
                    class="btn btn-outline-secondary btn-lg site-btn-outline ml-lg-2 mt-2 mt-lg-0"
                    >
                        Ver como funciona
                    </a>

                </div>

                <div class="mt-3 small text-muted">

                    Teste grátis por até 7 dias ou 200 mensagens.

                </div>

                <div class="mt-3 text-muted">

                    <span class="mr-3">
                        <i class="fas fa-check text-success"></i>
                        Sem WhatsApp Web
                    </span>

                    <span class="mr-3">
                        <i class="fas fa-check text-success"></i>
                        Templates oficiais
                    </span>

                    <span>
                        <i class="fas fa-check text-success"></i>
                        Sem celular conectado
                    </span>

                </div>

            </div>

            <div class="col-lg-6 mt-5 mt-lg-0">

                <div class="site-dashboard-mockup">

                    <div class="site-mockup-top">
                        Demonstração da Plataforma
                    </div>

                    <div class="site-mockup-body">

                        <div class="mb-3">

                            <small class="text-muted">
                                Recursos principais
                            </small>

                            <h5 class="font-weight-bold mb-0">
                                Tudo para campanhas e atendimento em um só lugar
                            </h5>

                        </div>

                        <div class="row">

                            <div class="col-md-6">

                                <div class="site-mini-card">
                                    <i class="fas fa-bullhorn text-success mr-2"></i>
                                    <strong>Campanhas WhatsApp</strong>
                                </div>

                            </div>

                            <div class="col-md-6">

                                <div class="site-mini-card">
                                    <i class="fas fa-comments text-success mr-2"></i>
                                    <strong>Central de Conversas</strong>
                                </div>

                            </div>

                            <div class="col-md-6">

                                <div class="site-mini-card">
                                    <i class="fas fa-file-alt text-success mr-2"></i>
                                    <strong>Templates Oficiais</strong>
                                </div>

                            </div>

                            <div class="col-md-6">

                                <div class="site-mini-card">
                                    <i class="fas fa-list text-success mr-2"></i>
                                    <strong>Listas de Contatos</strong>
                                </div>

                            </div>

                        </div>

                        <div class="site-mini-card">

                            <strong>
                                Operação profissional
                            </strong>

                            <div class="mt-3">

                                <p class="mb-2">
                                    <i class="fas fa-check-circle text-success"></i>
                                    Multiatendimento para organizar o trabalho da equipe
                                </p>

                                <p class="mb-0">
                                    <i class="fas fa-check-circle text-success"></i>
                                    API Oficial da Meta para campanhas e atendimento
                                </p>

                            </div>

                        </div>

                        <div class="site-mini-card mb-0">

                            <div class="d-flex align-items-center">

                                <i class="fab fa-whatsapp fa-2x text-success mr-3"></i>

                                <div>
                                    <strong>Disparador.net WhatsApp Business</strong><br>
                                    <small class="text-muted">
                                        Plataforma preparada para campanhas, atendimento e múltiplos números.
                                    </small>
                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

</section>

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

                O Disparador.net utiliza a API Oficial do WhatsApp Business Platform para campanhas, atendimento e templates oficiais, sem WhatsApp Web e sem celular conectado.

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
                                    Sem celular conectado
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
                    'titulo' => 'Campanhas WhatsApp',
                    'texto' => 'Alcance seus clientes com campanhas usando templates oficiais aprovados pela Meta.'
                ],
                [
                    'icon' => 'fas fa-file-alt',
                    'titulo' => 'Templates oficiais',
                    'texto' => 'Crie, sincronize e utilize modelos aprovados para iniciar conversas com segurança.'
                ],
                [
                    'icon' => 'fas fa-list',
                    'titulo' => 'Listas de contatos',
                    'texto' => 'Importe contatos, organize públicos e segmente campanhas por listas.'
                ],
                [
                    'icon' => 'fas fa-comments',
                    'titulo' => 'Central de conversas',
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
                <p class="text-muted">Vincule seu número à plataforma oficial da Meta.</p>
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
                Todo novo cliente tem <strong>50% de desconto no primeiro pagamento</strong>. Depois, como cliente, você pode indicar novas empresas e receber <strong><?= htmlspecialchars($percentualIndicacao, ENT_QUOTES, 'UTF-8'); ?>% de desconto em mensalidades futuras elegíveis</strong> por indicação confirmada, conforme as condições do programa.
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

<section id="faixas-meta" class="py-5 site-meta-tiers">

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

<section id="planos" class="py-5">

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
                    $valorPrimeiroPagamento = $valorMensal / 2;
                    ?>

                    <div class="site-plano-carousel-item">

                        <div class="card border-<?= $corPlano; ?> h-100">

                            <div class="card-body p-4 text-center">

                                <span class="badge badge-<?= $corPlano; ?> mb-3">
                                    <?= htmlspecialchars($plano['PLA_Nome']); ?>
                                </span>

                                <p class="text-success font-weight-bold mb-1">
                                    <span class="site-valor-primeiro-pagamento">R$ <?= number_format($valorPrimeiroPagamento, 2, ',', '.'); ?></span><span> no primeiro pagamento</span>
                                </p>

                                <p class="text-muted mb-1">
                                    <del>R$ <?= number_format($valorMensal, 2, ',', '.'); ?></del>
                                </p>

                                <p class="mb-3">
                                    A partir do 2º mês: <strong>R$ <?= number_format($valorMensal, 2, ',', '.'); ?>/mês</strong>
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

<section class="py-5 bg-light" id="custos-meta">

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
                        A partir de 1º de outubro de 2026, a Meta anunciou que mensagens de Serviço, incluindo respostas enviadas pela empresa durante essa janela, e templates de Utilidade enviados nela passarão a ser cobrados por mensagem. Mensagens de Marketing e Autenticação continuam sujeitas às respectivas tarifas.
                    </p>
                    <p class="mb-0">
                        A alteração é definida pela Meta e não representa aumento da mensalidade do Disparador.net. Mensagens recebidas do cliente não são apresentadas como mensagens cobradas pelo Disparador.net, e exceções e janelas gratuitas continuam sujeitas às regras oficiais vigentes da Meta.
                    </p>
                </div>
            </div>

        </div>

    </div>

</section>

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
