<?php

use Core\Session;

$flash = Session::getFlash();

?>
<!DOCTYPE html>
<html lang="pt-br">

<head>

    <meta charset="utf-8">

    <title>
        Cadastro | Disparador WhatsApp
    </title>

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <link
        rel="stylesheet"
        href="<?= BASE_URL ?>assets/adminlte/plugins/fontawesome-free/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="<?= BASE_URL ?>assets/adminlte/dist/css/adminlte.min.css"
    >

</head>

<body class="hold-transition">

<div class="container py-5">

    <div class="row justify-content-center">

        <div class="col-lg-7">

            <div class="card card-primary">

                <?php if($flash): ?>

                    <div
                        class="alert alert-<?= $flash['type'] === 'error'
                            ? 'danger'
                            : 'success' ?>"
                    >

                        <?= $flash['message'] ?>

                    </div>

                <?php endif; ?>

                <div class="card-header">

                    <h3 class="card-title">
                        Solicitação de Cadastro
                    </h3>

                </div>

                <form
                    action="<?= BASE_URL ?>/index.php?url=site/salvar"
                    method="post"
                >

                    <div class="card-body">

                        <div class="form-group">

                            <label>
                                Tipo de Pessoa
                            </label>

                            <select
                                name="tipo_pessoa"
                                id="tipo_pessoa"
                                class="form-control"
                            >

                                <option value="PJ">
                                    Pessoa Jurídica
                                </option>

                                <option value="PF">
                                    Pessoa Física
                                </option>

                            </select>

                        </div>

                        <div class="form-group">

                            <label>
                                CPF / CNPJ
                            </label>

                            <input
                                type="text"
                                name="cpf_cnpj"
                                id="cpf_cnpj"
                                class="form-control cpf_cnpj"
                                required
                            >

                        </div>

                        <div class="form-group">

                            <label id="label_nome">

                                Nome da Empresa

                            </label>

                            <input
                                type="text"
                                name="nome"
                                class="form-control"
                                required
                            >

                        </div>

                        <div
                            class="form-group"
                            id="grupo_razao_social"
                        >

                            <label>

                                Razão Social

                            </label>

                            <input
                                type="text"
                                name="razao_social"
                                class="form-control"
                            >

                        </div>

                        <div
                            class="form-group"
                            id="grupo_nome_fantasia"
                        >

                            <label>

                                Nome Fantasia

                            </label>

                            <input
                                type="text"
                                name="nome_fantasia"
                                class="form-control"
                            >

                        </div>

                        <div class="form-group">

                            <label>
                                E-mail
                            </label>

                            <input
                                type="email"
                                name="email"
                                class="form-control"
                                required
                            >

                        </div>

                        <div class="form-group">

                            <label>
                                Telefone
                            </label>

                            <input
                                type="text"
                                name="telefone"
                                class="form-control telefone"
                                required
                            >

                        </div>

                        <div class="row">

                            <div class="col-md-6">

                                <div class="form-group">

                                    <label>
                                        Senha
                                    </label>

                                    <input
                                        type="password"
                                        name="senha"
                                        class="form-control"
                                        required
                                    >

                                </div>

                            </div>

                            <div class="col-md-6">

                                <div class="form-group">

                                    <label>
                                        Confirmar Senha
                                    </label>

                                    <input
                                        type="password"
                                        name="confirmar_senha"
                                        class="form-control"
                                        required
                                    >

                                </div>

                            </div>

                        </div>

                        <div class="alert alert-info">

                            Após o cadastro, sua conta ficará aguardando aprovação da equipe administrativa.

                        </div>

                    </div>

                    <div class="card-footer">

                        <button
                            type="submit"
                            class="btn btn-success"
                        >

                            Solicitar Cadastro

                        </button>

                        <a
                            href="<?= BASE_URL ?>/index.php?url=site"
                            class="btn btn-secondary"
                        >

                            Voltar

                        </a>

                    </div>

                </form>

            </div>

        </div>

    </div>

</div>

<script src="<?= BASE_URL ?>assets/adminlte/plugins/jquery/jquery.min.js"></script>
<script src="<?= BASE_URL ?>assets/adminlte/plugins/inputmask/jquery.inputmask.min.js"></script>

<script>

$(function(){

    $('.telefone').inputmask(
        '(99) 99999-9999'
    );

    atualizarCampos();

    $('#tipo_pessoa').on(
        'change',
        atualizarCampos
    );

    function atualizarCampos()
    {
        let tipo =
            $('#tipo_pessoa').val();

        if(tipo === 'PF')
        {
            $('#label_nome')
                .text('Nome Completo');

            $('#grupo_razao_social')
                .hide();

            $('#grupo_nome_fantasia')
                .hide();

            $('#cpf_cnpj')
                .inputmask(
                    '999.999.999-99'
                );
        }
        else
        {
            $('#label_nome')
                .text('Nome da Empresa');

            $('#grupo_razao_social')
                .show();

            $('#grupo_nome_fantasia')
                .show();

            $('#cpf_cnpj')
                .inputmask(
                    '99.999.999/9999-99'
                );
        }
    }

});

</script>

</body>
</html>