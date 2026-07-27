<?php use Core\Csrf; ?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<?php $googleTagManagerSection = 'head'; require __DIR__ . '/../partials/google_tag_manager.php'; ?>
<meta charset="UTF-8">
<title>Recuperar senha - Disparador.net</title>

<meta name="robots" content="noindex, nofollow">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<link rel="stylesheet" href="<?= ASSET_URL; ?>/css/style.css?v=6">
</head>
<body class="login-custom-page">
<?php $googleTagManagerSection = 'body'; require __DIR__ . '/../partials/google_tag_manager.php'; ?>
<div class="login-custom-wrapper"><div class="login-custom-card">
    <h3 class="text-center mb-3">Recuperar senha</h3>
    <?php require __DIR__ . '/../components/flash.php'; ?>
    <p class="text-muted text-center">Vamos localizar sua conta pelo e-mail informado e, caso ela seja encontrada, enviaremos as instruções para redefinir sua senha.</p>
    <form method="POST" action="<?= rtrim(BASE_URL, '/'); ?>/index.php?url=login/enviarRecuperacao">
        <?= Csrf::input(); ?>
        <div class="form-group">
            <label>E-mail</label>
            <div class="input-group"><div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-envelope"></i></span></div>
                <input type="email" name="email" class="form-control" placeholder="seuemail@empresa.com" autocomplete="email" required>
            </div>
        </div>
        <button type="submit" class="btn btn-primary btn-block btn-login-custom"><i class="fas fa-paper-plane"></i> Enviar instruções</button>
    </form>
    <div class="text-center mt-3"><a href="<?= BASE_URL; ?>/index.php?url=login" class="text-muted">Voltar ao login</a></div>
</div></div>
</body>
</html>
