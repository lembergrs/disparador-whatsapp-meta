<?php

use Core\Auth;
use Models\Conversa;

$usuario = Auth::usuario();

$url = $_GET['url'] ?? 'dashboard';

$totalConversasNaoLidas = 0;

if($usuario && (($usuario['nivel'] ?? null) === 'admin' || Auth::nivelCliente($usuario['nivel'] ?? null))){
    try{
        $totalConversasNaoLidas = (new Conversa())->totalConversasNaoLidasPorUsuario($usuario);
    }catch(\Throwable $e){
        $totalConversasNaoLidas = 0;
    }
}

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
<?php $googleTagManagerSection = 'head'; require __DIR__ . '/../partials/google_tag_manager.php'; ?>

<meta charset="UTF-8">

<title><?= $titulo ?? 'Sistema'; ?></title>

<meta name="viewport" content="width=device-width, initial-scale=1">

<meta name="robots" content="noindex, nofollow">

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap4.min.css">

<link rel="stylesheet" href="<?= ASSET_URL; ?>/css/style.css">

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>


<style>
.main-sidebar .brand-link.logo-disparador-brand {
    align-items: center;
    background: #ffffff;
    display: flex;
    justify-content: center;
    min-height: 76px;
    padding: 0.75rem 0.5rem;
    text-align: center;
}

.main-sidebar .brand-link.logo-disparador-brand .brand-text {
    align-items: center;
    display: flex;
    justify-content: center;
    width: 100%;
}

.logo-disparador-full {
    display: block;
    height: auto;
    max-height: 52px;
    max-width: 210px;
    object-fit: contain;
}

.logo-disparador-mini {
    display: none;
    flex: 0 0 auto;
    height: 38px;
    overflow: hidden;
    position: relative;
    width: 38px;
}

.logo-disparador-mini img {
    height: 38px;
    left: 0;
    max-width: none;
    position: absolute;
    top: 0;
    width: auto;
}

body.sidebar-collapse .main-sidebar .brand-link.logo-disparador-brand {
    justify-content: center;
    padding-left: 0;
    padding-right: 0;
}

body.sidebar-collapse .main-sidebar .brand-link.logo-disparador-brand .brand-text {
    display: none !important;
}

body.sidebar-collapse .main-sidebar .brand-link.logo-disparador-brand .logo-disparador-full {
    display: none !important;
}

body.sidebar-collapse .main-sidebar .brand-link.logo-disparador-brand .logo-disparador-mini {
    display: block !important;
}

.client-sidebar-help{position:absolute;left:.75rem;right:.75rem;bottom:1rem;padding:.85rem;border-radius:.5rem;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12);color:#fff;z-index:2}.client-sidebar-help p{font-size:.82rem;color:#d4d9df;margin:.35rem 0 .65rem}.client-sidebar-help .btn{color:#fff;border-color:#aeb6bf}.client-sidebar-help .btn:hover{background:#fff;color:#343a40}.client-sidebar-help-mini{display:none;position:absolute;bottom:1rem;left:0;width:100%;text-align:center;z-index:2}.client-sidebar-help-mini button{width:42px;height:42px;border-radius:.45rem;border:1px solid rgba(255,255,255,.15);background:rgba(255,255,255,.08);color:#25d366;font-size:1.35rem}.main-sidebar .sidebar{padding-bottom:150px}.sidebar-collapse .client-sidebar-help{display:none}.sidebar-collapse .client-sidebar-help-mini{display:block}.sidebar-collapse .main-sidebar .sidebar{padding-bottom:70px}@media(max-height:620px){.client-sidebar-help{display:none}.client-sidebar-help-mini{display:block}.main-sidebar .sidebar{padding-bottom:70px}}@media(max-width:767.98px){.client-sidebar-help{display:none}.client-sidebar-help-mini{display:block}}
</style>

</head>

<body class="hold-transition sidebar-mini">
<?php $googleTagManagerSection = 'body'; require __DIR__ . '/../partials/google_tag_manager.php'; ?>

