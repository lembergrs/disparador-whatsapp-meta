<div class="row">

<div class="col-md-4">

<div class="card">

<div class="card-header">
<strong>Conversas</strong>
</div>

<div class="list-group list-group-flush">

<?php foreach($conversas as $conversa){ ?>

<a
href="<?= BASE_URL; ?>/index.php?url=conversa&id=<?= $conversa['CVS_ID']; ?>"
class="list-group-item list-group-item-action"
>

<div class="d-flex justify-content-between">

<strong>
<?= $conversa['CVS_Nome'] ?: $conversa['CVS_Numero']; ?>
</strong>

<?php if($conversa['CVS_NaoLida'] == 'S'){ ?>

<span class="badge badge-success">
Novo
</span>

<?php } ?>

</div>

<small class="text-muted">
<?= $conversa['CVS_UltimaMensagem']; ?>
</small>

</a>

<?php } ?>

</div>

</div>

</div>

<div class="col-md-8">

<div class="card" style="height: 75vh;">

<?php if($conversaSelecionada){ ?>

<div class="card-header">

<strong>
<?= $conversaSelecionada['CVS_Nome'] ?: $conversaSelecionada['CVS_Numero']; ?>
</strong>

<br>

<small class="text-muted">
<?= $conversaSelecionada['CVS_Numero']; ?>
</small>

</div>

<div
class="card-body"
style="
    overflow-y:auto;
    background:#e5ddd5;
"
>

<?php foreach($mensagens as $msg){ ?>

<?php if($msg['MSG_Direcao'] == 'enviada'){ ?>

<div class="d-flex justify-content-end mb-2">

    <div
    class="p-2 rounded"
    style="
        background:#dcf8c6;
        max-width:70%;
    "
    >

        <?= nl2br($msg['MSG_Texto']); ?>

        <br>

        <small class="text-muted">
            <?= date('d/m/Y H:i', strtotime($msg['MSG_DataMensagem'])); ?>
        </small>

    </div>

</div>

<?php }else{ ?>

<div class="d-flex justify-content-start mb-2">

    <div
    class="p-2 rounded"
    style="
        background:#fff;
        max-width:70%;
    "
    >

        <?= nl2br($msg['MSG_Texto']); ?>

        <br>

        <small class="text-muted">
            <?= date('d/m/Y H:i', strtotime($msg['MSG_DataMensagem'])); ?>
        </small>

    </div>

</div>

<?php } ?>

<?php } ?>

</div>

<div class="card-footer">

<?php if($janelaAberta){ ?>

<form
method="POST"
action="<?= BASE_URL; ?>/index.php?url=conversa/enviar"
>

<input
type="hidden"
name="conversa_id"
value="<?= $conversaSelecionada['CVS_ID']; ?>"
>

<div class="input-group">

<input
type="text"
name="mensagem"
class="form-control"
placeholder="Digite uma mensagem..."
required
>

<div class="input-group-append">

<button
class="btn btn-success"
type="submit"
>

<i class="fas fa-paper-plane"></i>

</button>

</div>

</div>

</form>

<?php }else{ ?>

<div class="alert alert-warning mb-0">

    A janela de atendimento de 24 horas está fechada.
    Para falar com este contato novamente, envie um template aprovado.

</div>

<?php } ?>

</div>

<?php }else{ ?>

<div class="card-body text-center text-muted">

Selecione uma conversa para visualizar as mensagens.

</div>

<?php } ?>

</div>

</div>

</div>