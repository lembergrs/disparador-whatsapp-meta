<!DOCTYPE html>
<html lang="pt-br">

<head>
<meta charset="UTF-8">
<title>Esqueci minha senha</title>

<meta name="robots" content="noindex, nofollow">
<meta name="viewport" content="width=device-width, initial-scale=1">

<link rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">

<link rel="stylesheet"
href="<?= BASE_URL; ?>/public/assets/css/style.css">
</head>

<body class="login-custom-page">

<div class="login-custom-wrapper">

<div class="login-custom-card">

<h3 class="text-center mb-3">
Recuperar senha
</h3>

<p class="text-muted text-center">
Informe seu e-mail para receber as instruções de recuperação.
</p>

<form method="POST" action="#">

<div class="form-group">

<label>E-mail</label>

<input
type="email"
class="form-control"
placeholder="seuemail@empresa.com"
required
>

</div>

<button
type="submit"
class="btn btn-primary btn-block btn-login-custom"
disabled
>
Enviar instruções
</button>

</form>

<div class="text-center mt-3">

<a href="<?= BASE_URL; ?>/index.php?url=login">
Voltar ao login
</a>

</div>

</div>

</div>

</body>
</html>
