<!DOCTYPE html>
<html lang="pt-br">

<head>

<meta charset="UTF-8">

<title>Login</title>

<link rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

</head>

<body class="hold-transition login-page">

<div class="login-box">

<div class="login-logo">
<b>WhatsApp</b> Disparador
</div>

<div class="card">

<?php require '../app/Views/components/flash.php'; ?>

<form
action="<?= BASE_URL; ?>/index.php?url=login/autenticar"
method="POST"
>

<div class="input-group mb-3">

<input
type="email"
name="email"
class="form-control"
placeholder="E-mail"
required
>

</div>

<div class="input-group mb-3">

<input
type="password"
name="senha"
class="form-control"
placeholder="Senha"
required
>

</div>

<button
type="submit"
class="btn btn-primary btn-block"
>

Entrar

</button>

</form>

</div>

</div>

</div>

</body>
</html>