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
type="button"
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
class="table table-bordered table-striped table-hover datatable"
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
onclick="abrirPreviewTemplate(this)"

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

<a
href="<?= BASE_URL; ?>/index.php?url=template/inativar&id=<?= $template['TMP_ID']; ?>"
class="btn btn-danger btn-sm"
onclick="return confirm('Deseja remover este template da listagem? Ele não será excluído da Meta.')"
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
aria-label="Close"
>
    <span aria-hidden="true">&times;</span>
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

<hr>

<h5>Variáveis</h5>

<div id="templateVariaveis"></div>

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
id="formNovoTemplate"
>

<div class="modal-header">

<h4 class="modal-title">

Novo Template

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
id="nome_template"
class="form-control"
required
oninput="formatarNomeTemplate(this)"
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





<div class="row">

    <div class="col-md-4">

        <div class="form-group">

            <label>Tipo do Header</label>

            <select
            name="header_tipo"
            id="header_tipo"
            class="form-control"
            onchange="alterarTipoHeader(this.value)"
            >
                <option value="">Sem Header</option>
                <option value="TEXT">Texto</option>
                <option value="IMAGE" disabled>Imagem em breve</option>
                <option value="VIDEO" disabled>Vídeo em breve</option>
                <option value="DOCUMENT" disabled>Documento em breve</option>   

            </select>

        </div>

    </div>

    <div
    class="col-md-8"
    id="areaHeaderTexto"
    style="display:none"
    >

        <div class="form-group">

            <label>Texto do Header</label>

            <input
            type="text"
            name="header"
            class="form-control"
            maxlength="60"
            >

        </div>

    </div>

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

<div
id="areaExemplosVariaveis"
style="display:none"
>

    <hr>

    <h5>
        Exemplos das Variáveis
    </h5>

    <div id="camposExemplosVariaveis"></div>

</div>

<hr>

<h5>
Botões
</h5>

<div class="form-group">

    <button
    type="button"
    class="btn btn-primary btn-sm"
    id="btnAdicionarBotao"
    >
        <i class="fas fa-plus"></i>
        Adicionar Botão
    </button>

</div>

<div id="areaBotoes"></div>


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

    if (
        $('#tabelaTemplates').length &&
        !$.fn.DataTable.isDataTable('#tabelaTemplates')
    ) {

        $('#tabelaTemplates').DataTable({
            language: {
                url:
                '//cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json'
            },
            order: [[0, 'asc']]
        });

    }

});

let contadorBotoes = 0;

$('#btnAdicionarBotao').click(function(){

    contadorBotoes++;

    let html = `

    <div
    class="card mb-2"
    id="botao_${contadorBotoes}"
    >

        <div class="card-body">

            <div class="row">

                <div class="col-md-4">

                    <select
                    name="botoes[${contadorBotoes}][tipo]"
                    class="form-control tipoBotao"
                    >

                        <option value="QUICK_REPLY">
                            Resposta Rápida
                        </option>

                        <option value="URL">
                            URL
                        </option>

                        <option value="PHONE_NUMBER">
                            Telefone
                        </option>

                    </select>

                </div>

                <div class="col-md-4">

                    <input
                    type="text"
                    name="botoes[${contadorBotoes}][texto]"
                    class="form-control"
                    placeholder="Texto do botão"
                    >

                </div>

                <div class="col-md-3">

                    <input
                    type="text"
                    name="botoes[${contadorBotoes}][valor]"
                    class="form-control"
                    placeholder="URL ou telefone"
                    >

                </div>

                <div class="col-md-1">

                    <button
                    type="button"
                    class="btn btn-danger removerBotao"
                    data-id="${contadorBotoes}"
                    >
                        X
                    </button>

                </div>

            </div>

        </div>

    </div>

    `;

    $('#areaBotoes').append(html);

});

