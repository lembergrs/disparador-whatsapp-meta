<div class="card">

<div class="card-header">

<button
id="btnNovaMeta"
class="btn btn-success"
data-toggle="modal"
data-target="#modalMeta"
>

Nova Conta Meta

</button>

</div>

<div class="card-body">

<table class="table table-bordered table-striped table-hover datatable">

<thead>

<tr>

<th>ID</th>
<th>Cliente</th>
<th>Conta</th>
<th>Número</th>
<th>Status</th>
<th>Ações</th>

</tr>

</thead>

<tbody>

<?php foreach($contas as $conta){ ?>

<tr>

<td><?= $conta['MTA_ID']; ?></td>

<td><?= $conta['CLI_Nome']; ?></td>

<td><?= $conta['MTA_Nome']; ?></td>

<td><?= $conta['MTA_NumeroTelefone']; ?></td>

<td><?= $conta['MTA_Status']; ?></td>

<td>

<button
type="button"
class="btn btn-info btn-sm btnEditarMeta"

data-id="<?= $conta['MTA_ID']; ?>"

data-cliente="<?= $conta['CLI_ID']; ?>"

data-nome="<?= $conta['MTA_Nome']; ?>"

data-phone="<?= $conta['MTA_PhoneNumberId']; ?>"

data-waba="<?= $conta['MTA_WabaId']; ?>"

data-token="<?= htmlspecialchars($conta['MTA_Token']); ?>"

data-url="<?= $conta['MTA_UrlBase']; ?>"

data-numero="<?= $conta['MTA_NumeroTelefone']; ?>"
>

<i class="fas fa-edit"></i>

</button>

<a
href="<?= BASE_URL; ?>/index.php?url=metaConta/testar&id=<?= $conta['MTA_ID']; ?>"
class="btn btn-success btn-sm"
>

<i class="fas fa-plug"></i>

</a>

<a
href="<?= BASE_URL; ?>/index.php?url=metaConta/inativar&id=<?= $conta['MTA_ID']; ?>"
class="btn btn-danger btn-sm"
onclick="return confirm('Deseja inativar esta conta?')"
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
id="modalMeta"
>

<div class="modal-dialog modal-lg">

<div class="modal-content">

<form
method="POST"
id="formMeta"
action="<?= BASE_URL; ?>/index.php?url=metaConta/salvar"
>

<div class="modal-header">

<h4 class="modal-title">
Nova Conta Meta
</h4>

</div>

<div class="modal-body">

<div class="form-group">

<label>Cliente</label>
<input
type="hidden"
name="id"
id="meta_id"
>
<select
name="cliente"
class="form-control"
required
>

<?php foreach($clientes as $cliente){ ?>

<option value="<?= $cliente['CLI_ID']; ?>">

<?= $cliente['CLI_Nome']; ?>

</option>

<?php } ?>

</select>

</div>

<div class="form-group">

<label>Nome da Conta</label>

<input
type="text"
name="nome"
class="form-control"
required
>

</div>

<div class="form-group">

<label>Phone Number ID</label>

<input
type="text"
name="phone_number_id"
class="form-control"
required
>

</div>

<div class="form-group">

<label>WABA ID</label>

<input
type="text"
name="waba_id"
class="form-control"
required
>

</div>

<div class="form-group">

<label>Token</label>

<textarea
name="token"
class="form-control"
rows="4"
required
></textarea>

</div>

<div class="form-group">

<label>URL Base</label>

<input
type="text"
name="url_base"
class="form-control"
value="https://graph.facebook.com/v23.0/"
required
>

</div>

<div class="form-group">

<label>Número WhatsApp</label>

<input
type="text"
name="numero"
class="form-control"
>

</div>

</div>

<div class="modal-footer">

<button
class="btn btn-success"
type="submit"
>

Salvar

</button>

</div>

</form>

</div>

</div>

</div>