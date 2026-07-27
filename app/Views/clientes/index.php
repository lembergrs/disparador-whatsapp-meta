<?php
if(!function_exists('escClienteAttr')){
    function escClienteAttr($valor)
    {
        return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES, 'UTF-8');
    }
}
?>

<div class="card">

<div class="card-header">

<a
href="#"
id="btnNovoCliente"
class="btn btn-primary"
data-toggle="modal"
data-target="#modalCliente"
>

<i class="fas fa-plus"></i>

Novo Cliente

</a>

<div class="mt-3">

    <a href="<?= BASE_URL; ?>/index.php?url=cliente"
    class="btn btn-sm <?= empty($statusFiltro) ? 'btn-primary' : 'btn-outline-primary'; ?>">
        Ativos/Pendentes
    </a>

    <a href="<?= BASE_URL; ?>/index.php?url=cliente/index/pendente"
    class="btn btn-sm <?= $statusFiltro == 'pendente' ? 'btn-warning' : 'btn-outline-warning'; ?>">
        Pendentes
    </a>

    <a href="<?= BASE_URL; ?>/index.php?url=cliente/index/ativo"
    class="btn btn-sm <?= $statusFiltro == 'ativo' ? 'btn-success' : 'btn-outline-success'; ?>">
        Ativos
    </a>

    <a href="<?= BASE_URL; ?>/index.php?url=cliente/index/inativo"
    class="btn btn-sm <?= $statusFiltro == 'inativo' ? 'btn-danger' : 'btn-outline-danger'; ?>">
        Inativos
    </a>

</div>

</div>

<div class="card-body">

<table
id="tabelaClientes"
class="table table-bordered table-striped table-hover datatable"
>

<thead>

<tr>

<th>ID</th>
<th>Cliente</th>
<th>CPF/CNPJ</th>
<th>Email</th>
<th>Telefone</th>
<th>Cadastro</th>
<th>Pagamento</th>
<th>Assinatura</th>
<th>Ações</th>

</tr>

</thead>

<tbody>

