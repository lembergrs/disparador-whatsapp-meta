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

</div>

<div class="card-body">

<table
id="tabelaClientes"
class="table table-bordered table-striped"
>

<thead>

<tr>

<th>ID</th>
<th>Cliente</th>
<th>CPF/CNPJ</th>
<th>Email</th>
<th>Telefone</th>
<th>Status</th>
<th>Ações</th>

</tr>

</thead>

<tbody>

<?php foreach($clientes as $cliente){ ?>

<tr>

<td>
<?= $cliente['CLI_ID']; ?>
</td>

<td>

<?= $cliente['CLI_Nome']; ?>

<?php if(!empty($cliente['CLI_RazaoSocial'])){ ?>

<br>

<small class="text-muted">

<?= $cliente['CLI_RazaoSocial']; ?>

</small>

<?php } ?>

</td>

<td>
<?= $cliente['CLI_CPF_CNPJ']; ?>
</td>

<td>
<?= $cliente['CLI_Email']; ?>
</td>

<td>
<?= $cliente['CLI_Telefone']; ?>
</td>

<td>

<?php if(
    $cliente['CLI_StatusPagamento']
    == 'pago'
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

<button
type="button"
class="btn btn-info btn-sm btnEditarCliente"

data-id="<?= $cliente['CLI_ID']; ?>"

data-nome="<?= htmlspecialchars($cliente['CLI_Nome']); ?>"

data-razao="<?= htmlspecialchars($cliente['CLI_RazaoSocial']); ?>"

data-email="<?= htmlspecialchars($cliente['CLI_Email']); ?>"

data-telefone="<?= $cliente['CLI_Telefone']; ?>"

data-documento="<?= $cliente['CLI_CPF_CNPJ']; ?>"

data-tipo="<?= $cliente['CLI_TipoPessoa']; ?>"

data-mensalidade="<?= number_format($cliente['CLI_ValorMensalidade'],2,',','.'); ?>"

data-vencimento="<?= $cliente['CLI_Vencimento']; ?>"

data-status="<?= $cliente['CLI_StatusPagamento']; ?>"

data-observacoes="<?= htmlspecialchars($cliente['CLI_Observacoes']); ?>"
>

<i class="fas fa-edit"></i>

</button>

</a>

<a
href="<?= BASE_URL; ?>/index.php?url=cliente/inativar&id=<?= $cliente['CLI_ID']; ?>"
class="btn btn-danger btn-sm"
onclick="return confirm('Deseja inativar?')"
>

<i class="fas fa-trash"></i>

</a>

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
>

<span>&times;</span>

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
class="btn btn-success"
>

Salvar

</button>

</div>

</form>

</div>

</div>

</div>

