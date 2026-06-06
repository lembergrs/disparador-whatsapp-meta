<!DOCTYPE html>
<html lang="pt-br">

<head>

<meta charset="UTF-8">

<title>Login - WhatsApp Disparador</title>

<meta name="viewport" content="width=device-width, initial-scale=1">

<link rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<link rel="stylesheet"
href="<?= BASE_URL; ?>/assets/css/style.css">

<script src="https://www.google.com/recaptcha/api.js" async defer></script>

</head>

<body class="login-custom-page">

<div class="login-custom-wrapper">

    <div class="login-custom-card">

        <div class="login-logo-area">

            <div class="login-image-placeholder">

                <img
                src="<?= BASE_URL; ?>/assets/img/logo-login.png"
                alt="Logo"
                onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                >

                <div class="login-icon-fallback">

                    <i class="fab fa-whatsapp"></i>

                </div>

            </div>

            <h2>
                WhatsApp Disparador
            </h2>

            <p>
                Campanhas, conversas e automação em uma única plataforma.
            </p>

        </div>

        <?php require __DIR__ . '/../components/flash.php'; ?>

        <form
        action="<?= BASE_URL; ?>/index.php?url=login/autenticar"
        method="POST"
        >

            <div class="form-group">

                <label>E-mail</label>

                <div class="input-group">

                    <div class="input-group-prepend">

                        <span class="input-group-text">
                            <i class="fas fa-envelope"></i>
                        </span>

                    </div>

                    <input
                    type="email"
                    name="email"
                    class="form-control"
                    placeholder="seuemail@empresa.com"
                    required
                    >

                </div>

            </div>

            <div class="form-group">

                <label>Senha</label>

                <div class="input-group">

                    <div class="input-group-prepend">

                        <span class="input-group-text">
                            <i class="fas fa-lock"></i>
                        </span>

                    </div>

                    <input
                    type="password"
                    name="senha"
                    class="form-control"
                    placeholder="Digite sua senha"
                    required
                    >

                </div>

            </div>

            <?php if(defined('RECAPTCHA_SITE_KEY') && RECAPTCHA_SITE_KEY != ''){ ?>

            <div class="mb-3">

                <div
                class="g-recaptcha"
                data-sitekey="<?= RECAPTCHA_SITE_KEY; ?>"
                ></div>

            </div>

            <?php } ?>

            <button
            type="submit"
            class="btn btn-primary btn-block btn-login-custom"
            >

                <i class="fas fa-sign-in-alt"></i>
                Entrar

            </button>

            <div class="text-center mt-3">

                <a
                href="<?= BASE_URL; ?>/index.php?url=login/esqueciSenha"
                class="text-muted"
                >
                    Esqueci minha senha
                </a>

            </div>

        </form>

        <div class="login-footer">

            <small>
                © <?= date('Y'); ?> Rose Distribuição
            </small>

        </div>

    </div>

</div>

<script src="https://www.google.com/recaptcha/api.js" async defer></script>

</body>

</html>