<?php foreach($clientes as $cliente){ ?>

<tr>

<td>
<?= (int) $cliente['CLI_ID']; ?>
</td>

<td>

<?= escClienteAttr($cliente['CLI_Nome']); ?>

<?php if(!empty($cliente['CLI_RazaoSocial'])){ ?>

<br>

<small class="text-muted">

<?= escClienteAttr($cliente['CLI_RazaoSocial']); ?>

</small>

<?php } ?>

</td>

<td>
<?= escClienteAttr($cliente['CLI_CPF_CNPJ']); ?>
</td>

<td>
<?= escClienteAttr($cliente['CLI_Email']); ?>
</td>

<td>
<?= escClienteAttr($cliente['CLI_Telefone']); ?>
</td>

<td>

    <?php
    switch($cliente['CLI_StatusCadastro']){

        case 'ativo':
            echo '<span class="badge badge-success">Ativo</span>';
            break;

        case 'pendente':
            echo '<span class="badge badge-warning">Pendente</span>';
            break;

        case 'inativo':
            echo '<span class="badge badge-danger">Inativo</span>';
            break;
    }
    ?>

</td>

<td>

<?php if(
    $cliente['CLI_StatusPagamento'] == 'pago'
){ ?>

    <span class="badge badge-success">
        Pago
    </span>

<?php } else { ?>

    <span class="badge badge-danger">
        Pendente
    </span>

<?php } ?>

</td>

<td>
<?php if(!empty($cliente['ASS_PlanoNome'])){ ?>
    <strong><?= escClienteAttr($cliente['ASS_PlanoNome']); ?></strong><br>
    <small class="text-muted">
        <?= escClienteAttr($cliente['ASS_Ciclo']); ?> |
        R$ <?= number_format($cliente['ASS_Valor'], 2, ',', '.'); ?>
    </small><br>
    <small>
        <?= escClienteAttr(ucfirst($cliente['ASS_Status'])); ?> |
        Próx.: <?= $cliente['ASS_DataProximaCobranca'] ? date('d/m/Y', strtotime($cliente['ASS_DataProximaCobranca'])) : '-'; ?>
    </small>
<?php }else{ ?>
    <span class="text-muted">Sem assinatura</span>
<?php } ?>
</td>

<td>

<?php if($cliente['CLI_Ativo'] == 'N'){ ?>

<a
href="#" data-post-url="<?= BASE_URL; ?>/index.php?url=cliente/aprovar&id=<?= (int) $cliente['CLI_ID']; ?>"
class="btn btn-success btn-sm"
onclick="return confirm('Deseja aprovar este cadastro?')"
>

<i class="fas fa-check"></i>

</a>

<?php } ?>

<button
type="button"
class="btn btn-info btn-sm btnEditarCliente"

data-id="<?= (int) $cliente['CLI_ID']; ?>"

data-nome="<?= escClienteAttr($cliente['CLI_Nome']); ?>"

data-razao="<?= escClienteAttr($cliente['CLI_RazaoSocial']); ?>"

data-email="<?= escClienteAttr($cliente['CLI_Email']); ?>"

data-telefone="<?= escClienteAttr($cliente['CLI_Telefone']); ?>"

data-documento="<?= escClienteAttr($cliente['CLI_CPF_CNPJ']); ?>"

data-tipo="<?= escClienteAttr($cliente['CLI_TipoPessoa']); ?>"

data-mensalidade="<?= number_format($cliente['CLI_ValorMensalidade'],2,',','.'); ?>"

data-vencimento="<?= escClienteAttr($cliente['CLI_Vencimento']); ?>"

data-status="<?= escClienteAttr($cliente['CLI_StatusPagamento']); ?>"

data-observacoes="<?= escClienteAttr($cliente['CLI_Observacoes']); ?>"
data-origem-cadastro="<?= escClienteAttr(\Models\Cliente::formatarOrigemCadastro($cliente['CLI_OrigemCadastro'] ?? null, $cliente['CLI_OrigemCadastroOutro'] ?? null)); ?>"
data-cnpj-fiscal="<?= escClienteAttr($cliente['CLI_NFSe_CNPJ'] ?? ''); ?>"
data-razao-social-fiscal="<?= escClienteAttr($cliente['CLI_NFSe_RazaoSocial'] ?? ''); ?>"
data-cep-fiscal="<?= escClienteAttr($cliente['CLI_NFSe_CEP'] ?? ''); ?>"
data-logradouro-fiscal="<?= escClienteAttr($cliente['CLI_NFSe_Logradouro'] ?? ''); ?>"
data-numero-fiscal="<?= escClienteAttr($cliente['CLI_NFSe_Numero'] ?? ''); ?>"
data-complemento-fiscal="<?= escClienteAttr($cliente['CLI_NFSe_Complemento'] ?? ''); ?>"
data-bairro-fiscal="<?= escClienteAttr($cliente['CLI_NFSe_Bairro'] ?? ''); ?>"
data-municipio-fiscal="<?= escClienteAttr($cliente['CLI_NFSe_Municipio'] ?? ''); ?>"
data-uf-fiscal="<?= escClienteAttr($cliente['CLI_NFSe_UF'] ?? ''); ?>"
data-codigo-ibge-fiscal="<?= escClienteAttr($cliente['CLI_NFSe_CodigoIBGE'] ?? ''); ?>"
data-telefone-fiscal="<?= escClienteAttr($cliente['CLI_NFSe_Telefone'] ?? ''); ?>"
data-email-fiscal="<?= escClienteAttr($cliente['CLI_NFSe_Email'] ?? ''); ?>"
>

<i class="fas fa-edit"></i>

</button>

</a>

<?php if(
    ($usuario['nivel'] ?? null) === 'admin'
    && ($cliente['CLI_Ativo'] ?? 'N') === 'S'
    && ($cliente['CLI_StatusCadastro'] ?? '') === 'ativo'
){ ?>
<a
href="#"
data-post-url="<?= BASE_URL; ?>/index.php?url=suporte/iniciar"
data-field-cliente_id="<?= (int) $cliente['CLI_ID']; ?>"
data-confirm="Deseja acessar a conta de &quot;<?= escClienteAttr($cliente['CLI_Nome']); ?>&quot;? Você visualizará o sistema exatamente como o cliente o vê."
class="btn btn-warning btn-sm"
title="Acessar como cliente"
data-toggle="tooltip"
>
    <i class="fas fa-user-secret" aria-hidden="true"></i>
    <span class="sr-only">Acessar como cliente</span>
</a>
<?php } ?>

<?php if($cliente['CLI_StatusCadastro'] != 'inativo'){ ?>

<a
href="#" data-post-url="<?= BASE_URL; ?>/index.php?url=cliente/inativar&id=<?= (int) $cliente['CLI_ID']; ?>"
class="btn btn-danger btn-sm"
onclick="return confirm('Deseja inativar?')"
>

<i class="fas fa-trash"></i>

</a>

<?php } ?>

<?php if($cliente['CLI_StatusCadastro'] == 'inativo'){ ?>

<a
href="#" data-post-url="<?= rtrim(BASE_URL, '/') ?>/index.php?url=cliente/reativar&id=<?= (int) $cliente['CLI_ID']; ?>"
class="btn btn-success btn-sm"
onclick="return confirm('Deseja reativar este cliente?')"
>

<i class="fas fa-undo"></i>

</a>

<?php } ?>

</td>

</tr>

<?php } ?>

