<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm fixed-top">

    <div class="container">

        <a class="navbar-brand font-weight-bold text-success" href="#">
            <i class="fab fa-whatsapp"></i>
            Disparador
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

            <ul class="navbar-nav ml-auto">

                <li class="nav-item">
                    <a class="nav-link" href="#recursos">Recursos</a>
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
                    class="btn btn-success ml-lg-2"
                    href="<?= BASE_URL; ?>/index.php?url=site/cadastro"
                    >
                        Criar Conta
                    </a>
                </li>

            </ul>

        </div>

    </div>

</nav>

<section class="py-5" style="margin-top:70px; background:#f4fbf7;">

    <div class="container py-5">

        <div class="row align-items-center">

            <div class="col-md-6">

                <span class="badge badge-success mb-3">
                    Plataforma WhatsApp Business
                </span>

                <h1 class="display-4 font-weight-bold">
                    Campanhas e atendimento pelo WhatsApp em um só lugar.
                </h1>

                <p class="lead text-muted mt-3">
                    Envie campanhas com templates oficiais da Meta,
                    organize contatos em listas e centralize suas conversas
                    em uma plataforma simples e profissional.
                </p>

                <div class="mt-4">

                    <a
                    href="<?= BASE_URL; ?>/index.php?url=site/cadastro"
                    class="btn btn-success btn-lg"
                    >
                        Começar Agora
                    </a>

                    <a
                    href="#recursos"
                    class="btn btn-outline-secondary btn-lg ml-2"
                    >
                        Ver Recursos
                    </a>

                </div>

            </div>

            <div class="col-md-6 mt-5 mt-md-0">

                <div class="card shadow border-0">

                    <div class="card-body">

                        <h5 class="font-weight-bold mb-3">
                            O que você poderá fazer:
                        </h5>

                        <p>
                            <i class="fas fa-check text-success"></i>
                            Importar contatos por lista
                        </p>

                        <p>
                            <i class="fas fa-check text-success"></i>
                            Enviar campanhas agendadas
                        </p>

                        <p>
                            <i class="fas fa-check text-success"></i>
                            Atender clientes na Central de Conversas
                        </p>

                        <p>
                            <i class="fas fa-check text-success"></i>
                            Usar vários números WhatsApp no mesmo cliente
                        </p>

                        <p class="mb-0">
                            <i class="fas fa-check text-success"></i>
                            Acompanhar resultados pelo Dashboard
                        </p>

                    </div>

                </div>

            </div>

        </div>

    </div>

</section>

