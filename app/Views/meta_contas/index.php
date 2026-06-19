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

<?php if(empty($colunaWebhookVerifyTokenExiste)){ ?>

<div class="alert alert-warning">
A coluna <strong>MTA_WebhookVerifyToken</strong> não foi encontrada na tabela <strong>meta_contas</strong>.
Crie a coluna para salvar o Verify Token do webhook.
</div>

<?php } ?>

<?php if(empty($colunasAutoRespostaExistem)){ ?>

<div class="alert alert-warning">
As colunas de <strong>auto resposta</strong> não foram encontradas na tabela <strong>meta_contas</strong>.
Crie as colunas para salvar a configuração de auto resposta.
</div>

<?php } ?>

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

data-webhook-token="<?= htmlspecialchars($conta['MTA_WebhookVerifyToken'] ?? ''); ?>"

data-url="<?= $conta['MTA_UrlBase']; ?>"

data-numero="<?= $conta['MTA_NumeroTelefone']; ?>"

data-auto-resposta-ativa="<?= $conta['MTA_AutoRespostaAtiva'] ?? 'N'; ?>"

data-auto-resposta-texto="<?= htmlspecialchars($conta['MTA_AutoRespostaTexto'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"

data-auto-resposta-intervalo="<?= $conta['MTA_AutoRespostaIntervaloMinutos'] ?? 1440; ?>"
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

<label>Webhook Verify Token</label>

<div class="input-group">

<input
type="text"
name="webhook_verify_token"
id="webhook_verify_token"
class="form-control"
minlength="32"
maxlength="128"
required
>

<div class="input-group-append">

<button
type="button"
class="btn btn-outline-secondary"
id="btnGerarWebhookToken"
>
Gerar Token
</button>

</div>

</div>

<small class="form-text text-muted">
Use este token no campo Verify Token da configuração do Webhook da Meta. Se ficar vazio em uma conta nova, o sistema gera um token seguro automaticamente ao salvar.
</small>

</div>

<div class="alert alert-info">

<strong>Configuração do Webhook na Meta</strong><br>
Webhook URL:
<code id="metaWebhookUrl">https://disparador.net/public/webhook/meta.php</code>
<br>
Verify Token:
<code id="metaWebhookVerifyTokenPreview">Informe ou gere um token</code>

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


<hr>

<h5>Auto resposta</h5>

<div class="alert alert-info">
Essa mensagem será enviada automaticamente quando esse número receber uma mensagem. Ela não usa template e só funciona dentro da janela de atendimento de 24 horas da Meta.
</div>

<div class="form-group">

<label>Ativar auto resposta</label>

<select
name="auto_resposta_ativa"
class="form-control"
>
<option value="N">Não</option>
<option value="S">Sim</option>
</select>

</div>

<div class="form-group">

<label>Texto da auto resposta</label>

<textarea
name="auto_resposta_texto"
class="form-control"
rows="4"
></textarea>

</div>

<div class="form-group">

<label>Intervalo para repetir auto resposta, em minutos</label>

<input
type="number"
name="auto_resposta_intervalo_minutos"
class="form-control"
min="5"
value="1440"
>

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