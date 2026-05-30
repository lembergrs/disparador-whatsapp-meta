<div class="card">

<div class="card-header">

<form
method="GET"
action="<?= BASE_URL; ?>/index.php"
class="form-inline"
>

<input
type="hidden"
name="url"
value="template/sincronizar"
>

<select
name="meta"
class="form-control mr-2"
required
>

<option value="">
Selecione a Conta Meta
</option>

<?php foreach($contas as $conta){ ?>

<option value="<?= $conta['MTA_ID']; ?>">

<?= $conta['MTA_Nome']; ?>

</option>

<?php } ?>

</select>

<button
type="submit"
class="btn btn-success"
>

<i class="fas fa-sync"></i>

Sincronizar Templates

</button>

<button
class="btn btn-primary ml-2"
data-toggle="modal"
data-target="#modalNovoTemplate"
>

<i class="fas fa-plus"></i>

Novo Template

</button>

</form>

</div>

<div class="card-body">

<table
id="tabelaTemplates"
class="table table-bordered table-striped"
>

<thead>

<tr>

<th>ID</th>
<th>Conta</th>
<th>Template</th>
<th>Categoria</th>
<th>Idioma</th>
<th>Status</th>
<th>Ações</th>

</tr>

</thead>

<tbody>

<?php foreach($templates as $template){ ?>

<tr>

<td>
<?= $template['TMP_ID']; ?>
</td>

<td>
<?= $template['MTA_Nome']; ?>
</td>

<td>
<?= $template['TMP_Nome']; ?>
</td>

<td>
<?= $template['TMP_Categoria']; ?>
</td>

<td>
<?= $template['TMP_Idioma']; ?>
</td>

<td>

<?php

$status =
$template['TMP_Status'];

if($status == 'APPROVED'){

    echo '
    <span class="badge badge-success">
    APROVADO
    </span>
    ';

}else if($status == 'PENDING'){

    echo '
    <span class="badge badge-warning">
    PENDENTE
    </span>
    ';

}else{

    echo '
    <span class="badge badge-danger">
    '.$status.'
    </span>
    ';

}

?>

</td>

<td>

<button
type="button"
class="btn btn-info btn-sm btnVisualizarTemplate"

data-nome="<?= htmlspecialchars($template['TMP_Nome'], ENT_QUOTES); ?>"

data-status="<?= htmlspecialchars($template['TMP_Status'], ENT_QUOTES); ?>"

data-idioma="<?= htmlspecialchars($template['TMP_Idioma'], ENT_QUOTES); ?>"

data-categoria="<?= htmlspecialchars($template['TMP_Categoria'], ENT_QUOTES); ?>"

data-componentes="<?= htmlspecialchars(
    base64_encode($template['TMP_Componentes']),
    ENT_QUOTES
); ?>"
>

<i class="fas fa-eye"></i>

</button>

</td>

</tr>

<?php } ?>

</tbody>

</table>

</div>

</div>





<div
class="modal fade"
id="modalTemplate"
tabindex="-1"
>

<div class="modal-dialog modal-lg">

<div class="modal-content">

<div class="modal-header">

<h4 class="modal-title">

Visualizar Template

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

<h5 id="templateNome"></h5>

<hr>

<div id="templatePreview"></div>

<hr>

<h5>
Variáveis
</h5>

<div id="templateVariaveis"></div>
</div>

</div>

</div>

</div>

<div
class="modal fade"
id="modalTemplate"
tabindex="-1"
>

<div class="modal-dialog modal-lg">

<div class="modal-content">

<div class="modal-header">

<h4 class="modal-title">
Visualizar Template
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

<div class="mb-3">

<strong>Nome:</strong>
<span id="tmpNome"></span>

</div>

<div class="mb-3">

<strong>Status:</strong>
<span id="tmpStatus"></span>

</div>

<div class="mb-3">

<strong>Idioma:</strong>
<span id="tmpIdioma"></span>

