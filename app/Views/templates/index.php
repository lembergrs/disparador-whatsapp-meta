<?php

use Core\Session;

$templateMetaErrorModal = Session::get('template_meta_error_modal');
Session::remove('template_meta_error_modal');

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

if(!function_exists('templateHeaderTipo')){
    function templateHeaderTipo($template)
    {
        if(!empty($template['TMP_HeaderTipo'])){
            return $template['TMP_HeaderTipo'];
        }

        $componentes = json_decode($template['TMP_Componentes'] ?? '[]', true);

        if(!is_array($componentes)){
            return '';
        }

        foreach($componentes as $componente){
            if(($componente['type'] ?? '') == 'HEADER'){
                return $componente['format'] ?? '';
            }
        }

        return '';
    }
}

if(!function_exists('templateHeaderMidiaUrlExemplo')){
    function templateHeaderMidiaUrlExemplo($template)
    {
        if(!empty($template['TMP_HeaderMidiaUrlExemplo'])){
            return $template['TMP_HeaderMidiaUrlExemplo'];
        }

        $componentes = json_decode($template['TMP_Componentes'] ?? '[]', true);

        if(!is_array($componentes)){
            return '';
        }

        foreach($componentes as $componente){
            if(($componente['type'] ?? '') == 'HEADER' && !empty($componente['media_url'])){
                return $componente['media_url'];
            }
        }

        return '';
    }
}

