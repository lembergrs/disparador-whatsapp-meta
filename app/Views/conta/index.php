<?php
if(!function_exists('contaEsc')){
    function contaEsc($valor)
    {
        return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
    }
}

if(!function_exists('contaDocumento')){
    function contaDocumento($documento)
    {
        $documento = preg_replace('/\D/', '', (string) $documento);

        if(strlen($documento) === 11){
            return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $documento);
        }

        if(strlen($documento) === 14){
            return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $documento);
        }

        return $documento ?: '-';
    }
}

$tipoPessoa = ($cliente['CLI_TipoPessoa'] ?? '') === 'PF' ? 'Pessoa Física' : 'Pessoa Jurídica';
$mensagemSuporte = 'Para alterar estas informações entre em contato com o suporte.';
?>

<div class="row">
    <div class="col-lg-8">
        <div class="card card-primary card-outline">
            <div class="card-header">
                <h3 class="card-title">Dados Cadastrais</h3>
            </div>

            <form method="post" action="<?= BASE_URL; ?>/index.php?url=conta/atualizarDados" id="formDadosConta">
                <div class="card-body">
                    <div class="alert alert-info">
                        <?= contaEsc($mensagemSuporte); ?>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Tipo Pessoa</label>
                                <input type="text" class="form-control" value="<?= contaEsc($tipoPessoa); ?>" readonly>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label>CPF/CNPJ</label>
                                <input type="text" class="form-control" value="<?= contaEsc(contaDocumento($cliente['CLI_CPF_CNPJ'] ?? '')); ?>" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Razão Social</label>
                        <input type="text" class="form-control" value="<?= contaEsc($cliente['CLI_RazaoSocial'] ?? '-'); ?>" readonly>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Nome de contato</label>
                                <input type="text" name="nome" class="form-control" maxlength="120" value="<?= contaEsc($cliente['CLI_Nome'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Nome Fantasia</label>
                                <input type="text" name="nome_fantasia" class="form-control" maxlength="150" value="<?= contaEsc($cliente['CLI_NomeFantasia'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Email de acesso/login</label>
                                <input type="email" class="form-control" value="<?= contaEsc($cliente['CLI_Email'] ?? ''); ?>" readonly>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Telefone</label>
                                <input type="text" name="telefone" class="form-control" maxlength="20" value="<?= contaEsc($cliente['CLI_Telefone'] ?? ''); ?>" required>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-footer text-right">
                    <button type="submit" class="btn btn-primary">Salvar dados</button>
                </div>
            </form>
        </div>

        <?php if($possuiEndereco){ ?>
            <div class="card card-secondary card-outline">
                <div class="card-header">
                    <h3 class="card-title">Endereço</h3>
                </div>

                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>CEP</label>
                                <input type="text" name="cep" form="formDadosConta" class="form-control" maxlength="9" value="<?= contaEsc($cliente['CLI_CEP'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="form-group">
                                <label>Logradouro</label>
                                <input type="text" name="logradouro" form="formDadosConta" class="form-control" maxlength="150" value="<?= contaEsc($cliente['CLI_Logradouro'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Número</label>
                                <input type="text" name="numero" form="formDadosConta" class="form-control" maxlength="20" value="<?= contaEsc($cliente['CLI_Numero'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="form-group">
                                <label>Complemento</label>
                                <input type="text" name="complemento" form="formDadosConta" class="form-control" maxlength="100" value="<?= contaEsc($cliente['CLI_Complemento'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Bairro</label>
                                <input type="text" name="bairro" form="formDadosConta" class="form-control" maxlength="100" value="<?= contaEsc($cliente['CLI_Bairro'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Cidade</label>
                                <input type="text" name="cidade" form="formDadosConta" class="form-control" maxlength="100" value="<?= contaEsc($cliente['CLI_Cidade'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>UF</label>
                                <input type="text" name="uf" form="formDadosConta" class="form-control" maxlength="2" value="<?= contaEsc($cliente['CLI_UF'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php } ?>
    </div>

    <div class="col-lg-4">
        <?php if(!\Core\Auth::isImpersonating()){ ?>
        <div class="card card-warning card-outline" id="seguranca">
            <div class="card-header">
                <h3 class="card-title">Segurança</h3>
            </div>

            <form method="post" action="<?= BASE_URL; ?>/index.php?url=conta/alterarSenha">
                <div class="card-body">
                    <div class="form-group">
                        <label>Senha atual</label>
                        <input type="password" name="senha_atual" class="form-control" autocomplete="current-password" required>
                    </div>

                    <div class="form-group">
                        <label>Nova senha</label>
                        <input type="password" name="nova_senha" id="novaSenhaConta" class="form-control" minlength="8" data-password-strength autocomplete="new-password" required>
                    </div>

                    <div class="form-group">
                        <label>Confirmar senha</label>
                        <input type="password" name="confirmar_senha" class="form-control" minlength="8" data-password-confirm="#novaSenhaConta" autocomplete="new-password" required>
                    </div>
                </div>

                <div class="card-footer text-right">
                    <button type="submit" class="btn btn-warning">Alterar senha</button>
                </div>
            </form>
        </div>
        <?php }else{ ?>
            <div class="alert alert-warning">Alteração de senha indisponível durante o modo suporte.</div>
        <?php } ?>
    </div>
</div>