</tbody>

</table>

</div>

</div>





<div
class="modal fade"
id="modalCliente"
tabindex="-1"
>

<div class="modal-dialog modal-xl">

<div class="modal-content">

<form
id="formCliente"
action="<?= BASE_URL; ?>/index.php?url=cliente/salvar"
method="POST"
>

<div class="modal-header">

<h4 class="modal-title">
Novo Cliente
</h4>

<button
type="button"
class="close"
data-dismiss="modal"
aria-label="Close"
>
    <span aria-hidden="true">&times;</span>
</button>

</div>





<div class="modal-body">

<div class="row">

<!-- Tipo Pessoa -->

<div class="col-md-3">

<div class="form-group">

<label>Tipo Pessoa</label>

<input
type="hidden"
name="id"
id="cliente_id"
>

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

</div>

<!-- CPF/CNPJ -->

<div class="col-md-3">

<div class="form-group">

<label>CPF / CNPJ</label>

<input
type="text"
name="cpf_cnpj"
class="form-control cpf_cnpj"
required
>

</div>

</div>

<!-- Telefone -->

<div class="col-md-3">

<div class="form-group">

<label>Telefone</label>

<input
type="text"
name="telefone"
class="form-control telefone"
required
>

</div>

</div>

<!-- Mensalidade -->

<div class="col-md-3">

<div class="form-group">

<label>Mensalidade</label>

<input
type="text"
name="mensalidade"
class="form-control valor"
value="0,00"
>

</div>

</div>

</div>





<div class="row">

<!-- Nome -->

<div class="col-md-6">

<div class="form-group">

<label id="label_nome">

Nome Fantasia

</label>

<input
type="text"
name="nome"
class="form-control"
required
>

</div>

</div>

<!-- Razão Social -->

<div class="col-md-6 area_razao_social">

<div class="form-group">

<label>Razão Social</label>

<input
type="text"
name="razao_social"
class="form-control"
>

</div>

</div>

</div>





<div class="row">

<!-- Email -->

<div class="col-md-4">

<div class="form-group">

<label>E-mail/Login</label>

<input
type="email"
name="email"
class="form-control"
required
>

</div>

</div>

<!-- Vencimento -->

<div class="col-md-4">

<div class="form-group">

<label>Vencimento</label>

<input
type="date"
name="vencimento"
class="form-control"
>

</div>

</div>

<!-- Status -->

<div class="col-md-4">

