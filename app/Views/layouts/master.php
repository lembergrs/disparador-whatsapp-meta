<?php

use Core\Auth;

$usuario = Auth::usuario();

$url = $_GET['url'] ?? 'dashboard';

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>

<meta charset="UTF-8">

<title><?= $titulo ?? 'Sistema'; ?></title>

<meta name="viewport" content="width=device-width, initial-scale=1">

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap4.min.css">

<link rel="stylesheet" href="<?= ASSET_URL; ?>/css/style.css">

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

</head>

<body class="hold-transition sidebar-mini">

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

<li class="nav-item">

<a
href="<?= BASE_URL; ?>/index.php?url=login/sair"
class="nav-link"
>

Sair

</a>

</li>

</ul>

</nav>

<!-- Sidebar -->

<aside class="main-sidebar sidebar-dark-primary elevation-4">

<a href="<?= BASE_URL; ?>/index.php?url=dashboard" class="brand-link">

<span class="brand-text font-weight-light">
<img
    src="<?= ASSET_URL; ?>/img/logo_disparador.png"
    alt="Logo"
    onerror="this.onerror=null; this.style.display='none'; if (this.nextElementSibling) { this.nextElementSibling.style.display='flex'; }"
    width="230"
    >
</span>

</a>

<div class="sidebar">

<div class="user-panel mt-3 pb-3 mb-3 d-flex">

<div class="info">

<a href="#" class="d-block">

<?= $usuario['nome']; ?>

</a>

</div>

</div>

<nav class="mt-2">

<ul class="nav nav-pills nav-sidebar flex-column">

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
    href="<?= BASE_URL; ?>/index.php?url=financeiroAdmin"
    class="nav-link <?= str_contains($url, 'financeiroAdmin') ? 'active' : ''; ?>"
    >

        <i class="nav-icon fas fa-dollar-sign"></i>

        <p>Financeiro</p>

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
href="<?= BASE_URL; ?>/index.php?url=metaConta"
class="nav-link <?= str_contains($url, 'metaConta') ? 'active' : ''; ?>"
>

<i class="nav-icon fab fa-whatsapp"></i>

<p>Contas Meta</p>

</a>

</li>

<?php } ?>

<?php if(Auth::nivelCliente($usuario['nivel'] ?? null)){


$clienteLiberado = Auth::clienteLiberado();

$clientePodeConectarMeta = Auth::clientePodeConectarMeta();

$podeGerenciarConta = in_array($usuario['nivel'] ?? null, ['cliente', 'cliente_admin'], true);

$usuario = Auth::usuario();

?>

<?php if($podeGerenciarConta){ ?>
<li class="nav-item">

    <a
    href="<?= BASE_URL; ?>/index.php?url=financeiro"
    class="nav-link <?= str_contains($url, 'financeiro') ? 'active' : ''; ?>"
    >

        <i class="nav-icon fas fa-dollar-sign"></i>

        <p>
            Financeiro
        </p>

    </a>

</li>
<?php } ?>

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

    <a
    href="<?= $clienteLiberado
    ? BASE_URL . '/index.php?url=listaContato'
    : BASE_URL . '/index.php?url=financeiro'; ?>"
    class="nav-link <?= str_contains($url, 'listaContato') ? 'active' : ''; ?>""
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
href="<?= $clienteLiberado
? BASE_URL . '/index.php?url=conversa'
: BASE_URL . '/index.php?url=financeiro'; ?>"
class="nav-link <?= str_contains($url, 'conversa') ? 'active' : ''; ?>"
>

<i class="nav-icon fas fa-comments"></i>

<p>Conversas</p>

</a>

</li>

<?php } ?>

</ul>

</nav>

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

<?php require $viewPath; ?>

</div>

</section>

</div>

</div>

<script>

const CSRF_TOKEN = '<?= htmlspecialchars(\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>';
const BASE_URL = '<?= BASE_URL; ?>';
const META_APP_ID = '1598345545186185';
const META_CONFIGURATION_ID = '1493119295619741';

window.fbAsyncInit = function() {

    FB.init({
        appId: META_APP_ID,
        cookie: true,
        xfbml: false,
        version: 'v23.0'
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

<script src="<?= ASSET_URL; ?>/js/app.js"></script>

<script async defer crossorigin="anonymous" src="https://connect.facebook.net/pt_BR/sdk.js"></script>

</body>
</html>