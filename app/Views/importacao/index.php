<?php if(isset($_SESSION['sucesso'])){ ?>

<div class="alert alert-success">

<?= $_SESSION['sucesso']; ?>

</div>

<?php unset($_SESSION['sucesso']); ?>

<?php } ?>

<?php if(isset($_SESSION['erro'])){ ?>

<div class="alert alert-danger">

<?= $_SESSION['erro']; ?>

</div>

<?php unset($_SESSION['erro']); ?>

<?php } ?>

<div class="card">

<div class="card-header">

<form
action="<?= BASE_URL; ?>/index.php?url=importacao/importar"
method="POST"
enctype="multipart/form-data"
>

<div class="row">

<div class="col-md-6">

<div class="custom-file">

<input
type="file"
name="arquivo"
class="custom-file-input"
required
>

<label class="custom-file-label">
Escolher arquivo
</label>

</div>

</div>

<div class="col-md-3">

<button
type="submit"
class="btn btn-success"
>

<i class="fas fa-upload"></i>

Importar

</button>

</div>

</div>

</form>

</div>

<div class="card-body">

<table
id="tabelaContatos"
class="table table-bordered table-striped"
>

<thead>

<tr>

<th>ID</th>
<th>Nome</th>
<th>Telefone</th>
<th>Importação</th>

</tr>

</thead>

<tbody>

<?php foreach($contatos as $contato){ ?>

<tr>

<td>
<?= $contato['CON_ID']; ?>
</td>

<td>
<?= $contato['CON_Nome']; ?>
</td>

<td>
<?= $contato['CON_Telefone']; ?>
</td>

<td>

<?= date(
    'd/m/Y H:i',
    strtotime(
        $contato['CON_DataImportacao']
    )
); ?>

</td>

</tr>

<?php } ?>

</tbody>

</table>

</div>

</div>

<script>

$(document).ready(function(){

    $('#tabelaContatos').DataTable({
        language: {
            url:
            '//cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json'
        }
    });

    $('.custom-file-input').on(
        'change',
        function(){

            let fileName =
                $(this)
                .val()
                .split('\\')
                .pop();

            $(this)
                .next('.custom-file-label')
                .html(fileName);

        }
    );

});

</script>