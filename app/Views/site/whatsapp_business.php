<?php
$perguntasFrequentes = [
    'Vou deixar de usar o WhatsApp Business no celular?' => 'Não. Para números elegíveis, a Meta pode permitir que o mesmo número continue no aplicativo WhatsApp Business e também seja conectado ao Disparador.net.',
    'Preciso trocar meu número?' => 'Não necessariamente. Durante a conexão, a Meta apresenta as opções disponíveis para o seu número. Você também pode configurar um novo número quando essa opção estiver disponível.',
    'Preciso desinstalar o WhatsApp Business?' => 'Não. Siga as etapas apresentadas pela Meta durante a conexão. Para um número elegível, o aplicativo WhatsApp Business continua fazendo parte da operação.',
    'Posso continuar usando WhatsApp Web e WhatsApp Desktop?' => 'Sim, mas dispositivos vinculados podem ser desconectados durante a integração. Depois de concluir, você poderá vinculá-los novamente pelo WhatsApp Business.',
    'Vou perder minhas conversas?' => 'A conexão não significa que suas conversas no aplicativo serão apagadas. A disponibilidade de mensagens anteriores no Disparador depende dos recursos e das condições disponibilizadas pela Meta, por isso não é possível garantir a importação integral do histórico.',
    'Todas as contas podem usar essa modalidade?' => 'Não. A disponibilidade depende da elegibilidade do número e da conta, definida pela Meta. As opções disponíveis aparecem durante o processo de conexão.',
    'O que é Coexistence?' => 'É o nome técnico usado pela Meta para o recurso que permite, quando elegível, utilizar o WhatsApp Business App e a API Oficial com o mesmo número.',
    'Essa integração é oficial?' => 'Sim. A conexão é realizada pelo processo oficial da Meta e utiliza a infraestrutura da WhatsApp Business Platform.',
    'Posso conectar um número novo ao Disparador?' => 'Sim. O botão Conectar WhatsApp abre o processo da Meta, que apresenta as opções disponíveis para conectar um número existente elegível ou configurar um novo número.'
];

