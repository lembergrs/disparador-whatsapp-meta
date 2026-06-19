<?php

if(!function_exists('categoriaTemplatePtBr')){

    function categoriaTemplatePtBr($categoria)
    {
        $categorias = [
            'MARKETING' => 'Marketing',
            'UTILITY' => 'Utilidade',
            'AUTHENTICATION' => 'Autenticação'
        ];

        return $categorias[$categoria] ?? $categoria;
    }

}

?>

<div class="card">

<div class="card-header">

<form
method="POST"
action="<?= BASE_URL; ?>/index.php?url=template/sincronizar"
class="form-inline"
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
<?= categoriaTemplatePtBr($template['TMP_Categoria']); ?>
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

data-categoria="<?= htmlspecialchars(categoriaTemplatePtBr($template['TMP_Categoria']), ENT_QUOTES); ?>"

data-componentes="<?= htmlspecialchars(
    base64_encode($template['TMP_Componentes']),
    ENT_QUOTES
); ?>"
>

<i class="fas fa-eye"></i>

</button>

<a
href="#" data-post-url="<?= BASE_URL; ?>/index.php?url=template/inativar" data-field-id="<?= (int) $template['TMP_ID']; ?>"
class="btn btn-danger btn-sm"
data-confirm="Deseja remover este template da listagem? Ele não será excluído da Meta."
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

Você pode usar variáveis como {{nome}}, {{valor}} ou {{erro}}.
O sistema converterá automaticamente para o padrão da Meta: {{1}}, {{2}}, {{3}}.

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

<div class="card card-outline card-secondary mt-3">
    <div class="card-header py-2">
        <h3 class="card-title">Preview dos botões</h3>
    </div>
    <div class="card-body py-2" id="previewBotoesTemplate">
        <small class="text-muted">Nenhum botão adicionado.</small>
    </div>
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

    if (
        $('#tabelaTemplates').length &&
        $.fn.DataTable &&
        !$.fn.DataTable.isDataTable('#tabelaTemplates')
    ) {

        $('#tabelaTemplates').DataTable({
            language: {
                url:
                'https://cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json'
            },
            order: [[0, 'asc']]
        });

    }

});

let contadorBotoes = 0;

function totalBotoesTemplatePorTipo(tipo)
{
    let total = 0;

    $('#areaBotoes .card-botao-template').each(function(){
        if(!tipo || $(this).find('.tipoBotao').val() == tipo){
            total++;
        }
    });

    return total;
}

function atualizarCampoValorBotao(card)
{
    let tipo = card.find('.tipoBotao').val();
    let campoValor = card.find('.valorBotao');
    let ajudaValor = card.find('.ajudaValorBotao');

    if(tipo == 'QUICK_REPLY'){
        campoValor
            .val('')
            .prop('required', false)
            .prop('disabled', true)
            .attr('placeholder', 'Não se aplica');

        ajudaValor.text('Resposta rápida não usa URL ou telefone.');
        return;
    }

    campoValor
        .prop('disabled', false)
        .prop('required', true);

    if(tipo == 'URL'){
        campoValor.attr('placeholder', 'https://exemplo.com');
        ajudaValor.text('Informe uma URL completa iniciando com http:// ou https://.');
        return;
    }

    campoValor.attr('placeholder', '+5541999999999');
    ajudaValor.text('Informe o telefone em formato internacional, com código do país.');
}

function atualizarPreviewBotoesTemplate()
{
    let html = '';

    $('#areaBotoes .card-botao-template').each(function(){
        let tipo = $(this).find('.tipoBotao').val();
        let texto = $(this).find('.textoBotao').val() || 'Botão sem texto';
        let icone = 'fa-reply';

        if(tipo == 'URL'){
            icone = 'fa-link';
        }

        if(tipo == 'PHONE_NUMBER'){
            icone = 'fa-phone';
        }

        html += `
            <button type="button" class="btn btn-outline-primary btn-block btn-sm mb-1" disabled>
                <i class="fas ${icone}"></i>
                ${$('<div>').text(texto).html()}
            </button>
        `;
    });

    if(html == ''){
        html = '<small class="text-muted">Nenhum botão adicionado.</small>';
    }

    $('#previewBotoesTemplate').html(html);
}

