<?php

use Core\Session;

$flash = Session::getFlash();
$dadosCadastro = Session::get('cadastro_dados') ?? [];
Session::remove('cadastro_dados');

?>
<!DOCTYPE html>
<html lang="pt-br">

<head>

    <meta charset="utf-8">

    <title>Cadastro | Disparador RL2 Net</title>

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <link rel="stylesheet" href="<?= ASSET_URL; ?>/css/style.css?v=11">

    <?php if(defined('RECAPTCHA_SITE_KEY') && RECAPTCHA_SITE_KEY != ''){ ?>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <?php } ?>

</head>

<body class="site-cadastro-page">

<div class="container py-5">

    <div class="text-center mb-4">

        <a href="<?= BASE_URL; ?>/index.php?url=site">
            <img
            src="<?= ASSET_URL; ?>/img/logo-disparador.png"
            alt="Disparador"
            style="max-height:85px; max-width:320px;"
            >
        </a>

    </div>

    <div class="row justify-content-center align-items-stretch">

        <div class="col-lg-5 mb-4 mb-lg-0">

            <div class="card site-card-feature h-100">

                <div class="card-body p-4">

                    <span class="badge badge-success mb-3">
                        WhatsApp Business API
                    </span>

                    <h2 class="font-weight-bold mb-3">
                        Crie sua conta grátis no Disparador
                    </h2>

                    <p class="text-muted">
                        Cadastre sua empresa, acesse o painel imediatamente e conecte seu WhatsApp para iniciar o teste gratuito.
                    </p>

                    <hr>

                    <p>
                        <i class="fas fa-check-circle text-success"></i>
                        Campanhas com templates oficiais
                    </p>

                    <p>
                        <i class="fas fa-check-circle text-success"></i>
                        Segmentação por listas de contatos
                    </p>

                    <p>
                        <i class="fas fa-check-circle text-success"></i>
                        Central de atendimento estilo WhatsApp
                    </p>

                    <p>
                        <i class="fas fa-check-circle text-success"></i>
                        Preparado para múltiplos números
                    </p>

                    <div class="alert alert-info mt-4 mb-0">
                        Após criar sua conta, conecte seu WhatsApp Business para iniciar o teste de até 7 dias ou 200 mensagens.
                    </div>

                </div>

            </div>

        </div>

        <div class="col-lg-7">

            <div class="card site-card-feature">

                <div class="card-body p-4">

                    <h4 class="font-weight-bold mb-1">
                        Dados de cadastro
                    </h4>

                    <p class="text-muted mb-4">
                        Preencha os dados abaixo para criar sua conta. Depois, conecte seu WhatsApp para iniciar o teste.
                    </p>

                    <?php if($flash): ?>

                        <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : 'success'; ?> alert-dismissible fade show">

                            <?= $flash['message']; ?>

                            <button
                            type="button"
                            class="close"
                            data-dismiss="alert"
                            aria-label="Close"
                            >
                                <span aria-hidden="true">&times;</span>
                            </button>

                        </div>

                    <?php endif; ?>

                    <form
                    action="<?= rtrim(BASE_URL, '/'); ?>/index.php?url=site/salvar"
                    method="post"
                    id="formCadastroPublico"
                    novalidate
                    >

                        <div class="row">

                            <div class="col-md-6">

                                <div class="form-group">

                                    <label>Tipo de Pessoa</label>

                                    <select
                                    name="tipo_pessoa"
                                    id="tipo_pessoa"
                                    class="form-control"
                                    >
                                        <option value="PJ" <?= ($dadosCadastro['tipo_pessoa'] ?? 'PJ') == 'PJ' ? 'selected' : ''; ?>>Pessoa Jurídica</option>
                                        <option value="PF" <?= ($dadosCadastro['tipo_pessoa'] ?? '') == 'PF' ? 'selected' : ''; ?>>Pessoa Física</option>
                                    </select>

                                </div>

                            </div>

                            <div class="col-md-6">

                                <div class="form-group">

                                    <label>CPF / CNPJ</label>

                                    <input
                                    type="text"
                                    name="cpf_cnpj"
                                    id="cpf_cnpj"
                                    class="form-control cpf_cnpj"
                                    value="<?= htmlspecialchars($dadosCadastro['cpf_cnpj'] ?? ''); ?>"
                                    required
                                    >

                                    <div
                                    id="erroCpfCnpj"
                                    class="invalid-feedback"
                                    >
                                        Informe um CPF ou CNPJ válido.
                                    </div>

                                </div>

                            </div>

                        </div>

                        <div class="form-group">

                            <label id="label_nome">Nome da Empresa</label>

                            <input
                            type="text"
                            name="nome"
                            class="form-control"
                            value="<?= htmlspecialchars($dadosCadastro['nome'] ?? ''); ?>"
                            required
                            >

                        </div>

                        <div class="row">

                            <div class="col-md-6" id="grupo_razao_social">

                                <div class="form-group">

                                    <label>Razão Social</label>

                                    <input
                                    type="text"
                                    name="razao_social"
                                    class="form-control"
                                    value="<?= htmlspecialchars($dadosCadastro['razao_social'] ?? ''); ?>"
                                    >

                                </div>

                            </div>

                            <div class="col-md-6" id="grupo_nome_fantasia">

                                <div class="form-group">

                                    <label>Nome Fantasia</label>

                                    <input
                                    type="text"
                                    name="nome_fantasia"
                                    class="form-control"
                                    value="<?= htmlspecialchars($dadosCadastro['nome_fantasia'] ?? ''); ?>"
                                    >

                                </div>

                            </div>

                        </div>

                        <div class="row">

                            <div class="col-md-6">

                                <div class="form-group">

                                    <label>E-mail</label>

                                    <input
                                    type="email"
                                    name="email"
                                    class="form-control"
                                    value="<?= htmlspecialchars($dadosCadastro['email'] ?? ''); ?>"
                                    autocomplete="email"
                                    required
                                    >

                                    <div
                                    id="erroEmailCadastro"
                                    class="invalid-feedback"
                                    >
                                        Informe um e-mail válido.
                                    </div>

                                </div>

                            </div>

                            <div class="col-md-6">

                                <div class="form-group">

                                    <label>WhatsApp para contato</label>

                                    <input
                                    type="text"
                                    name="telefone"
                                    class="form-control telefone"
                                    value="<?= htmlspecialchars($dadosCadastro['telefone'] ?? ''); ?>"
                                    required
                                    >

                                </div>

                            </div>

                        </div>

                        <div class="row">

                            <div class="col-md-6">

                                <div class="form-group">

                                    <label>Senha</label>

                                    <input
                                    type="password"
                                    name="senha"
                                    class="form-control"
                                    autocomplete="new-password"
                                    minlength="6"
                                    required
                                    >

                                    <div
                                    id="erroSenhaCadastro"
                                    class="invalid-feedback"
                                    >
                                        A senha deve ter pelo menos 6 caracteres.
                                    </div>

                                </div>

                            </div>

                            <div class="col-md-6">

                                <div class="form-group">

                                    <label>Confirmar Senha</label>

                                    <input
                                    type="password"
                                    name="confirmar_senha"
                                    class="form-control"
                                    autocomplete="new-password"
                                    required
                                    >

                                    <div
                                    id="erroConfirmarSenhaCadastro"
                                    class="invalid-feedback"
                                    >
                                        As senhas informadas não conferem.
                                    </div>

                                </div>

                            </div>

                        </div>

                        <div class="alert alert-light border mt-3">

                            <small class="text-muted">

                                <i class="fas fa-info-circle text-info"></i>

                                A utilização da API Oficial do WhatsApp Business está sujeita às regras e validações da Meta.
                                Durante a conexão do número, poderão ser solicitadas informações adicionais para validação da conta.

                            </small>

                        </div>

                        <?php if(defined('RECAPTCHA_SITE_KEY') && RECAPTCHA_SITE_KEY != ''){ ?>

                        <div class="mb-4 mt-3">

                            <div
                            class="g-recaptcha"
                            data-sitekey="<?= RECAPTCHA_SITE_KEY; ?>"
                            ></div>

                        </div>

                        <?php } ?>

                        <div class="form-check mb-4">

                            <input
                            type="checkbox"
                            id="aceiteTermos"
                            name="aceiteTermos"
                            <?= !empty($dadosCadastro['aceiteTermos']) ? 'checked' : ''; ?>
                            required
                            >

                            <label
                            class="form-check-label"
                            for="aceiteTermos"
                            >
                                Li e concordo com os
                                <a href="#" data-toggle="modal" data-target="#modalTermos">
                                    Termos de Uso
                                </a>

                                e a

                                <a href="#" data-toggle="modal" data-target="#modalPrivacidade">
                                    Política de Privacidade
                                </a>.
                            </label>

                        </div>

                        <button
                        type="submit"
                        id="btnSolicitarCadastro"
                        class="btn btn-success btn-block"
                        disabled
                        >
                            Criar conta grátis
                        </button>

                    </form>

                </div>

            </div>

        </div>

    </div>