$(document).on('submit', '#formNovoTemplate', function(e){

    let headerTipo = $('#header_tipo').val();

    if(headerTipo == 'TEXT'){

        let headerTexto = $('[name=header]').val();

        if(headerTexto.trim() == ''){

            alert('Informe o texto do Header.');

            e.preventDefault();

            return false;

        }

    }

    let totalBotoes = 0;
    let totalUrl = 0;
    let totalTelefone = 0;
    let totalQuick = 0;

    $('#areaBotoes .card').each(function(){

        totalBotoes++;

        let tipo =
            $(this).find('[name*="[tipo]"]').val();

        let texto =
            $(this).find('[name*="[texto]"]').val();

        let valor =
            $(this).find('[name*="[valor]"]').val();

        if(texto.trim() == ''){

            alert('Informe o texto de todos os botões.');

            e.preventDefault();

            return false;

        }

        if(tipo == 'URL'){

            totalUrl++;

            if(valor.trim() == ''){

                alert('Informe a URL do botão.');

                e.preventDefault();

                return false;

            }

        }

        if(tipo == 'PHONE_NUMBER'){

            totalTelefone++;

            if(valor.trim() == ''){

                alert('Informe o telefone do botão.');

                e.preventDefault();

                return false;

            }

        }

        if(tipo == 'QUICK_REPLY'){

            totalQuick++;

        }

    });

    if(totalBotoes > 10){

        alert('A Meta permite no máximo 10 botões.');

        e.preventDefault();

        return false;

    }

    if(totalUrl > 2){

        alert('A Meta permite no máximo 2 botões de URL.');

        e.preventDefault();

        return false;

    }

    if(totalTelefone > 1){

        alert('A Meta permite no máximo 1 botão de telefone.');

        e.preventDefault();

        return false;

    }

});

$(document).on(
    'click',
    '.removerBotao',
    function(){

        $('#botao_' + $(this).data('id')).remove();

    }
);

$(document).on('input', '[name=body]', function(){

    let texto = $(this).val();

    let matches = texto.match(/{{(.*?)}}/g);

    let variaveis = [];

    if(matches){

        matches.forEach(function(v){

            v = v
                .replace('{{', '')
                .replace('}}', '')
                .trim();

            if(!variaveis.includes(v)){
                variaveis.push(v);
            }

        });

    }

    let html = '';

    if(variaveis.length > 0){

        variaveis.forEach(function(v){

            html += `
                <div class="form-group">

                    <label>
                        Exemplo para {{${v}}}
                    </label>

                    <input
                    type="text"
                    name="exemplos[${v}]"
                    class="form-control"
                    placeholder="Exemplo de valor para aprovação"
                    required
                    >

                </div>
            `;

        });

        $('#camposExemplosVariaveis').html(html);
        $('#areaExemplosVariaveis').show();

    }else{

        $('#camposExemplosVariaveis').html('');
        $('#areaExemplosVariaveis').hide();

    }

});

function abrirPreviewTemplate(botao)
{
    botao = $(botao);

    $('#tmpNome').html(botao.data('nome'));
    $('#tmpStatus').html(botao.data('status'));
    $('#tmpIdioma').html(botao.data('idioma'));
    $('#tmpCategoria').html(botao.data('categoria'));

    let componentes = [];

    try{
        componentes = JSON.parse(
            atob(
                botao.attr('data-componentes')
            )
        );
    }catch(e){
        componentes = [];
    }

    let html = '';

    componentes.forEach(function(comp){

        if(comp.type == 'HEADER' && comp.format == 'TEXT'){
            html += '<div class="alert alert-secondary"><strong>' + comp.text + '</strong></div>';
        }

        if(comp.type == 'BODY' && comp.text){
            html += '<div class="border rounded p-2 mb-2">' + comp.text.replace(/\n/g, '<br>') + '</div>';
        }

        if(comp.type == 'FOOTER' && comp.text){
            html += '<small class="text-muted">' + comp.text + '</small>';
        }

        if(comp.type == 'BUTTONS' && comp.buttons){

            html += '<div class="mt-3">';

            comp.buttons.forEach(function(btn){
                html += '<button type="button" class="btn btn-outline-primary btn-block btn-sm mb-1" disabled>' + btn.text + '</button>';
            });

            html += '</div>';
        }

    });

    $('#templatePreview').html(html);

    $('#modalTemplate').modal('show');
}

function alterarTipoHeader(tipo)
{
    if(tipo === 'TEXT'){

        $('#areaHeaderTexto').show();

    }else{

        $('#areaHeaderTexto').hide();

        $('[name=header]').val('');

    }
}

function formatarNomeTemplate(campo)
{
    let valor = campo.value;

    valor = valor
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/ç/g, 'c')
        .replace(/\s+/g, '_')
        .replace(/[^a-z0-9_]/g, '')
        .replace(/_+/g, '_')
        .replace(/^_+/, '');

    campo.value = valor;
}

</script>