$(document).on('click', '#btnAdicionarBotao', function(e){

    e.preventDefault();

    if(totalBotoesTemplatePorTipo() >= 10){
        alert('A Meta permite no máximo 10 botões.');
        return;
    }

    contadorBotoes++;

    let html = `

    <div
    class="card mb-2 card-botao-template"
    id="botao_${contadorBotoes}"
    data-id="${contadorBotoes}"
    >

        <div class="card-body">

            <div class="row">

                <div class="col-md-3">

                    <label>Tipo</label>
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

                    <label>Texto</label>
                    <input
                    type="text"
                    name="botoes[${contadorBotoes}][texto]"
                    class="form-control textoBotao"
                    placeholder="Texto do botão"
                    maxlength="25"
                    required
                    >

                </div>

                <div class="col-md-4">

                    <label>URL ou telefone</label>
                    <input
                    type="text"
                    name="botoes[${contadorBotoes}][valor]"
                    class="form-control valorBotao"
                    placeholder="URL ou telefone"
                    disabled
                    >
                    <small class="form-text text-muted ajudaValorBotao">
                        Resposta rápida não usa URL ou telefone.
                    </small>

                </div>

                <div class="col-md-1 d-flex align-items-end">

                    <button
                    type="button"
                    class="btn btn-danger removerBotao"
                    data-id="${contadorBotoes}"
                    title="Remover botão"
                    >
                        X
                    </button>

                </div>

            </div>

        </div>

    </div>

    `;

    $('#areaBotoes').append(html);
    atualizarCampoValorBotao($('#botao_' + contadorBotoes));
    atualizarPreviewBotoesTemplate();

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

    let textosValidar = [
        $('[name=header]').val() || '',
        $('[name=body]').val() || ''
    ];

    $('#areaBotoes .card-botao-template').each(function(){
        if($(this).find('.tipoBotao').val() == 'URL'){
            textosValidar.push($(this).find('.valorBotao').val() || '');
        }
    });

    for(let i = 0; i < textosValidar.length; i++){
        if(!validarVariaveisTemplateTexto(textosValidar[i])){
            alert('Existe uma variável inválida no template. Use o formato {{nome}} ou {{1}}.');
            e.preventDefault();
            return false;
        }
    }

    let totalBotoes = 0;
    let totalUrl = 0;
    let totalTelefone = 0;
    let invalido = false;

    $('#areaBotoes .card-botao-template').each(function(){

        totalBotoes++;

        let tipo = $(this).find('[name*="[tipo]"]').val();
        let texto = $(this).find('[name*="[texto]"]').val();
        let valor = $(this).find('[name*="[valor]"]').val();

        if(texto.trim() == ''){
            alert('Informe o texto de todos os botões.');
            invalido = true;
            return false;
        }

        if(tipo == 'URL'){
            totalUrl++;

            if(valor.trim() == ''){
                alert('Informe a URL do botão.');
                invalido = true;
                return false;
            }

            if(!/^https?:\/\//i.test(valor.trim())){
                alert('Informe uma URL válida iniciando com http:// ou https://.');
                invalido = true;
                return false;
            }
        }

        if(tipo == 'PHONE_NUMBER'){
            totalTelefone++;

            if(valor.trim() == ''){
                alert('Informe o telefone do botão.');
                invalido = true;
                return false;
            }
        }
    });

    if(invalido){
        e.preventDefault();
        return false;
    }

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
        atualizarPreviewBotoesTemplate();

    }
);

$(document).on('change', '.tipoBotao', function(){
    let card = $(this).closest('.card-botao-template');
    let tipo = $(this).val();

    if(tipo == 'URL' && totalBotoesTemplatePorTipo('URL') > 2){
        alert('A Meta permite no máximo 2 botões de URL.');
        $(this).val('QUICK_REPLY');
    }

    if(tipo == 'PHONE_NUMBER' && totalBotoesTemplatePorTipo('PHONE_NUMBER') > 1){
        alert('A Meta permite no máximo 1 botão de telefone.');
        $(this).val('QUICK_REPLY');
    }

    atualizarCampoValorBotao(card);
    atualizarPreviewBotoesTemplate();
});

$(document).on('input', '.textoBotao, .valorBotao', function(){
    atualizarPreviewBotoesTemplate();
});

$('#modalNovoTemplate').on('hidden.bs.modal', function(){
    $('#areaBotoes').html('');
    contadorBotoes = 0;
    atualizarPreviewBotoesTemplate();
});

function validarVariaveisTemplateTexto(texto)
{
    texto = String(texto || '');

    let matches = texto.match(/{{(.*?)}}/g) || [];

    for(let i = 0; i < matches.length; i++){
        let conteudo = matches[i]
            .replace('{{', '')
            .replace('}}', '');

        if(conteudo !== conteudo.trim() || !/^[A-Za-z0-9_]+$/.test(conteudo)){
            return false;
        }
    }

    let textoSemVariaveisValidas = texto.replace(/{{[A-Za-z0-9_]+}}/g, '');

    return !/[{}]/.test(textoSemVariaveisValidas);
}

function obterVariaveisNovoTemplate()
{
    let textos = [
        $('[name=header]').val() || '',
        $('[name=body]').val() || ''
    ];

    $('#areaBotoes .card-botao-template').each(function(){
        if($(this).find('.tipoBotao').val() == 'URL'){
            textos.push($(this).find('.valorBotao').val() || '');
        }
    });

    let variaveis = [];

    textos.forEach(function(texto){
        let matches = texto.match(/{{\s*([A-Za-z0-9_]+)\s*}}/g);

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
    });

    return variaveis;
}

function atualizarExemplosVariaveisTemplate()
{
    let variaveis = obterVariaveisNovoTemplate();
    let html = '';

    if(variaveis.length > 0){

        variaveis.forEach(function(v, index){

            html += `
                <div class="form-group">

                    <label>
                        Exemplo para {{${v}}}
                        <small class="text-muted">${v} → {{${index + 1}}}</small>
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
}

$(document).on('input change', '[name=body], [name=header], .valorBotao, .tipoBotao', function(){
    atualizarExemplosVariaveisTemplate();
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