<div class="wrapper">

<!-- Navbar -->

<nav class="main-header navbar navbar-expand navbar-white navbar-light">

<ul class="navbar-nav">

<li class="nav-item">

<a
class="nav-link"
data-widget="pushmenu"
href="#"
>

<i class="fas fa-bars"></i>

</a>

</li>

</ul>

<ul class="navbar-nav ml-auto">

<?php if(Auth::nivelCliente($usuario['nivel'] ?? null)){ ?>

<li class="nav-item d-none d-sm-flex align-items-center text-muted px-2">
<?= htmlspecialchars($usuario['nome'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
</li>

<li class="nav-item d-none d-sm-flex align-items-center text-muted">|</li>

<li class="nav-item dropdown">
    <a class="nav-link dropdown-toggle" href="#" id="dropdownMinhaConta" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
        Minha Conta
    </a>
    <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownMinhaConta">
        <a class="dropdown-item" href="<?= BASE_URL; ?>/index.php?url=conta">
            <i class="fas fa-user mr-2"></i> Meus Dados
        </a>
        <a class="dropdown-item" href="<?= BASE_URL; ?>/index.php?url=conta#seguranca">
            <i class="fas fa-lock mr-2"></i> Alterar Senha
        </a>
        <div class="dropdown-divider"></div>
        <a class="dropdown-item" href="<?= BASE_URL; ?>/index.php?url=financeiro">
            <i class="fas fa-dollar-sign mr-2"></i> Financeiro
        </a>
        <div class="dropdown-divider"></div>
        <form method="post" action="<?= BASE_URL; ?>/index.php?url=login/sair" class="m-0">
            <button type="submit" class="dropdown-item">
                <i class="fas fa-sign-out-alt mr-2"></i> Sair
            </button>
        </form>
    </div>
</li>

<?php }else{ ?>

<li class="nav-item">

<form method="post" action="<?= BASE_URL; ?>/index.php?url=login/sair" class="m-0">
<button type="submit" class="nav-link btn btn-link p-0">

Sair

</button>
</form>

</li>

<?php } ?>

</ul>

</nav>

<!-- Sidebar -->

<aside class="main-sidebar sidebar-dark-primary elevation-4">

<a href="<?= BASE_URL; ?>/index.php?url=dashboard" class="brand-link logo-disparador-brand">

<span class="brand-text font-weight-light">
<img
    src="<?= ASSET_URL; ?>/img/logo-disparador.png"
    alt="Disparador"
    class="logo-disparador-full"
    >
</span>
<span class="logo-disparador-mini" aria-hidden="true">
    <img
        src="<?= ASSET_URL; ?>/img/logo-disparador.png"
        alt=""
        >
</span>

</a>

<div class="sidebar">

<nav class="mt-2">

<ul class="nav nav-pills nav-sidebar flex-column"
    data-widget="treeview"
    role="menu"
    data-accordion="false">

<li class="nav-item">

<a
href="<?= BASE_URL; ?>/index.php?url=dashboard"
class="nav-link <?= ($url == 'dashboard') ? 'active' : ''; ?>"
>

<i class="nav-icon fas fa-home"></i>

<p>Dashboard</p>

</a>

</li>

<?php if($usuario['nivel'] == 'admin'){ ?>

<li class="nav-item has-treeview <?= str_contains($url, 'artigoAdmin') ? 'menu-open' : ''; ?>">
    <a href="#" class="nav-link <?= str_contains($url, 'artigoAdmin') ? 'active' : ''; ?>">
        <i class="nav-icon fas fa-pen-nib"></i><p>Conteúdo<i class="right fas fa-angle-left"></i></p>
    </a>
    <ul class="nav nav-treeview">
        <li class="nav-item"><a href="<?= BASE_URL; ?>/index.php?url=artigoAdmin" class="nav-link <?= str_contains($url, 'artigoAdmin') ? 'active' : ''; ?>"><i class="far fa-circle nav-icon"></i><p>Artigos</p></a></li>
    </ul>
</li>

<li class="nav-item">

<a
href="<?= BASE_URL; ?>/index.php?url=cliente"
class="nav-link <?= str_contains($url, 'cliente') ? 'active' : ''; ?>"
>

<i class="nav-icon fas fa-users"></i>

<p>Clientes</p>

</a>

</li>

<li class="nav-item">
<a
href="<?= BASE_URL; ?>/index.php?url=onboardingSuporteAdmin"
class="nav-link <?= str_contains($url, 'onboardingSuporteAdmin') ? 'active' : ''; ?>"
>
<i class="nav-icon fas fa-headset"></i>
<p>Suporte onboarding</p>
</a>
</li>

<li class="nav-item">

    <a
    href="<?= BASE_URL; ?>/index.php?url=financeiroAdmin"
    class="nav-link <?= str_contains($url, 'financeiroAdmin') ? 'active' : ''; ?>"
    >

        <i class="nav-icon fas fa-dollar-sign"></i>

        <p>Financeiro</p>

    </a>

</li>

<li class="nav-item">
    <a href="<?= BASE_URL; ?>/index.php?url=indicacaoAdmin" class="nav-link <?= str_contains($url, 'indicacaoAdmin') ? 'active' : ''; ?>">
        <i class="nav-icon fas fa-share-alt"></i><p>Programa de Indicação</p>
    </a>
</li>

<li class="nav-item">
    <a href="<?= BASE_URL; ?>/index.php?url=depoimentoAdmin" class="nav-link <?= str_contains($url, 'depoimentoAdmin') ? 'active' : ''; ?>">
        <i class="nav-icon fas fa-quote-left"></i><p>Depoimentos</p>
    </a>
</li>

<li class="nav-item">

    <a
    href="<?= BASE_URL; ?>/index.php?url=nfse"
    class="nav-link <?= str_contains($url, 'nfse') ? 'active' : ''; ?>"
    >

        <i class="nav-icon fas fa-file-invoice"></i>

        <p>NFS-e</p>

    </a>

</li>

<li class="nav-item">

    <a
    href="<?= BASE_URL; ?>/index.php?url=assinatura"
    class="nav-link <?= str_contains($url, 'assinatura') ? 'active' : ''; ?>"
    >

        <i class="nav-icon fas fa-file-invoice-dollar"></i>

        <p>Assinaturas</p>

    </a>

</li>

<li class="nav-item">

<a
href="<?= BASE_URL; ?>/index.php?url=notificacao"
class="nav-link <?= str_contains($url, 'notificacao') ? 'active' : ''; ?>"
>

<i class="nav-icon fas fa-bell"></i>

<p>Notificações</p>

</a>

</li>

<li class="nav-item">
<a href="<?= BASE_URL; ?>/index.php?url=metaPricingReport" class="nav-link <?= str_contains($url, 'metaPricingReport') ? 'active' : ''; ?>">
<i class="nav-icon fas fa-tags"></i><p>Pricing Meta</p>
</a>
</li>

<li class="nav-item">

<a
href="<?= BASE_URL; ?>/index.php?url=metaConta"
class="nav-link <?= str_contains($url, 'metaConta') ? 'active' : ''; ?>"
>

<i class="nav-icon fab fa-whatsapp"></i>

<p>Contas Meta</p>

</a>

</li>

<?php if(Auth::podeGerenciarPropriaConfiguracaoMeta($usuario)){ ?>
<li class="nav-item">
<a href="<?= BASE_URL; ?>/index.php?url=configuracao/meta" class="nav-link <?= str_contains($url, 'configuracao/meta') ? 'active' : ''; ?>">
<i class="nav-icon fab fa-whatsapp"></i>
<p>Números WhatsApp</p>
</a>
</li>
<?php } ?>

<li class="nav-item">

<a
href="<?= BASE_URL; ?>/index.php?url=template"
class="nav-link <?= str_contains($url, 'template') ? 'active' : ''; ?>"
>

<i class="nav-icon fas fa-file-alt"></i>

<p>Templates Meta</p>

</a>

</li>

<li class="nav-item">
<a
href="<?= BASE_URL; ?>/index.php?url=disparo"
class="nav-link <?= str_contains($url, 'disparo') ? 'active' : ''; ?>"
>
<i class="nav-icon fas fa-paper-plane"></i>
<p>Disparos</p>
</a>
</li>

<li class="nav-item">

<a
href="<?= BASE_URL; ?>/index.php?url=conversa"
class="nav-link <?= str_contains($url, 'conversa') ? 'active' : ''; ?>"
>

<i class="nav-icon fas fa-comments"></i>

<p>
    Conversas
    <?php if($totalConversasNaoLidas > 0){ ?>
        <span class="badge badge-danger right">
            <?= $totalConversasNaoLidas > 99 ? '99+' : (int) $totalConversasNaoLidas; ?>
        </span>
    <?php } ?>
</p>

</a>

</li>

<?php } ?>

<?php if(Auth::nivelCliente($usuario['nivel'] ?? null)){


// O Dashboard já precisou avaliar o acesso para escolher a próxima ação.
// Preserva inclusive false; outras telas continuam usando o Auth normalmente.
$clienteLiberado = isset($acessoOperacionalDashboard)
    ? $acessoOperacionalDashboard
    : Auth::clienteLiberado();

$clientePodeConectarMeta = Auth::clientePodeConectarMeta();

$podeGerenciarConta = in_array($usuario['nivel'] ?? null, ['cliente', 'cliente_admin'], true);

$usuario = Auth::usuario();

?>

<?php if($podeGerenciarConta){ ?>
<li class="nav-item">

    <a
    href="<?= ($clienteLiberado || $clientePodeConectarMeta)
    ? BASE_URL . '/index.php?url=configuracao/meta'
    : BASE_URL . '/index.php?url=financeiro'; ?>"
    class="nav-link <?= str_contains($url, 'configuracao') ? 'active' : ''; ?>"
    >

        <i class="nav-icon fab fa-whatsapp"></i>

        <p>
            Números WhatsApp
        </p>

    </a>

</li>
<?php } ?>

<?php if($podeGerenciarConta){ ?>
<li class="nav-item">
    <a href="<?= BASE_URL; ?>/index.php?url=usuario" class="nav-link <?= str_contains($url, 'usuario') ? 'active' : ''; ?>">
        <i class="nav-icon fas fa-users"></i>
        <p>Usuários</p>
    </a>
</li>
<?php } ?>

<li class="nav-item">
    <a href="<?= BASE_URL; ?>/index.php?url=depoimento" class="nav-link <?= $url === 'depoimento' ? 'active' : ''; ?>">
        <i class="nav-icon fas fa-comment-dots"></i><p>Meu depoimento</p>
    </a>
</li>

<li class="nav-item">

    <a
    href="<?= $clienteLiberado
    ? BASE_URL . '/index.php?url=listaContato'
    : BASE_URL . '/index.php?url=financeiro'; ?>"
    class="nav-link <?= (str_contains($url, 'listaContato') || str_contains($url, 'importacao')) ? 'active' : ''; ?>"
    >

        <i class="nav-icon fas fa-list"></i>

        <p>
            Listas de Contatos
        </p>

    </a>

</li>

<!-- li class="nav-item">

<a
href="<?= BASE_URL; ?>/index.php?url=importacao"
class="nav-link <?= str_contains($url, 'importacao') ? 'active' : ''; ?>"
>

<i class="nav-icon fas fa-file-excel"></i>

<p>Importar Contatos</p>

</a>

</li -->

<li class="nav-item">

<a
href="<?= $clienteLiberado
? BASE_URL . '/index.php?url=template'
: BASE_URL . '/index.php?url=financeiro'; ?>"
class="nav-link <?= str_contains($url, 'template') ? 'active' : ''; ?>"
>

<i class="nav-icon fas fa-file-alt"></i>

<p>Templates</p>

</a>

</li>

<li class="nav-item">

<a
href="<?= $clienteLiberado
? BASE_URL . '/index.php?url=disparo'
: BASE_URL . '/index.php?url=financeiro'; ?>"
class="nav-link <?= str_contains($url, 'disparo') ? 'active' : ''; ?>"
>

<i class="nav-icon fas fa-paper-plane"></i>

<p>Disparos</p>

</a>

</li>

<li class="nav-item">

<a
href="<?= $clienteLiberado
? BASE_URL . '/index.php?url=campanha'
: BASE_URL . '/index.php?url=financeiro'; ?>"
class="nav-link <?= str_contains($url, 'campanha') ? 'active' : ''; ?>"
>

<i class="nav-icon fas fa-bullhorn"></i>

<p>Campanhas</p>

</a>

</li>

<li class="nav-item">

<a
href="<?= BASE_URL; ?>/index.php?url=indicacao"
class="nav-link <?= str_contains($url, 'indicacao') ? 'active' : ''; ?>"
>

<i class="nav-icon fas fa-share-alt"></i>

<p>Indique e Ganhe</p>

</a>

</li>

<li class="nav-item">

<a
href="<?= $clienteLiberado
? BASE_URL . '/index.php?url=conversa'
: BASE_URL . '/index.php?url=financeiro'; ?>"
class="nav-link <?= str_contains($url, 'conversa') ? 'active' : ''; ?>"
>

<i class="nav-icon fas fa-comments"></i>

<p>
Conversas
<?php if($totalConversasNaoLidas > 0){ ?>
    <span class="badge badge-danger right">
        <?= $totalConversasNaoLidas > 99 ? '99+' : (int) $totalConversasNaoLidas; ?>
    </span>
<?php } ?>
</p>

</a>

</li>

<?php } ?>

</ul>

</nav>

<?php if(Auth::nivelCliente($usuario['nivel'] ?? null)){ ?>
<div class="client-sidebar-help" aria-label="Ajuda">
    <strong><i class="fab fa-whatsapp text-success mr-1"></i> Precisa de ajuda?</strong>
    <p>Nossa equipe está pronta para te atender pelo WhatsApp.</p>
    <button type="button" class="btn btn-outline-light btn-sm btn-block" data-toggle="modal" data-target="#modalAjudaGlobal"><i class="fab fa-whatsapp mr-1"></i> Solicitar ajuda</button>
</div>
<div class="client-sidebar-help-mini">
    <button type="button" title="Solicitar ajuda pelo WhatsApp" aria-label="Solicitar ajuda pelo WhatsApp" data-toggle="modal" data-target="#modalAjudaGlobal"><i class="fab fa-whatsapp"></i></button>
</div>
<?php } ?>

</div>

</aside>

<!-- Conteúdo -->

<div class="content-wrapper">

<section class="content-header">

<div class="container-fluid">

<h1><?= $titulo ?? ''; ?></h1>

</div>

</section>

<section class="content">

<div class="container-fluid">

<?php require __DIR__ . '/../components/flash.php'; ?>

<?php require __DIR__ . '/../components/support_mode_banner.php'; ?>

<?php require $viewPath; ?>

</div>

</section>

</div>

<?php if(Auth::nivelCliente($usuario['nivel'] ?? null)){ ?>
<div class="modal fade" id="modalAjudaGlobal" tabindex="-1" role="dialog" aria-labelledby="tituloModalAjudaGlobal" aria-hidden="true"><div class="modal-dialog" role="document"><form method="post" action="<?= BASE_URL; ?>/index.php?url=onboardingSuporte/solicitar" class="modal-content"><?= \Core\Csrf::input(); ?><input type="hidden" name="conta_id" value=""><input type="hidden" name="etapa" value="ajuda_geral"><div class="modal-header"><h4 class="modal-title h5" id="tituloModalAjudaGlobal">Solicitar ajuda</h4><button type="button" class="close" data-dismiss="modal" aria-label="Fechar"><span aria-hidden="true">&times;</span></button></div><div class="modal-body"><p class="small text-muted">Nossa equipe acompanhará a solicitação e entrará em contato com você pelo WhatsApp.</p><div class="form-group"><label for="ajudaGlobalAssunto">Como podemos ajudar?</label><select class="form-control" id="ajudaGlobalAssunto" name="assunto" required><option value="">Selecione</option><option value="duvida_configuracao">Dúvida sobre configuração</option><option value="mensagem_erro">Mensagem de erro</option><option value="orientacao">Preciso de orientação</option><option value="outro">Outro</option></select></div><div class="form-group"><label for="ajudaGlobalDescricao">Conte um pouco mais <span class="text-muted">(opcional)</span></label><textarea class="form-control" id="ajudaGlobalDescricao" name="descricao" rows="3" maxlength="1000"></textarea><small class="form-text text-muted">Não envie senhas, códigos de verificação ou tokens de acesso.</small></div><div class="form-group"><label for="ajudaGlobalPeriodo">Melhor período para contato</label><select class="form-control" id="ajudaGlobalPeriodo" name="periodo" required><option value="manha">Manhã</option><option value="tarde">Tarde</option><option value="noite">Noite</option><option value="qualquer" selected>Qualquer horário</option></select></div><div class="form-group mb-0"><label for="ajudaGlobalHorario">Detalhe de horário <span class="text-muted">(opcional)</span></label><input class="form-control" id="ajudaGlobalHorario" name="horario" maxlength="120" placeholder="Ex.: dias úteis, entre 14h e 17h"></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-success">Enviar solicitação</button></div></form></div></div>
<?php } ?>

</div>

<script>

const CSRF_TOKEN = '<?= htmlspecialchars(\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>';
const BASE_URL = '<?= BASE_URL; ?>';
window.META_APP_ID = '<?= htmlspecialchars(META_APP_ID, ENT_QUOTES, 'UTF-8'); ?>';
window.META_CONFIGURATION_ID = '<?= htmlspecialchars(META_CONFIGURATION_ID, ENT_QUOTES, 'UTF-8'); ?>';
window.META_EMBEDDED_SIGNUP_REDIRECT_URI = '<?= htmlspecialchars(META_EMBEDDED_SIGNUP_REDIRECT_URI, ENT_QUOTES, 'UTF-8'); ?>';
window.META_GRAPH_VERSION = '<?= htmlspecialchars(META_GRAPH_VERSION, ENT_QUOTES, 'UTF-8'); ?>';

window.fbAsyncInit = function() {

    FB.init({
        appId: window.META_APP_ID,
        cookie: true,
        xfbml: false,
        version: window.META_GRAPH_VERSION
    });

};

document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('form[method="post"], form[method="POST"]').forEach(function(form){
        if(!form.querySelector('input[name="csrf_token"]')){
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'csrf_token';
            input.value = CSRF_TOKEN;
            form.appendChild(input);
        }
    });

    document.querySelectorAll('[data-post-url]').forEach(function(link){
        link.addEventListener('click', function(e){
            e.preventDefault();

            if(link.dataset.confirm && !confirm(link.dataset.confirm)){
                return;
            }

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = link.dataset.postUrl;

            const csrf = document.createElement('input');
            csrf.type = 'hidden';
            csrf.name = 'csrf_token';
            csrf.value = CSRF_TOKEN;
            form.appendChild(csrf);

            Object.keys(link.dataset).forEach(function(key){
                if(key.indexOf('field') === 0){
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = key.substring(5).replace(/^./, function(c){ return c.toLowerCase(); });
                    input.value = link.dataset[key];
                    form.appendChild(input);
                }
            });

            document.body.appendChild(form);
            form.submit();
        });
    });
});

</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>

<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap4.min.js"></script>

<script src="<?= ASSET_URL; ?>/js/password-strength.js"></script>

<script src="<?= ASSET_URL; ?>/js/app.js"></script>

<script async defer crossorigin="anonymous" src="https://connect.facebook.net/pt_BR/sdk.js"></script>

</body>
</html>
