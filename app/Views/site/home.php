<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">

    <title>Disparador.net | Plataforma Oficial para WhatsApp Business</title>

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="icon" type="image/x-icon" href="<?= ASSET_URL ?>/img/favicon.ico?v=1">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= ASSET_URL ?>/img/favicon.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= ASSET_URL ?>/img/favicon.png">
    <meta name="theme-color" content="#28A745">

    <meta
    name="description"
    content="Envie campanhas oficiais pelo WhatsApp, organize contatos e centralize o atendimento da sua empresa utilizando a API Oficial da Meta."
    >

    <meta property="og:title" content="Disparador.net | Plataforma Oficial para WhatsApp Business">
    <meta
    property="og:description"
    content="Envie campanhas oficiais pelo WhatsApp, organize contatos e centralize o atendimento da sua empresa utilizando a API Oficial da Meta."
    >
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= BASE_URL; ?>/index.php?url=site">

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
    href="<?= ASSET_URL; ?>/css/style.css?v=10"
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
    }
    </style>
</head>

<body>
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm fixed-top site-navbar">

    <div class="container">

        <a class="navbar-brand d-flex align-items-center" href="<?= BASE_URL; ?>/index.php?url=site">
            <img
            src="<?= ASSET_URL; ?>/img/logo-disparador.png"
            alt="Disparador.net"
            class="site-logo"
            >
        </a>

        <button
        class="navbar-toggler"
        type="button"
        data-toggle="collapse"
        data-target="#menuSite"
        >
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="menuSite">

            <ul class="navbar-nav ml-auto align-items-lg-center">

                <li class="nav-item">
                    <a class="nav-link" href="#recursos">Recursos</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="#como-funciona">Ver como funciona</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="#planos">Planos</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="#faq">FAQ</a>
                </li>

                <li class="nav-item">
                    <a
                    class="btn btn-outline-success ml-lg-3"
                    href="<?= BASE_URL; ?>/index.php?url=login"
                    >
                        Entrar
                    </a>
                </li>

                <li class="nav-item">
                    <a
                    class="btn btn-success ml-lg-2 site-btn-main"
                    href="<?= BASE_URL; ?>/index.php?url=site/cadastro"
                    >
                        Solicitar acesso
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
                    Plataforma Oficial do WhatsApp Business
                </span>

                <h1 class="site-hero-title">
                    Campanhas oficiais e atendimento centralizado pelo WhatsApp.
                </h1>

                <p class="site-hero-text mt-4">
                    Use a API Oficial da Meta para enviar campanhas, organizar contatos e atender clientes em uma única plataforma.
                </p>

                <div class="mt-4">

                    <a
                    href="<?= BASE_URL; ?>/index.php?url=site/cadastro"
                    class="btn btn-success btn-lg site-btn-main"
                    >
                        Solicitar acesso
                    </a>

                    <a
                    href="#como-funciona"
                    class="btn btn-outline-secondary btn-lg site-btn-outline ml-lg-2 mt-2 mt-lg-0"
                    >
                        Ver como funciona
                    </a>

                </div>

                <div class="mt-4 text-muted">

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
                    ?>

                    <div class="site-plano-carousel-item">

                        <div class="card border-<?= $corPlano; ?> h-100">

                            <div class="card-body p-4 text-center">

                                <span class="badge badge-<?= $corPlano; ?> mb-3">
                                    <?= htmlspecialchars($plano['PLA_Nome']); ?>
                                </span>

                                <h4 class="font-weight-bold">
                                    R$ <?= number_format($valorMensal, 2, ',', '.'); ?>/mês
                                </h4>

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
                                >
                                    Solicitar acesso
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

    </div>

</section>

