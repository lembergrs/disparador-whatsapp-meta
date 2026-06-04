<?php

function formatarNumeroBR($numero)
{
    $numero = preg_replace('/\D/', '', $numero);

    if(substr($numero, 0, 2) == '55'){
        $numero = substr($numero, 2);
    }

    if(strlen($numero) == 11){

        return '(' . substr($numero, 0, 2) . ') '
            . substr($numero, 2, 5)
            . '-'
            . substr($numero, 7);

    }

    if(strlen($numero) == 10){

        return '(' . substr($numero, 0, 2) . ') '
            . substr($numero, 2, 4)
            . '-'
            . substr($numero, 6);

    }

    return $numero;
}

?>
<div class="row">

<div class="col-md-4">

<div
class="card"
style="height:75vh;"
>

<div class="card-header bg-light">

<strong>
<i class="fas fa-comments"></i>
Conversas
</strong>

</div>

<div
class="list-group list-group-flush"
style="overflow-y:auto;"
>

<?php foreach($conversas as $conversa){ ?>

<?php

$nome =
    $conversa['CVS_Nome'] ?: formatarNumeroBR($conversa['CVS_Numero'])
    ?: formatarNumeroBR($conversa['CVS_Numero']);

$numeroFormatado =
    formatarNumeroBR($conversa['CVS_Numero']);

?>

<a
href="<?= BASE_URL; ?>/index.php?url=conversa&id=<?= $conversa['CVS_ID']; ?>"
class="list-group-item list-group-item-action <?= isset($conversaSelecionada['CVS_ID']) && $conversaSelecionada['CVS_ID'] == $conversa['CVS_ID'] ? 'active' : ''; ?>"
>

<div class="d-flex justify-content-between">

<strong>
<?= $nome; ?>
</strong>

<?php if($conversa['CVS_NaoLida'] == 'S'){ ?>

<span class="badge badge-success">
Novo
</span>

<?php } ?>

</div>

<small class="<?= isset($conversaSelecionada['CVS_ID']) && $conversaSelecionada['CVS_ID'] == $conversa['CVS_ID'] ? 'text-white' : 'text-muted'; ?>">
<?= $numeroFormatado; ?>
</small>

<br>

<small class="<?= isset($conversaSelecionada['CVS_ID']) && $conversaSelecionada['CVS_ID'] == $conversa['CVS_ID'] ? 'text-white' : 'text-muted'; ?>">
<?= $conversa['CVS_UltimaMensagem']; ?>
</small>

</a>

<?php } ?>

</div>

</div>

</div>

<div class="col-md-8">

<div
class="card"
style="height:75vh;"
>

<?php if($conversaSelecionada){ ?>

<?php

$nomeSelecionado =
    $conversaSelecionada['CVS_Nome']
    ?: formatarNumeroBR($conversaSelecionada['CVS_Numero']);

?>

<div class="card-header bg-light">

<strong>
<?= $nomeSelecionado; ?>
</strong>

<br>

<small class="text-muted">
<?= formatarNumeroBR($conversaSelecionada['CVS_Numero']); ?>
</small>

</div>

<div
class="card-body conversa-bg"
style="
    overflow-y:auto;
    background-color:#efeae2;
    background-image:
        radial-gradient(circle at 25px 25px, rgba(0,0,0,0.04) 2px, transparent 0),
        radial-gradient(circle at 75px 75px, rgba(0,0,0,0.03) 2px, transparent 0);
    background-size:100px 100px;
"
>

<?php foreach($mensagens as $msg){ ?>

<?php if($msg['MSG_Direcao'] == 'enviada'){ ?>

<div class="d-flex justify-content-end mb-2">

    <div
    class="p-2 rounded shadow-sm"
    style="
        background:#d9fdd3;
        max-width:70%;
        border-radius:8px;
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
    class="p-2 rounded shadow-sm"
    style="
        background:#ffffff;
        max-width:70%;
        border-radius:8px;
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

<div class="card-footer bg-light">

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