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
href="<?= ASSET_URL; ?>/css/style.css?v=6">

<script src="https://www.google.com/recaptcha/api.js" async defer></script>

</head>

<body class="login-custom-page">

<div class="login-custom-wrapper">

    <div class="login-custom-card">

        <div class="login-logo-area">

            <div class="login-image-placeholder">

                <a class="navbar-brand d-flex align-items-center" href="<?= BASE_URL; ?>/index.php?url=site">
                    <img
                    src="<?= ASSET_URL; ?>/img/logo-disparador.png"
                    alt="Disparador"
                    onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                    >
                </a>
                <div class="login-icon-fallback">

                    <i class="fab fa-whatsapp"></i>

                </div>

            </div>

            <p>
                Campanhas, conversas e automação em uma única plataforma.
            </p>

        </div>

        <?php require __DIR__ . '/../components/flash.php'; ?>

        <form
        action="<?= rtrim(BASE_URL, '/'); ?>/index.php?url=login/autenticar"
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
                    autocomplete="username"
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
                    autocomplete="current-password"
                    required
                    >

                </div>

                <?php if(defined('RECAPTCHA_SITE_KEY') && RECAPTCHA_SITE_KEY != ''){ ?>

                <div class="mt-4 mb-3">

                    <div
                        id="recaptchaLoading"
                        class="alert alert-warning text-center mb-3"
                    >
                        <i class="fas fa-shield-alt"></i>
                        Aguarde o carregamento da proteção de segurança.
                    </div>

                    <div class="d-flex justify-content-center">
                        <div
                            class="g-recaptcha"
                            data-sitekey="<?= RECAPTCHA_SITE_KEY; ?>"
                            data-callback="recaptchaValidado"
                        ></div>
                    </div>

                </div>

                <?php } ?>

                <button
                    type="submit"
                    id="btnLogin"
                    class="btn btn-primary btn-block btn-login-custom"
                    disabled
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

            </div>

        </form>

        <div class="login-footer">

            <small>
                © <?= date('Y'); ?> RL2 Net. Todos os direitos reservados.
            </small>

        </div>

    </div>

</div>
<script>

function recaptchaValidado()
{
    var btnLogin = document.getElementById('btnLogin');

    if(btnLogin){
        btnLogin.disabled = false;
    }
}

document.addEventListener('DOMContentLoaded', function(){

    var tentativas = 0;

    var verificarRecaptcha = setInterval(function(){

        tentativas++;

        var iframe = document.querySelector('.g-recaptcha iframe');

        if(iframe){

            var aviso = document.getElementById('recaptchaLoading');

            if(aviso){
                aviso.style.display = 'none';
            }

            clearInterval(verificarRecaptcha);
        }

        if(tentativas >= 50){
            clearInterval(verificarRecaptcha);
        }

    }, 100);

});

</script>

</body>

</html>