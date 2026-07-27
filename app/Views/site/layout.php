<!DOCTYPE html>
<html lang="pt-br">

<head>
<?php $googleTagManagerSection = 'head'; require __DIR__ . '/../partials/google_tag_manager.php'; ?>

<meta charset="UTF-8">

<title><?= $titulo ?? 'Disparador.net'; ?></title>

<meta
name="viewport"
content="width=device-width, initial-scale=1"
>

<link
rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css"
>

<link
rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"
>

</head>

<body>
<?php $googleTagManagerSection = 'body'; require __DIR__ . '/../partials/google_tag_manager.php'; ?>

<?= $conteudo; ?>

</body>

</html>