</div>

<div class="modal fade" id="modalTermos" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Termos de Uso</h5>

                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <?php require __DIR__ . '/termos_uso.php'; ?>
            </div>

            <div class="modal-footer">

                <button
                type="button"
                class="btn btn-secondary"
                data-dismiss="modal"
                >
                    Fechar
                </button>

            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalPrivacidade" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Política de Privacidade</h5>

                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <?php require __DIR__ . '/politica_privacidade.php'; ?>
            </div>

            <div class="modal-footer">

                <button
                type="button"
                class="btn btn-secondary"
                data-dismiss="modal"
                >
                    Fechar
                </button>

            </div>

        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.inputmask/5.0.8/jquery.inputmask.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

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

        $('#cpf_cnpj').inputmask('remove');

        if(tipo === 'PF'){

            $('#label_nome').text('Nome Completo');

            $('#grupo_razao_social').hide();
            $('#grupo_nome_fantasia').hide();

            $('#cpf_cnpj').inputmask(
                '999.999.999-99'
            );

        }else{

            $('#label_nome').text('Nome da Empresa');

            $('#grupo_razao_social').show();
            $('#grupo_nome_fantasia').show();

            $('#cpf_cnpj').inputmask(
                '99.999.999/9999-99'
            );

        }
    }

    const tamanhoMinimoSenha = 6;

    function apenasNumeros(valor)
    {
        return String(valor || '').replace(/\D/g, '');
    }

    function cpfValido(cpf)
    {
        cpf = apenasNumeros(cpf);

        if(cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)){
            return false;
        }

        for(let t = 9; t < 11; t++){
            let soma = 0;

            for(let i = 0; i < t; i++){
                soma += parseInt(cpf.charAt(i), 10) * ((t + 1) - i);
            }

            let digito = ((10 * soma) % 11) % 10;

            if(parseInt(cpf.charAt(t), 10) !== digito){
                return false;
            }
        }

        return true;
    }

    function calcularDigitoCnpj(base, pesos)
    {
        let soma = 0;

        pesos.forEach(function(peso, indice){
            soma += parseInt(base.charAt(indice), 10) * peso;
        });

        let resto = soma % 11;

        return resto < 2 ? '0' : String(11 - resto);
    }

    function cnpjValido(cnpj)
    {
        cnpj = apenasNumeros(cnpj);

        if(cnpj.length !== 14 || /^(\d)\1{13}$/.test(cnpj)){
            return false;
        }

        let digito1 = calcularDigitoCnpj(
            cnpj.substring(0, 12),
            [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]
        );

        let digito2 = calcularDigitoCnpj(
            cnpj.substring(0, 12) + digito1,
            [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]
        );

        return cnpj.slice(-2) === digito1 + digito2;
    }

    function documentoValido(documento)
    {
        documento = apenasNumeros(documento);

        if(documento.length === 11){
            return cpfValido(documento);
        }

        if(documento.length === 14){
            return cnpjValido(documento);
        }

        return false;
    }

    function emailValido(email)
    {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(email || '').trim());
    }

    function marcarCampo(campo, valido)
    {
        campo.toggleClass('is-invalid', !valido);
        campo.toggleClass('is-valid', valido && campo.val().trim() !== '');

        return valido;
    }

    function validarDocumentoCadastro()
    {
        let campo = $('#cpf_cnpj');
        let valido = documentoValido(campo.val());

        return marcarCampo(campo, valido);
    }

    function validarEmailCadastro()
    {
        let campo = $('[name="email"]');
        let valido = emailValido(campo.val());

        return marcarCampo(campo, valido);
    }

    function validarSenhaCadastro()
    {
        let campo = $('[name="senha"]');
        let valido = campo.val().length >= tamanhoMinimoSenha;

        marcarCampo(campo, valido);
        validarConfirmacaoSenhaCadastro();

        return valido;
    }

    function validarConfirmacaoSenhaCadastro()
    {
        let senha = $('[name="senha"]').val();
        let campo = $('[name="confirmar_senha"]');
        let valido = campo.val() !== '' && campo.val() === senha;

        return marcarCampo(campo, valido);
    }

    function formularioCadastroValido()
    {
        let valido = true;

        valido = validarDocumentoCadastro() && valido;
        valido = validarEmailCadastro() && valido;
        valido = validarSenhaCadastro() && valido;
        valido = validarConfirmacaoSenhaCadastro() && valido;

        return valido;
    }

    function atualizarBotaoCadastro()
    {
        $('#btnSolicitarCadastro').prop(
            'disabled',
            !$('#aceiteTermos').is(':checked')
        );
    }

    $('#cpf_cnpj').on('blur', validarDocumentoCadastro);
    $('[name="email"]').on('blur', validarEmailCadastro);
    $('[name="senha"]').on('blur input', validarSenhaCadastro);
    $('[name="confirmar_senha"]').on('blur input', validarConfirmacaoSenhaCadastro);

    $('#formCadastroPublico').on('submit', function(e){
        if(!formularioCadastroValido()){
            e.preventDefault();
            e.stopPropagation();
        }
    });

    $('#aceiteTermos').on(
        'change',
        atualizarBotaoCadastro
    );

    atualizarBotaoCadastro();

});

</script>

</body>
</html>