</div>

<div class="mb-3">

<strong>Categoria:</strong>
<span id="tmpCategoria"></span>

</div>

<hr>

<div id="templatePreview"></div>

</div>

</div>

</div>

</div>

<div
class="modal fade"
id="modalNovoTemplate"
>

<div class="modal-dialog modal-lg">

<div class="modal-content">

<form
method="POST"
action="<?= BASE_URL; ?>/index.php?url=template/criar"
>

<div class="modal-header">

<h4 class="modal-title">

Novo Template

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

<div class="col-md-6">

<div class="form-group">

<label>Conta Meta</label>

<select
name="meta"
class="form-control"
required
>

<?php foreach($contas as $conta){ ?>

<option value="<?= $conta['MTA_ID']; ?>">

<?= $conta['MTA_Nome']; ?>

</option>

<?php } ?>

</select>

</div>

</div>

<div class="col-md-6">

<div class="form-group">

<label>Nome do Template</label>

<input
type="text"
name="nome"
class="form-control"
required
>

<small class="text-muted">

Use apenas:
letras minúsculas,
números e underline.

</small>

</div>

</div>

</div>





<div class="row">

<div class="col-md-6">

<div class="form-group">

<label>Categoria</label>

<select
name="categoria"
class="form-control"
required
>

<option value="MARKETING">
Marketing
</option>

<option value="UTILITY">
Utilidade
</option>

<option value="AUTHENTICATION">
Autenticação
</option>

</select>

</div>

</div>

<div class="col-md-6">

<div class="form-group">

<label>Idioma</label>

<select
name="idioma"
class="form-control"
required
>

<option value="pt_BR">
Português Brasil
</option>

<option value="en_US">
Inglês
</option>

</select>

</div>

</div>

</div>





<div class="form-group">

<label>HEADER</label>

<input
type="text"
name="header"
class="form-control"
maxlength="60"
>

<small class="text-muted">

Opcional.
Título da mensagem.

</small>

</div>





<div class="form-group">

<label>BODY</label>

<textarea
name="body"
class="form-control"
rows="6"
required
></textarea>

<small class="text-muted">

Use variáveis:
{{1}}, {{2}}, {{3}}

</small>

</div>





<div class="form-group">

<label>FOOTER</label>

<input
type="text"
name="footer"
class="form-control"
maxlength="60"
>

<small class="text-muted">

Opcional.
Rodapé da mensagem.

</small>

</div>

</div>

<div class="modal-footer">

<button
type="submit"
class="btn btn-success"
>

Criar Template

</button>

</div>

</form>

</div>

</div>

</div>


<script>

$(document).ready(function(){

    $('#tabelaTemplates').DataTable({

        language: {

            url:
            '//cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json'

        }

    });

});





$(document).on(
    'click',
    '.btnVisualizarTemplate',
    function(){

        let nome =
            $(this).data('nome');

        let componentes =
            $(this).data('componentes');





        $('#templateNome').html(
            nome
        );





        let html = '';





        try{

            componentes =
                JSON.parse(componentes);





            componentes.forEach(function(comp){

                html += `
                    <div class="mb-3">
                `;





                html += `
                    <strong>
                        ${comp.type}
                    </strong>
                    <br>
                `;





                if(comp.text){

                    html += `
                        <div class="border rounded p-2">

                            ${comp.text}

                        </div>
                    `;

                }






                if(comp.buttons){

                    comp.buttons.forEach(function(btn){

                        html += `
                            <button
                            class="btn btn-primary btn-sm mr-1"
                            >

                                ${btn.text}

                            </button>
                        `;

                    });

                }






                html += `
                    </div>
                `;

            });

        }catch(e){

            html =
            '<div class="alert alert-danger">Erro ao carregar template.</div>';

        }






        $('#templatePreview').html(
            html
        );





        $('#modalTemplate').modal(
            'show'
        );

    }
);

</script>