<section class="py-5 bg-light">

    <div class="container">

        <div class="row align-items-center">

            <div class="col-md-6">

                <span class="badge badge-success mb-3">
                    Simulador de custos
                </span>

                <h2 class="site-section-title">
                    Simule o custo estimado das mensagens da Meta
                </h2>

                <p class="text-muted">
                    Informe o tipo de mensagem e a quantidade estimada para planejar o custo aproximado que a Meta pode cobrar conforme categoria, país e regras vigentes.
                </p>

                <p class="text-muted small">
                    A mensalidade do Disparador.net é separada e não está incluída nesta simulação.
                </p>

            </div>

            <div class="col-md-6">

                <div class="card site-card-feature">

                    <div class="card-body p-4">

                        <div class="form-group">

                            <label>Tipo de mensagem</label>

                            <select
                            id="simuladorTipoMensagem"
                            class="form-control"
                            >
                                <option value="marketing">
                                    Marketing
                                </option>

                                <option value="utility">
                                    Utilidade
                                </option>

                                <option value="authentication">
                                    Autenticação
                                </option>
                            </select>

                        </div>

                        <div class="form-group">

                            <label>Quantidade estimada de mensagens</label>

                            <input
                            type="number"
                            id="simuladorMensagens"
                            class="form-control"
                            value="1000"
                            min="0"
                            >

                        </div>

                        <div
                        class="alert alert-success mt-3"
                        id="resultadoSimulador"
                        >
                            Informe os dados para calcular.
                        </div>

                        <small class="text-muted">
                            Esta é apenas uma estimativa, não um valor final garantido. A cobrança real pode variar conforme categoria, país do destinatário, moeda, regras vigentes e mensagens efetivamente entregues.
                        </small>

                    </div>

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

                <?php
                $perguntasFrequentes = [
                    'O Disparador.net usa WhatsApp Web?' => 'Não. A plataforma opera com a API Oficial do WhatsApp Business da Meta.',
                    'Preciso deixar o celular ligado?' => 'Não. A operação acontece pela infraestrutura oficial da Meta, sem depender de sessão aberta em navegador.',
                    'Posso usar meu número atual?' => 'Sim, desde que o número atenda aos requisitos da Meta para conexão com a API Oficial do WhatsApp Business.',
                    'Posso importar contatos?' => 'Sim. Você pode importar contatos, criar listas e organizar públicos para campanhas.',
                    'Posso ter mais de um atendente?' => 'Sim. Os planos permitem diferentes quantidades de usuários para atendimento.',
                    'Posso ter mais de um número WhatsApp?' => 'Sim. A plataforma permite múltiplos números conforme o plano contratado.',
                    'A Meta cobra pelas mensagens?' => 'A Meta pode cobrar pelas mensagens conforme categoria, país e regras vigentes. O Disparador.net mostra uma estimativa para ajudar no planejamento.',
                    'Como solicito acesso?' => 'Preencha o cadastro para que a equipe avalie a configuração necessária e oriente os próximos passos de ativação.',
                ];
                ?>

                <?php foreach($perguntasFrequentes as $pergunta => $resposta){ ?>
                    <div class="site-faq-item">
                        <h5><?= htmlspecialchars($pergunta); ?></h5>
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
            Cadastre sua empresa e comece a organizar campanhas, contatos e conversas em uma plataforma oficial.
        </p>

        <a
        href="<?= BASE_URL; ?>/index.php?url=site/cadastro"
        class="btn btn-light btn-lg"
        >
            Solicitar acesso
        </a>

    </div>

</section>

<footer class="py-4 bg-dark text-white">

    <div class="container">

        <div class="row align-items-center">

            <div class="col-md-6 text-center text-md-left mb-2 mb-md-0">

                © 2026 RL2 Net - Todos os direitos reservados.<br>
                Disparador.net é uma plataforma da RL2 Net.<br>
                Contato: contato@disparador.net

            </div>

            <div class="col-md-6 text-center text-md-right">

                <a class="text-white mr-3" href="<?= BASE_URL; ?>/index.php?url=site/politicaPrivacidade">
                    Política de Privacidade
                </a>

                <a class="text-white" href="<?= BASE_URL; ?>/index.php?url=site/termosUso">
                    Termos de Uso
                </a>

            </div>

        </div>

    </div>

</footer>

<script>

document.addEventListener('DOMContentLoaded', function(){

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

    const campoQuantidade =
        document.getElementById('simuladorMensagens');

    const campoTipo =
        document.getElementById('simuladorTipoMensagem');

    const resultado =
        document.getElementById('resultadoSimulador');

    const precosMetaBrasil = {
        marketing: 0.0625,
        utility: 0.0068,
        authentication: 0.0068
    };

    const nomesTipos = {
        marketing: 'Marketing',
        utility: 'Utilidade',
        authentication: 'Autenticação'
    };

    function formatarDolar(valor)
    {
        return valor.toLocaleString(
            'en-US',
            {
                style: 'currency',
                currency: 'USD'
            }
        );
    }

    function atualizarSimulador()
    {
        const quantidade =
            parseInt(campoQuantidade.value || 0);

        const tipo =
            campoTipo.value;

        const precoUnitario =
            precosMetaBrasil[tipo] || 0;

        const total =
            quantidade * precoUnitario;

        resultado.innerHTML =
            '<strong>Estimativa Meta:</strong> ' +
            formatarDolar(total) +
            '<br>' +
            '<small>' +
            quantidade.toLocaleString('pt-BR') +
            ' mensagens de ' +
            nomesTipos[tipo] +
            ' × ' +
            formatarDolar(precoUnitario) +
            ' por mensagem.' +
            '</small>';
    }

    campoQuantidade.addEventListener(
        'input',
        atualizarSimulador
    );

    campoTipo.addEventListener(
        'change',
        atualizarSimulador
    );

    atualizarSimulador();

});

</script>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>