$faqSchema = [];
foreach($perguntasFrequentes as $pergunta => $resposta){
    $faqSchema[] = [
        '@type' => 'Question',
        'name' => $pergunta,
        'acceptedAnswer' => ['@type'=>'Answer', 'text'=>$resposta]
    ];
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <?php $googleTagManagerSection = 'head'; require __DIR__ . '/../partials/google_tag_manager.php'; ?>
    <meta charset="UTF-8">
    <title>Conecte seu WhatsApp Business à API Oficial | Disparador.net</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Conecte um número elegível do WhatsApp Business ao Disparador.net pela API Oficial da Meta e continue utilizando o aplicativo no celular.">
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
    <meta name="author" content="Disparador.net">
    <link rel="canonical" href="https://disparador.net/whatsapp-business">
    <link rel="icon" type="image/x-icon" href="<?= ASSET_URL; ?>/img/favicon.ico?v=1">
    <meta name="theme-color" content="#08a63f">

    <meta property="og:locale" content="pt_BR">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Disparador.net">
    <meta property="og:title" content="Use seu WhatsApp Business junto com o Disparador.net">
    <meta property="og:description" content="Continue utilizando um número elegível no WhatsApp Business e conecte-o aos recursos do Disparador.net pela integração oficial da Meta.">
    <meta property="og:url" content="https://disparador.net/whatsapp-business">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Use seu WhatsApp Business junto com o Disparador.net">
    <meta name="twitter:description" content="Conheça a conexão oficial que permite usar um número elegível no WhatsApp Business e no Disparador.net.">

    <script type="application/ld+json"><?= json_encode([
        '@context'=>'https://schema.org',
        '@type'=>'WebPage',
        'name'=>'Use seu WhatsApp Business junto com o Disparador.net',
        'url'=>'https://disparador.net/whatsapp-business',
        'description'=>'Como conectar um número elegível do WhatsApp Business ao Disparador.net pela API Oficial da Meta.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
    <script type="application/ld+json"><?= json_encode([
        '@context'=>'https://schema.org',
        '@type'=>'FAQPage',
        'mainEntity'=>$faqSchema
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="<?= ASSET_URL; ?>/css/style.css?v=14">
</head>
<body>
<?php $googleTagManagerSection = 'body'; require __DIR__ . '/../partials/google_tag_manager.php'; ?>

<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm fixed-top site-navbar">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="<?= BASE_URL; ?>/">
            <img src="<?= ASSET_URL; ?>/img/logo-disparador.png" alt="Disparador.net" width="1136" height="247" class="site-logo">
        </a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#menuWhatsappBusiness" aria-controls="menuWhatsappBusiness" aria-expanded="false" aria-label="Abrir menu de navegação">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="menuWhatsappBusiness">
            <ul class="navbar-nav ml-auto align-items-lg-center site-main-nav">
                <li class="nav-item"><a class="nav-link" href="#como-funciona">Como funciona</a></li>
                <li class="nav-item"><a class="nav-link" href="#dispositivos">Dispositivos</a></li>
                <li class="nav-item"><a class="nav-link" href="#faq">FAQ</a></li>
                <li class="nav-item site-nav-action"><a class="btn btn-outline-success site-nav-button" href="<?= BASE_URL; ?>/index.php?url=login">Entrar</a></li>
                <li class="nav-item site-nav-action"><a class="btn btn-success ml-lg-2 site-btn-main" href="<?= BASE_URL; ?>/index.php?url=site/cadastro" data-analytics-event="select_trial" data-analytics-location="whatsapp_business_header" data-analytics-destination="registration">Começar agora</a></li>
            </ul>
        </div>
    </div>
</nav>

<main>
    <section class="site-hero-v2 whatsapp-business-hero">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-7">
                    <span class="badge badge-success mb-3">Integração oficial da Meta</span>
                    <h1 class="site-hero-title">Use seu WhatsApp Business junto com o Disparador.net</h1>
                    <p class="site-hero-text mt-4">
                        Números elegíveis podem continuar funcionando no WhatsApp Business no celular e também aproveitar campanhas, contatos, atendimento e gestão no Disparador.net.
                    </p>
                    <p class="text-muted">
                        Você não precisa abandonar o aplicativo. Clique em Conectar WhatsApp e a Meta apresentará as opções disponíveis para seu número.
                    </p>
                    <div class="mt-4">
                        <a href="<?= BASE_URL; ?>/index.php?url=site/cadastro" class="btn btn-success btn-lg site-btn-main shadow-sm" data-analytics-event="select_trial" data-analytics-location="whatsapp_business_hero" data-analytics-destination="registration">Começar agora</a>
                        <a href="#como-funciona" class="btn btn-outline-secondary btn-lg site-btn-outline ml-lg-2 mt-2 mt-lg-0">Entender a conexão</a>
                    </div>
                </div>
                <div class="col-lg-5 mt-5 mt-lg-0">
                    <div class="site-dashboard-mockup whatsapp-business-benefit-card">
                        <div class="site-mockup-top">Um número, mais possibilidades</div>
                        <div class="site-mockup-body">
                            <p><i class="fas fa-mobile-alt text-success mr-2"></i><strong>WhatsApp Business no celular</strong></p>
                            <p><i class="fas fa-plus-circle text-success mr-2"></i><strong>Recursos do Disparador.net</strong></p>
                            <p class="mb-0"><i class="fas fa-shield-alt text-success mr-2"></i><strong>Infraestrutura oficial da Meta</strong></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5 bg-white" aria-labelledby="mesmo-numero-titulo">
        <div class="container">
            <div class="text-center mb-5">
                <span class="badge badge-success mb-3">Mesmo número</span>
                <h2 id="mesmo-numero-titulo" class="site-section-title">O aplicativo e o Disparador trabalhando juntos</h2>
                <p class="text-muted mx-auto site-section-lead">Quando essa opção estiver disponível para seu número, você mantém o uso cotidiano no celular e acrescenta os recursos do Disparador.net.</p>
            </div>
            <div class="whatsapp-business-diagram" aria-label="O mesmo número conectado ao WhatsApp Business no celular e ao Disparador.net pela API Oficial da Meta">
                <div class="whatsapp-business-number"><i class="fab fa-whatsapp"></i><strong>Mesmo número</strong></div>
                <div class="whatsapp-business-branches" aria-hidden="true"></div>
                <div class="row justify-content-center">
                    <div class="col-md-5 mb-4 mb-md-0">
                        <div class="card site-feature-card h-100 text-center"><div class="card-body p-4">
                            <div class="site-feature-icon mx-auto"><i class="fas fa-mobile-alt"></i></div>
                            <h3 class="h4 font-weight-bold">WhatsApp Business no celular</h3>
                            <p class="text-muted mb-1">Uso cotidiano</p><p class="text-muted mb-0">Atendimento pelo aplicativo</p>
                        </div></div>
                    </div>
                    <div class="col-md-5">
                        <div class="card site-feature-card h-100 text-center"><div class="card-body p-4">
                            <div class="site-feature-icon mx-auto"><i class="fas fa-paper-plane"></i></div>
                            <h3 class="h4 font-weight-bold">Disparador.net</h3>
                            <p class="text-muted mb-1">Campanhas e contatos</p><p class="text-muted mb-1">Atendimento em equipe</p><p class="text-muted mb-0">Gestão pela API Oficial da Meta</p>
                        </div></div>
                    </div>
                </div>
            </div>
            <p class="text-center text-muted small mt-4 mb-0">Esse recurso da Meta é conhecido tecnicamente como Coexistence.</p>
        </div>
    </section>

    <section class="py-5 bg-light" id="como-funciona">
        <div class="container">
            <div class="text-center mb-5"><h2 class="site-section-title">Como funciona</h2><p class="text-muted">A conexão acontece no ambiente seguro apresentado pela Meta.</p></div>
            <div class="row">
                <?php foreach([
                    ['Clique em “Conectar WhatsApp”','No Disparador.net, existe uma única ação para iniciar a conexão.'],
                    ['Siga o processo da Meta','Entre com sua conta e confira as opções disponibilizadas para seu número.'],
                    ['Conclua as etapas apresentadas','Para um WhatsApp Business existente, a Meta poderá solicitar etapas adicionais, inclusive leitura de QR Code quando aplicável.'],
                    ['Finalize a conexão','A modalidade correta é identificada automaticamente ao final do processo.'],
                    ['Use os dois ambientes','Continue no WhatsApp Business e passe também a utilizar os recursos disponíveis no Disparador.net.']
                ] as $indice=>$passo){ ?>
                    <div class="col-md-6 col-lg mb-4"><div class="card site-feature-card h-100"><div class="card-body p-4"><div class="site-step"><?= $indice + 1; ?></div><h3 class="h5 font-weight-bold"><?= htmlspecialchars($passo[0]); ?></h3><p class="text-muted mb-0"><?= htmlspecialchars($passo[1]); ?></p></div></div></div>
                <?php } ?>
            </div>
        </div>
    </section>

    <section class="py-5 bg-white" id="dispositivos">
        <div class="container"><div class="row justify-content-center"><div class="col-lg-9">
            <div class="card whatsapp-business-device-card"><div class="card-body p-4 p-md-5">
                <div class="d-md-flex align-items-start"><div class="site-feature-icon flex-shrink-0 mr-md-4"><i class="fas fa-desktop"></i></div><div>
                    <h2 class="site-section-title h3">O que acontece com WhatsApp Web e outros dispositivos?</h2>
                    <p>Ao conectar um número já utilizado no WhatsApp Business à API Oficial, dispositivos vinculados, como WhatsApp Web ou WhatsApp Desktop, podem ser desconectados durante o processo.</p>
                    <p class="mb-0 text-muted">Depois de finalizar a integração, esses dispositivos podem ser vinculados novamente pelo WhatsApp Business.</p>
                </div></div>
            </div></div>
        </div></div></div>
    </section>

    <section class="py-5 bg-light">
        <div class="container"><div class="row justify-content-center"><div class="col-lg-9 text-center">
            <span class="badge badge-success mb-3">Disponibilidade</span>
            <h2 class="site-section-title">A Meta apresenta as opções para cada número</h2>
            <p class="text-muted mb-0">A possibilidade de manter o número no WhatsApp Business depende da elegibilidade e das condições definidas pela Meta. O Disparador.net não promete disponibilidade para todas as contas nem importação integral de mensagens anteriores.</p>
        </div></div></div>
    </section>

    <section class="py-5 bg-white" id="faq">
        <div class="container"><div class="text-center mb-5"><h2 class="site-section-title">Perguntas frequentes</h2></div><div class="row justify-content-center"><div class="col-lg-8">
            <?php foreach($perguntasFrequentes as $pergunta=>$resposta){ ?><div class="site-faq-item"><h3 class="h5"><?= htmlspecialchars($pergunta); ?></h3><p class="text-muted mb-0"><?= htmlspecialchars($resposta); ?></p></div><?php } ?>
        </div></div></div>
    </section>

    <section class="site-final-cta">
        <div class="container text-center"><h2 class="font-weight-bold">Leve seu WhatsApp Business para o Disparador.net</h2><p class="lead">Conecte seu número pela integração oficial da Meta e descubra as opções disponíveis para sua operação.</p><a href="<?= BASE_URL; ?>/index.php?url=site/cadastro" class="btn btn-light btn-lg" data-analytics-event="select_trial" data-analytics-location="whatsapp_business_final_cta" data-analytics-destination="registration">Começar agora</a></div>
    </section>
</main>

<footer class="py-4 bg-dark text-white"><div class="container"><div class="row align-items-center"><div class="col-md-6 text-center text-md-left mb-3 mb-md-0">© 2026 RL2 Net - Todos os direitos reservados.<br><small>WhatsApp e Meta são marcas comerciais de seus respectivos proprietários.</small></div><div class="col-md-6 text-center text-md-right"><a class="text-white mr-3" href="<?= BASE_URL; ?>/index.php?url=site/politicaPrivacidade">Política de Privacidade</a><a class="text-white mr-3" href="<?= BASE_URL; ?>/index.php?url=site/termosUso">Termos de Uso</a><a class="text-white" href="<?= BASE_URL; ?>/">Início</a></div></div></div></footer>

<?php $analyticsWhatsappLocation = 'whatsapp_business_page'; require __DIR__ . '/partials/whatsapp_button.php'; ?>
<script>document.addEventListener('DOMContentLoaded',function(){window.Disparador.analytics.push('view_whatsapp_business_page',{page_type:'whatsapp_business',source_area:'public_site'});});</script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
