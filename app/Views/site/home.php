<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">

    <title>Disparador | WhatsApp Business API</title>

    <meta name="viewport" content="width=device-width, initial-scale=1">

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
</head>

<body>
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm fixed-top site-navbar">

    <div class="container">

        <a class="navbar-brand d-flex align-items-center" href="<?= BASE_URL; ?>/index.php?url=site">
            <img
            src="<?= ASSET_URL; ?>/img/logo-disparador.png"
            alt="Disparador"
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
                    <a class="nav-link" href="#como-funciona">Como funciona</a>
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
                        Começar agora
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
                    Transforme seu WhatsApp em uma plataforma de <span>campanhas e atendimento</span>.
                </h1>

                <p class="site-hero-text mt-4">
                    Com o Disparador, sua empresa cria templates oficiais, organiza listas,
                    envia campanhas e centraliza conversas em uma única ferramenta.
                </p>

                <div class="mt-4">

                    <a
                    href="<?= BASE_URL; ?>/index.php?url=site/cadastro"
                    class="btn btn-success btn-lg site-btn-main"
                    >
                        Começar agora
                    </a>

                    <a
                    href="#como-funciona"
                    class="btn btn-outline-secondary btn-lg site-btn-outline ml-lg-2 mt-2 mt-lg-0"
                    >
                        Como funciona
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
                        Multiempresa
                    </span>

                </div>

            </div>

            <div class="col-lg-6 mt-5 mt-lg-0">

                <div class="site-dashboard-mockup">

                    <div class="site-mockup-top">
                        Painel Disparador
                    </div>

                    <div class="site-mockup-body">

                        <div class="mb-3">

                            <small class="text-muted">
                                Status da plataforma
                            </small>

                            <h5 class="font-weight-bold mb-0">
                                Operação em produção
                            </h5>

                        </div>

                        <div class="row">

                            <div class="col-md-6">

                                <div class="site-mini-card">
                                    <small class="text-muted">API Meta</small>
                                    <h4 class="mb-0 text-success">
                                        Operacional
                                    </h4>
                                </div>

                            </div>

                            <div class="col-md-6">

                                <div class="site-mini-card">
                                    <small class="text-muted">Webhooks</small>
                                    <h4 class="mb-0 text-success">
                                        Ativos
                                    </h4>
                                </div>

                            </div>

                        </div>

                        <div class="site-mini-card">

                            <strong>
                                Últimos 30 dias
                            </strong>

                            <div class="mt-3">

                                <p class="mb-2">
                                    <i class="fas fa-check-circle text-success"></i>
                                    Plataforma implantada em ambiente de produção
                                </p>

                                <p class="mb-2">
                                    <i class="fas fa-check-circle text-success"></i>
                                    Integração com API Oficial da Meta configurada
                                </p>

                                <p class="mb-2">
                                    <i class="fas fa-check-circle text-success"></i>
                                    Templates oficiais criados e sincronizados
                                </p>

                                <p class="mb-0">
                                    <i class="fas fa-check-circle text-success"></i>
                                    Central de conversas e campanhas disponíveis
                                </p>

                            </div>

                        </div>

                        <div class="site-mini-card mb-0">

                            <div class="d-flex align-items-center">

                                <i class="fab fa-whatsapp fa-2x text-success mr-3"></i>

                                <div>
                                    <strong>Disparador WhatsApp Business</strong><br>
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

                O Disparador utiliza a API Oficial do WhatsApp Business Platform
                para envio de mensagens, gerenciamento de templates e integração
                com números comerciais.

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
                                    API oficial para campanhas,
                                    atendimento e automações.
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
                                    Infraestrutura utilizada por
                                    milhões de empresas no mundo.
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
                                    Templates Homologados
                                </div>

                            </div>

                            <div class="col-md-3 col-6 mb-3">

                                <i class="fas fa-check-circle text-success"></i>

                                <div class="small mt-2">
                                    Cloud API
                                </div>

                            </div>

                            <div class="col-md-3 col-6 mb-3">

                                <i class="fas fa-check-circle text-success"></i>

                                <div class="small mt-2">
                                    Ambiente Seguro
                                </div>

                            </div>

                        </div>

                    </div>

                </div>

                <p class="text-center text-muted small mt-3 mb-0">

                    Meta, WhatsApp e seus respectivos logotipos são marcas de seus proprietários.
                    O Disparador utiliza a infraestrutura oficial do WhatsApp Business Platform.

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
                    'texto' => 'Envie campanhas para listas de contatos usando templates oficiais da Meta.'
                ],
                [
                    'icon' => 'fas fa-file-alt',
                    'titulo' => 'Templates oficiais',
                    'texto' => 'Crie, sincronize e utilize templates aprovados para iniciar conversas.'
                ],
                [
                    'icon' => 'fas fa-list',
                    'titulo' => 'Listas de contatos',
                    'texto' => 'Importe contatos e organize sua base por campanhas, públicos ou segmentos.'
                ],
                [
                    'icon' => 'fas fa-comments',
                    'titulo' => 'Central de conversas',
                    'texto' => 'Receba mensagens dos clientes e acompanhe tudo em uma tela estilo WhatsApp Web.'
                ],
                [
                    'icon' => 'fas fa-tags',
                    'titulo' => 'Etiquetas e filtros',
                    'texto' => 'Classifique conversas por status, assunto, prioridade ou etapa do atendimento.'
                ],
                [
                    'icon' => 'fab fa-whatsapp',
                    'titulo' => 'Múltiplos números',
                    'texto' => 'Prepare sua operação para conectar mais de um número WhatsApp por cliente.'
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
                Um fluxo simples para começar a usar o WhatsApp de forma profissional.
            </p>

        </div>

        <div class="row text-center">

            <div class="col-md-3 mb-4">
                <div class="site-step">1</div>
                <h5 class="font-weight-bold">Crie sua conta</h5>
                <p class="text-muted">Cadastre sua empresa e aguarde a aprovação.</p>
            </div>

            <div class="col-md-3 mb-4">
                <div class="site-step">2</div>
                <h5 class="font-weight-bold">Conecte o WhatsApp</h5>
                <p class="text-muted">Vincule seu número à plataforma oficial da Meta.</p>
            </div>

            <div class="col-md-3 mb-4">
                <div class="site-step">3</div>
                <h5 class="font-weight-bold">Importe contatos</h5>
                <p class="text-muted">Crie listas e organize sua base de clientes.</p>
            </div>

            <div class="col-md-3 mb-4">
                <div class="site-step">4</div>
                <h5 class="font-weight-bold">Envie e atenda</h5>
                <p class="text-muted">Dispare campanhas e acompanhe as conversas.</p>
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

        <div class="row">

            <?php
            $planos = [
                [
                    'nome' => 'Básico',
                    'descricao' => 'Para começar com um número WhatsApp.',
                    'numeros' => '1 número WhatsApp',
                    'usuarios' => '1 usuário',
                    'classe' => 'secondary'
                ],
                [
                    'nome' => 'Profissional',
                    'descricao' => 'Para empresas com operação ativa de campanhas.',
                    'numeros' => '2 números WhatsApp',
                    'usuarios' => '3 usuários',
                    'classe' => 'success'
                ],
                [
                    'nome' => 'Empresa',
                    'descricao' => 'Para operações maiores e mais atendentes.',
                    'numeros' => '5 números WhatsApp',
                    'usuarios' => '10 usuários',
                    'classe' => 'primary'
                ],
            ];
            ?>

            <?php foreach($planos as $plano){ ?>

                <div class="col-md-4 mb-4">

                    <div class="card h-100 site-card-feature">

                        <div class="card-body p-4 text-center">

                            <span class="badge badge-<?= $plano['classe']; ?> mb-3">
                                <?= $plano['nome']; ?>
                            </span>

                            <h4 class="font-weight-bold">
                                <?= $plano['nome']; ?>
                            </h4>

                            <p class="text-muted">
                                <?= $plano['descricao']; ?>
                            </p>

                            <hr>

                            <p>
                                <i class="fab fa-whatsapp text-success"></i>
                                <?= $plano['numeros']; ?>
                            </p>

                            <p>
                                <i class="fas fa-user text-info"></i>
                                <?= $plano['usuarios']; ?>
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
                    Estime o custo das mensagens da Meta
                </h2>

                <p class="text-muted">
                    Informe o tipo de mensagem e a quantidade estimada para ter uma ideia
                    do custo aproximado cobrado pela Meta para números do Brasil.
                </p>

                <p class="text-muted small">
                    A mensalidade da plataforma Disparador não está incluída nesta simulação.
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
                            Valores estimados com base em tabela pública da Meta para o Brasil.
                            A cobrança real pode variar conforme categoria, país do destinatário,
                            moeda, regras vigentes e mensagens efetivamente entregues.
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

                <div class="site-faq-item">
                    <h5>O Disparador usa WhatsApp Web?</h5>
                    <p class="text-muted">
                        Não. A plataforma foi desenvolvida para operar com a API oficial do WhatsApp Business da Meta.
                    </p>
                </div>

                <div class="site-faq-item">
                    <h5>Preciso deixar celular ligado?</h5>
                    <p class="text-muted">
                        Não. A operação acontece pela estrutura oficial da Meta, sem depender de sessão de navegador.
                    </p>
                </div>

                <div class="site-faq-item">
                    <h5>Posso importar contatos?</h5>
                    <p class="text-muted">
                        Sim. Você pode organizar seus contatos em listas para facilitar campanhas e segmentações.
                    </p>
                </div>

                <div class="site-faq-item">
                    <h5>Posso ter mais de um número?</h5>
                    <p class="text-muted">
                        Sim. A plataforma está preparada para múltiplos números por cliente, conforme o plano contratado.
                    </p>
                </div>

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
            Cadastre sua empresa e comece a organizar campanhas, contatos e conversas.
        </p>

        <a
        href="<?= BASE_URL; ?>/index.php?url=site/cadastro"
        class="btn btn-light btn-lg"
        >
            Criar conta
        </a>

    </div>

</section>

<footer class="py-4 bg-dark text-white">

    <div class="container">

        <div class="row align-items-center">

            <div class="col-md-6 text-center text-md-left mb-2 mb-md-0">

                © <?= date('Y'); ?> Disparador RL2 Net. Todos os direitos reservados.

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