<div class="form-group">

<label>Status Pagamento</label>

<select
name="status"
class="form-control"
>

<option value="pago">
Pago
</option>

<option value="pendente">
Pendente
</option>

</select>

</div>

</div>

</div>





<div class="card card-outline card-secondary mt-3">

<div class="card-header">
<strong>Dados fiscais para NFS-e (PJ)</strong>
</div>

<div class="card-body">

<div class="row">

<div class="col-md-4"><div class="form-group"><label>CNPJ fiscal</label><input type="text" name="cnpj_fiscal" class="form-control" maxlength="18"></div></div>
<div class="col-md-8"><div class="form-group"><label>Razão social fiscal</label><input type="text" name="razao_social_fiscal" class="form-control" maxlength="150"></div></div>
<div class="col-md-3"><div class="form-group"><label>CEP fiscal</label><input type="text" name="cep_fiscal" class="form-control" maxlength="9"></div></div>
<div class="col-md-7"><div class="form-group"><label>Logradouro fiscal</label><input type="text" name="logradouro_fiscal" class="form-control" maxlength="150"></div></div>
<div class="col-md-2"><div class="form-group"><label>Número</label><input type="text" name="numero_fiscal" class="form-control" maxlength="20"></div></div>
<div class="col-md-4"><div class="form-group"><label>Complemento</label><input type="text" name="complemento_fiscal" class="form-control" maxlength="100"></div></div>
<div class="col-md-4"><div class="form-group"><label>Bairro</label><input type="text" name="bairro_fiscal" class="form-control" maxlength="100"></div></div>
<div class="col-md-3"><div class="form-group"><label>Município</label><input type="text" name="municipio_fiscal" class="form-control" maxlength="100"></div></div>
<div class="col-md-1"><div class="form-group"><label>UF</label><input type="text" name="uf_fiscal" class="form-control" maxlength="2"></div></div>
<div class="col-md-3"><div class="form-group"><label>Código IBGE</label><input type="text" name="codigo_ibge_fiscal" class="form-control" maxlength="7"></div></div>
<div class="col-md-3"><div class="form-group"><label>Telefone fiscal</label><input type="text" name="telefone_fiscal" class="form-control" maxlength="20"></div></div>
<div class="col-md-6"><div class="form-group"><label>E-mail fiscal</label><input type="email" name="email_fiscal" class="form-control" maxlength="150"></div></div>

</div>

<small class="text-muted">Clientes PF ou PJ com dados fiscais incompletos continuam usando o sistema, mas não ficam aptos para emissão fiscal automática.</small>

</div>

</div>

<div class="row">

<!-- Senha -->

<div class="col-md-6">

<div class="form-group">

<label>Senha</label>

<div class="input-group">

<input
type="text"
name="senha"
id="senha"
class="form-control"
data-password-strength
>

<div class="input-group-append">

<button
type="button"
id="gerarSenha"
class="btn btn-info"
>

Gerar

</button>

</div>

</div>

</div>

</div>

</div>





<div class="form-group">

<label>Como conheceu o Disparador.net</label>

<input
type="text"
name="origem_cadastro_visualizacao"
class="form-control"
value="Não informado"
readonly
>

</div>

<div class="form-group">

<label>Observações</label>

<textarea
name="observacoes"
class="form-control"
rows="4"
></textarea>

</div>

</div>





<div class="modal-footer">

<button
type="submit"
id="btnSalvarCliente"
class="btn btn-success"
>

Salvar

</button>

</div>

</form>

</div>

</div>

</div>



<script>
const formCliente = document.getElementById('formCliente');
const btnSalvarCliente = document.getElementById('btnSalvarCliente');

if(formCliente && btnSalvarCliente){

    formCliente.addEventListener('submit', function(){

        if(
            formCliente.checkValidity &&
            !formCliente.checkValidity()
        ){
            return true;
        }

        btnSalvarCliente.disabled = true;
        btnSalvarCliente.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';

        return true;

    });

}
</script>