<section id="recursos" class="py-5">

    <div class="container">

        <div class="text-center mb-5">

            <h2 class="font-weight-bold">
                Recursos principais
            </h2>

            <p class="text-muted">
                Tudo que sua empresa precisa para organizar campanhas e atendimento.
            </p>

        </div>

        <div class="row">

            <?php
            $recursos = [
                ['icon' => 'fas fa-bullhorn', 'titulo' => 'Campanhas WhatsApp', 'texto' => 'Crie campanhas para listas de contatos e agende o envio.'],
                ['icon' => 'fas fa-file-alt', 'titulo' => 'Templates Meta', 'texto' => 'Use templates oficiais aprovados pela Meta.'],
                ['icon' => 'fas fa-list', 'titulo' => 'Listas Segmentadas', 'texto' => 'Organize contatos por listas e campanhas específicas.'],
                ['icon' => 'fas fa-comments', 'titulo' => 'Central de Conversas', 'texto' => 'Atenda mensagens recebidas em uma interface simples.'],
                ['icon' => 'fas fa-tags', 'titulo' => 'Etiquetas', 'texto' => 'Classifique conversas por prioridade, status ou assunto.'],
                ['icon' => 'fab fa-whatsapp', 'titulo' => 'Múltiplos Números', 'texto' => 'Permita mais de um número WhatsApp por cliente.'],
            ];
            ?>

            <?php foreach($recursos as $recurso){ ?>

                <div class="col-md-4 mb-4">

                    <div class="card h-100 border-0 shadow-sm">

                        <div class="card-body">

                            <div class="text-success mb-3">
                                <i class="<?= $recurso['icon']; ?> fa-2x"></i>
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

<section class="py-5 bg-light">

    <div class="container">

        <div class="text-center mb-5">

            <h2 class="font-weight-bold">
                Como funciona
            </h2>

        </div>

        <div class="row text-center">

            <div class="col-md-3 mb-4">
                <h3 class="text-success">1</h3>
                <h5>Crie sua conta</h5>
                <p class="text-muted">Cadastre sua empresa na plataforma.</p>
            </div>

            <div class="col-md-3 mb-4">
                <h3 class="text-success">2</h3>
                <h5>Conecte o WhatsApp</h5>
                <p class="text-muted">Vincule seu número WhatsApp Business.</p>
            </div>

            <div class="col-md-3 mb-4">
                <h3 class="text-success">3</h3>
                <h5>Importe contatos</h5>
                <p class="text-muted">Organize seus contatos em listas.</p>
            </div>

            <div class="col-md-3 mb-4">
                <h3 class="text-success">4</h3>
                <h5>Envie e atenda</h5>
                <p class="text-muted">Dispare campanhas e responda clientes.</p>
            </div>

        </div>

    </div>

</section>

<section id="planos" class="py-5">

    <div class="container">

        <div class="text-center mb-5">

            <h2 class="font-weight-bold">
                Planos
            </h2>

            <p class="text-muted">
                Escolha o plano ideal para sua operação.
            </p>

        </div>

        <div class="row">

            <?php
            $planos = [
                ['nome' => 'Básico', 'numero' => '1 número WhatsApp', 'atendentes' => '1 atendente', 'classe' => 'secondary'],
                ['nome' => 'Profissional', 'numero' => '2 números WhatsApp', 'atendentes' => '3 atendentes', 'classe' => 'success'],
                ['nome' => 'Empresa', 'numero' => '5 números WhatsApp', 'atendentes' => '10 atendentes', 'classe' => 'primary'],
            ];
            ?>

            <?php foreach($planos as $plano){ ?>

                <div class="col-md-4 mb-4">

                    <div class="card h-100 shadow-sm">

                        <div class="card-header text-center bg-<?= $plano['classe']; ?> text-white">

                            <h4 class="mb-0">
                                <?= $plano['nome']; ?>
                            </h4>

                        </div>

                        <div class="card-body text-center">

                            <p>
                                <i class="fab fa-whatsapp text-success"></i>
                                <?= $plano['numero']; ?>
                            </p>

                            <p>
                                <i class="fas fa-user text-info"></i>
                                <?= $plano['atendentes']; ?>
                            </p>

                            <p>
                                Campanhas, listas, templates e central de conversas.
                            </p>

                            <a
                            href="<?= BASE_URL; ?>/index.php?url=site/cadastro"
                            class="btn btn-outline-success btn-block"
                            >
                                Escolher plano
                            </a>

                        </div>

                    </div>

                </div>

            <?php } ?>

        </div>

    </div>

</section>

<section class="py-5 bg-light">

    <div class="container">

        <div class="text-center mb-5">

            <h2 class="font-weight-bold">
                Depoimentos
            </h2>

        </div>

        <div class="row">

            <div class="col-md-6 mb-4">

                <div class="card border-0 shadow-sm h-100">

                    <div class="card-body">

                        <p>
                            “Conseguimos organizar as campanhas e acompanhar melhor
                            as conversas com nossos clientes.”
                        </p>

                        <strong>
                            Cliente Disparador
                        </strong>

                    </div>

                </div>

            </div>

            <div class="col-md-6 mb-4">

                <div class="card border-0 shadow-sm h-100">

                    <div class="card-body">

                        <p>
                            “A centralização das listas e mensagens facilitou muito
                            a rotina da equipe.”
                        </p>

                        <strong>
                            Empresa Parceira
                        </strong>

                    </div>

                </div>

            </div>

        </div>

    </div>

</section>

<section id="faq" class="py-5">

    <div class="container">

        <div class="text-center mb-5">

            <h2 class="font-weight-bold">
                Perguntas frequentes
            </h2>

        </div>

        <div class="row justify-content-center">

            <div class="col-md-8">

                <div class="mb-4">
                    <h5>Posso usar meu número atual?</h5>
                    <p class="text-muted">Sim, desde que ele esteja apto para uso com a API oficial do WhatsApp Business.</p>
                </div>

                <div class="mb-4">
                    <h5>Preciso deixar um celular ligado?</h5>
                    <p class="text-muted">Não. A plataforma utiliza a API oficial da Meta, sem depender de WhatsApp Web.</p>
                </div>

                <div class="mb-4">
                    <h5>Posso ter mais de um número?</h5>
                    <p class="text-muted">Sim. Os planos poderão permitir diferentes quantidades de números por cliente.</p>
                </div>

                <div class="mb-4">
                    <h5>O envio é oficial?</h5>
                    <p class="text-muted">Sim. O sistema foi pensado para operar com a WhatsApp Cloud API da Meta.</p>
                </div>

            </div>

        </div>

    </div>

</section>

<section class="py-5 bg-success text-white text-center">

    <div class="container">

        <h2 class="font-weight-bold">
            Pronto para organizar seu WhatsApp?
        </h2>

        <p class="lead">
            Comece a estruturar suas campanhas e conversas em uma única plataforma.
        </p>

        <a
        href="<?= BASE_URL; ?>/index.php?url=site/cadastro"
        class="btn btn-light btn-lg"
        >
            Criar Conta
        </a>

    </div>

</section>

<footer class="py-4 bg-dark text-white text-center">

    <div class="container">

        <p class="mb-0">
            © <?= date('Y'); ?> Disparador. Todos os direitos reservados.
        </p>

    </div>

</footer>