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

<section class="site-hero">

    <div class="container">

        <div class="row align-items-center">

            <div class="col-lg-6">

                <span class="badge badge-success mb-3">
                    API Oficial do WhatsApp Business
                </span>

                <h1 class="site-hero-title">
                    Envie campanhas e atenda clientes pelo <span>WhatsApp</span> em uma única plataforma.
                </h1>

                <p class="site-hero-text mt-4">
                    O Disparador ajuda sua empresa a organizar contatos, criar campanhas,
                    usar templates oficiais da Meta e centralizar conversas com seus clientes.
                </p>

                <div class="mt-4">

                    <a
                    href="<?= BASE_URL; ?>/index.php?url=site/cadastro"
                    class="btn btn-success btn-lg site-btn-main"
                    >
                        Criar conta gratuita
                    </a>

                    <a
                    href="#recursos"
                    class="btn btn-outline-secondary btn-lg ml-lg-2 mt-2 mt-lg-0"
                    >
                        Ver recursos
                    </a>

                </div>

                <p class="text-muted mt-3 mb-0">
                    Não depende de WhatsApp Web. Integração pela plataforma oficial da Meta.
                </p>

            </div>

            <div class="col-lg-6 mt-5 mt-lg-0">

                <div class="card site-card-feature">

                    <div class="card-body p-4">

                        <h5 class="font-weight-bold mb-4">
                            Com o Disparador você pode:
                        </h5>

                        <div class="site-check-item">
                            <i class="fas fa-check-circle text-success"></i>
                            Importar contatos e organizar por listas
                        </div>

                        <div class="site-check-item">
                            <i class="fas fa-check-circle text-success"></i>
                            Criar campanhas com templates aprovados
                        </div>

                        <div class="site-check-item">
                            <i class="fas fa-check-circle text-success"></i>
                            Acompanhar o envio das mensagens
                        </div>

                        <div class="site-check-item">
                            <i class="fas fa-check-circle text-success"></i>
                            Receber e responder conversas no painel
                        </div>

                        <div class="site-check-item mb-0">
                            <i class="fas fa-check-circle text-success"></i>
                            Trabalhar com múltiplos números WhatsApp
                        </div>

                    </div>

                </div>

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

                <h2 class="site-section-title">
                    Simule sua operação
                </h2>

                <p class="text-muted">
                    Em breve você poderá estimar o custo mensal considerando quantidade de mensagens,
                    plano escolhido e custos da Meta.
                </p>

            </div>

            <div class="col-md-6">

                <div class="card site-card-feature">

                    <div class="card-body p-4">

                        <label>Quantidade estimada de mensagens/mês</label>

                        <input
                        type="number"
                        id="simuladorMensagens"
                        class="form-control"
                        value="1000"
                        min="0"
                        >

                        <small class="text-muted">
                            Esta simulação é apenas uma estimativa.
                        </small>

                        <div class="alert alert-success mt-3 mb-0" id="resultadoSimulador">
                            Informe a quantidade para estimar sua operação.
                        </div>

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

    const campo = document.getElementById('simuladorMensagens');
    const resultado = document.getElementById('resultadoSimulador');

    function atualizarSimulador()
    {
        const mensagens = parseInt(campo.value || 0);

        let plano = 'Básico';

        if(mensagens > 2000){
            plano = 'Profissional';
        }

        if(mensagens > 10000){
            plano = 'Empresa';
        }

        resultado.innerHTML =
            '<strong>Plano sugerido:</strong> ' + plano +
            '<br><small>Os custos de mensagens da Meta variam conforme categoria, país e tabela vigente.</small>';
    }

    campo.addEventListener('input', atualizarSimulador);

    atualizarSimulador();

});

</script>