if(!function_exists('templateHeaderDocumentoNome')){
    function templateHeaderDocumentoNome($template)
    {
        if(!empty($template['TMP_HeaderDocumentoNome'])){
            return $template['TMP_HeaderDocumentoNome'];
        }

        $componentes = json_decode($template['TMP_Componentes'] ?? '[]', true);

        if(!is_array($componentes)){
            return '';
        }

        foreach($componentes as $componente){
            if(($componente['type'] ?? '') == 'HEADER' && !empty($componente['media_name'])){
                return $componente['media_name'];
            }
        }

        return '';
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
data-header-tipo="<?= htmlspecialchars(templateHeaderTipo($template), ENT_QUOTES); ?>"
data-header-midia-url="<?= htmlspecialchars(templateHeaderMidiaUrlExemplo($template), ENT_QUOTES); ?>"
data-header-documento-nome="<?= htmlspecialchars(templateHeaderDocumentoNome($template), ENT_QUOTES); ?>"
>

<i class="fas fa-eye"></i>

</button>

<button
 type="button"
 class="btn btn-warning btn-sm btnEditarTemplate"
 data-id="<?= (int) $template['TMP_ID']; ?>"
 data-nome="<?= htmlspecialchars($template['TMP_Nome'], ENT_QUOTES); ?>"
 data-header-tipo="<?= htmlspecialchars($template['TMP_HeaderTipo'] ?? '', ENT_QUOTES); ?>"
 data-documento="<?= htmlspecialchars($template['TMP_HeaderDocumentoNome'] ?? '', ENT_QUOTES); ?>"
>
    <i class="fas fa-edit"></i>
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


<?php if(!empty($templateMetaErrorModal) && is_array($templateMetaErrorModal)){ ?>
<div class="modal fade" id="modalErroTemplateMeta" tabindex="-1" role="dialog" aria-labelledby="modalErroTemplateMetaTitulo" aria-hidden="true">
<div class="modal-dialog modal-lg" role="document">
<div class="modal-content">
    <div class="modal-header bg-danger">
        <h4 class="modal-title" id="modalErroTemplateMetaTitulo"><?= htmlspecialchars($templateMetaErrorModal['titulo'] ?? 'Não foi possível criar o template', ENT_QUOTES, 'UTF-8'); ?></h4>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    </div>
    <div class="modal-body">
        <?php if(!empty($templateMetaErrorModal['destaque'])){ ?>
            <p><strong><?= htmlspecialchars($templateMetaErrorModal['destaque'], ENT_QUOTES, 'UTF-8'); ?></strong></p>
        <?php } ?>
        <p class="mb-0"><?= nl2br(htmlspecialchars($templateMetaErrorModal['mensagem'] ?? 'Não foi possível criar o template na Meta. Tente novamente.', ENT_QUOTES, 'UTF-8')); ?></p>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
    </div>
</div>
</div>
</div>
<?php } ?>

<div class="modal fade" id="modalEditarTemplate">
<div class="modal-dialog">
<div class="modal-content">
<form method="POST" action="<?= BASE_URL; ?>/index.php?url=template/editar" enctype="multipart/form-data">
    <?= \Core\Csrf::input(); ?>
    <input type="hidden" name="id" id="editarTemplateId">
    <div class="modal-header">
        <h4 class="modal-title">Editar Template</h4>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    </div>
    <div class="modal-body">
        <p><strong id="editarTemplateNome"></strong></p>
        <p>Tipo atual do header: <span id="editarTemplateHeader" class="badge badge-info"></span></p>
        <p id="editarTemplateMidiaAtual" class="text-muted"></p>
        <div class="alert alert-warning mb-0">Templates aprovados pela Meta podem exigir criação de um novo template para alteração. Para substituir mídia com segurança, crie um novo template.</div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
        <button type="submit" class="btn btn-warning">Entendi</button>
    </div>
</form>
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
enctype="multipart/form-data"
>
<?= \Core\Csrf::input(); ?>


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
                <option value="IMAGE">Imagem</option>
                <option value="VIDEO">Vídeo</option>
                <option value="DOCUMENT">Documento/PDF</option>   

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

<div id="areaHeaderMidia" class="form-group" style="display:none">
    <label>Arquivo do header</label>
    <div class="meta-media-drop border rounded p-3 text-center" data-input="header_media" role="button" tabindex="0" style="cursor:pointer;">
        <i class="fas fa-cloud-upload-alt fa-2x mb-2 text-muted"></i>
        <p class="mb-1">Clique ou arraste o arquivo aqui.</p>
        <small class="text-muted meta-media-help" id="headerMediaAjuda">Selecione um tipo de mídia.</small>
        <input type="file" name="header_media" id="header_media" class="d-none" accept=".jpg,.jpeg,.png,.webp,.mp4,.3gpp,.pdf">
    </div>
    <div class="mt-2" id="headerMediaNome"></div>
    <img src="" alt="Preview da imagem" id="headerMediaPreview" class="img-fluid rounded mt-2" style="display:none;max-height:180px;">
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


function escapeHtmlTemplatePreview(valor)
{
    return $('<div>').text(valor || '').html();
}

function traduzirStatusTemplateMeta(status)
{
    const mapa = {
        APPROVED: 'Aprovado',
        PENDING: 'Em análise',
        REJECTED: 'Rejeitado',
        PAUSED: 'Pausado',
        DISABLED: 'Desativado',
        IN_APPEAL: 'Em recurso',
        PENDING_DELETION: 'Exclusão pendente',
        DELETED: 'Excluído'
    };

    status = String(status || '');

    return mapa[status.toUpperCase()] || status;
}

function normalizarUrlPreviewTemplate(url)
{
    url = (url || '').trim();

    if(url === ''){
        return '';
    }

    url = url.replace('/public/uploads/templates/', '/uploads/templates/');

    if(/^https?:\/\//i.test(url) || url.indexOf('//') === 0 || url.indexOf('data:image/') === 0){
        return url;
    }

    if(url.charAt(0) !== '/'){
        url = '/' + url;
    }

    return (typeof BASE_URL !== 'undefined' ? BASE_URL : '') + url;
}

function renderizarHeaderMidiaTemplate(formato, urlMidia, nomeMidia)
{
    formato = String(formato || '').toUpperCase();
    urlMidia = normalizarUrlPreviewTemplate(urlMidia);
    nomeMidia = nomeMidia || 'Mídia enviada para aprovação';

    if(formato == 'IMAGE' && urlMidia){
        return '<div class="mb-2"><img src="' + escapeHtmlTemplatePreview(urlMidia) + '" alt="Imagem do cabeçalho" class="img-fluid rounded border" style="max-width:100%;max-height:220px;"></div>';
    }

    if(formato == 'VIDEO' && urlMidia){
        return '<div class="mb-2"><video controls class="w-100 rounded border" style="max-height:260px;"><source src="' + escapeHtmlTemplatePreview(urlMidia) + '"></video><small class="text-muted">' + escapeHtmlTemplatePreview(nomeMidia) + '</small></div>';
    }

    let iconeMidia = formato == 'IMAGE' ? 'fa-image' : (formato == 'VIDEO' ? 'fa-video' : 'fa-file-pdf');
    let texto = formato == 'IMAGE' ? 'Imagem no cabeçalho' : (formato == 'VIDEO' ? 'Vídeo no cabeçalho' : nomeMidia);

    return '<div class="alert alert-info"><i class="fas ' + iconeMidia + '"></i> ' + escapeHtmlTemplatePreview(texto) + '</div>';
}

function abrirPreviewTemplate(botao)
{
    botao = $(botao);

    $('#tmpNome').html(botao.data('nome'));
    $('#tmpStatus').html(escapeHtmlTemplatePreview(traduzirStatusTemplateMeta(botao.data('status'))));
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
    let headerMidiaRenderizado = false;
    let headerTipoNormalizado = String(botao.attr('data-header-tipo') || '').toUpperCase();
    let headerMidiaUrlNormalizada = botao.attr('data-header-midia-url') || '';
    let headerDocumentoNomeNormalizado = botao.attr('data-header-documento-nome') || '';

    componentes.forEach(function(comp){

        if(comp.type == 'HEADER' && comp.format == 'TEXT'){
            html += '<div class="alert alert-secondary"><strong>' + comp.text + '</strong></div>';
        }

        if(comp.type == 'HEADER' && ['IMAGE','VIDEO','DOCUMENT'].indexOf(String(comp.format || '').toUpperCase()) >= 0){
            let formato = String(comp.format || '').toUpperCase();
            let nomeMidia = headerDocumentoNomeNormalizado || comp.media_name || 'Mídia enviada para aprovação';
            let urlMidia = headerMidiaUrlNormalizada || comp.media_url || '';
            html += renderizarHeaderMidiaTemplate(formato, urlMidia, nomeMidia);
            headerMidiaRenderizado = true;
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

    if(!headerMidiaRenderizado && ['IMAGE','VIDEO','DOCUMENT'].indexOf(headerTipoNormalizado) >= 0){
        html = renderizarHeaderMidiaTemplate(
            headerTipoNormalizado,
            headerMidiaUrlNormalizada,
            headerDocumentoNomeNormalizado || 'Mídia enviada para aprovação'
        ) + html;
    }

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


function ajudaMidiaHeaderMeta(tipo)
{
    tipo = String(tipo || '').toUpperCase();

    const config = {
        IMAGE: {
            ajuda: 'Imagem: JPG, PNG ou WEBP até 5 MB.',
            accept: '.jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp'
        },
        VIDEO: {
            ajuda: 'Vídeo: MP4 ou 3GPP até 16 MB.',
            accept: '.mp4,.3gp,.3gpp,video/mp4,video/3gpp'
        },
        DOCUMENT: {
            ajuda: 'Documento: PDF até 10 MB.',
            accept: '.pdf,application/pdf'
        }
    };

    return config[tipo] || {ajuda: 'Selecione um tipo de mídia.', accept: ''};
}

function limparUploadMidiaMeta(inputSelector, nomeSelector, previewSelector)
{
    $(inputSelector).val('');
    $(nomeSelector).text('');
    $(previewSelector).hide().attr('src', '');
}

function configurarUploadMidiaMeta(dropSelector, inputSelector, nomeSelector, previewSelector)
{
    const drop = $(dropSelector);
    const input = $(inputSelector);

    drop.on('click keydown', function(e){
        if(e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' '){
            return;
        }

        e.preventDefault();
        e.stopPropagation();

        const inputFile = input.get(0);
        if(inputFile && !inputFile.disabled){
            inputFile.click();
        }
    });

    input.on('click', function(e){
        e.stopPropagation();
    });

    drop.on('dragover', function(e){ e.preventDefault(); drop.addClass('border-primary'); });
    drop.on('dragleave drop', function(e){ e.preventDefault(); drop.removeClass('border-primary'); });
    drop.on('drop', function(e){
        const files = e.originalEvent.dataTransfer.files;
        if(files && files.length){
            input[0].files = files;
            input.trigger('change');
        }
    });

    input.on('change', function(){
        const file = this.files && this.files[0] ? this.files[0] : null;
        $(nomeSelector).text(file ? file.name : '');
        $(previewSelector).hide().attr('src', '');
        if(file && file.type && file.type.indexOf('image/') === 0){
            const reader = new FileReader();
            reader.onload = function(e){ $(previewSelector).attr('src', e.target.result).show(); };
            reader.readAsDataURL(file);
        }
    });
}

const alterarTipoHeaderOriginal = typeof alterarTipoHeader === 'function' ? alterarTipoHeader : null;
alterarTipoHeader = function(tipo){
    if(alterarTipoHeaderOriginal){ alterarTipoHeaderOriginal(tipo); }
    if(['IMAGE','VIDEO','DOCUMENT'].indexOf(tipo) >= 0){
        const config = ajudaMidiaHeaderMeta(tipo);
        $('#areaHeaderTexto').hide();
        $('#areaHeaderMidia').show();
        $('#headerMediaAjuda').text(config.ajuda);
        $('#header_media')
            .prop('required', true)
            .prop('disabled', false)
            .attr('accept', config.accept);
        limparUploadMidiaMeta('#header_media', '#headerMediaNome', '#headerMediaPreview');
    }else{
        $('#areaHeaderMidia').hide();
        $('#header_media').prop('required', false).prop('disabled', false).attr('accept', '');
        limparUploadMidiaMeta('#header_media', '#headerMediaNome', '#headerMediaPreview');
    }
};

configurarUploadMidiaMeta('.meta-media-drop[data-input="header_media"]', '#header_media', '#headerMediaNome', '#headerMediaPreview');


$(document).on('click', '.btnEditarTemplate', function(){
    $('#editarTemplateId').val($(this).data('id'));
    $('#editarTemplateNome').text($(this).data('nome'));
    $('#editarTemplateHeader').text($(this).data('header-tipo') || 'Sem header');
    let documento = $(this).data('documento') || '';
    $('#editarTemplateMidiaAtual').text(documento ? ('Mídia atual: ' + documento) : 'Sem arquivo de mídia local exibível.');
    $('#modalEditarTemplate').modal('show');
});


<?php if(!empty($templateMetaErrorModal) && is_array($templateMetaErrorModal)){ ?>
$(function(){
    $('#modalErroTemplateMeta').modal('show');
});
<?php } ?>

</script>