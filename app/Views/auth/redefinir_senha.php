<?php use Core\Csrf; $tokenSeguro = htmlspecialchars((string) ($token ?? ''), ENT_QUOTES, 'UTF-8'); ?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Redefinir senha - Disparador.net</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<link rel="stylesheet" href="<?= ASSET_URL; ?>/css/style.css?v=6">
</head>
<body class="login-custom-page">
<div class="login-custom-wrapper"><div class="login-custom-card">
    <h3 class="text-center mb-3">Definir nova senha</h3>
    <?php require __DIR__ . '/../components/flash.php'; ?>
    <p class="text-muted text-center">Informe uma senha forte com letras maiúsculas, minúsculas, números e caractere especial.</p>
    <form method="POST" action="<?= rtrim(BASE_URL, '/'); ?>/index.php?url=login/salvarNovaSenha">
        <?= Csrf::input(); ?>
        <input type="hidden" name="token_recuperacao" value="<?= $tokenSeguro; ?>">
        <div class="form-group"><label>Nova senha</label><input type="password" name="nova_senha" class="form-control" autocomplete="new-password" maxlength="255" required></div>
        <div class="form-group"><label>Confirmar nova senha</label><input type="password" name="confirmar_senha" class="form-control" autocomplete="new-password" maxlength="255" required></div>
        <button type="submit" class="btn btn-primary btn-block btn-login-custom"><i class="fas fa-save"></i> Salvar nova senha</button>
    </form>
</div></div>
</body>
</html>
