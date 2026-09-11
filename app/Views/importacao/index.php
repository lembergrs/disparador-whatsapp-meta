<?php

if(!function_exists('formatarTelefone')){

    function formatarTelefone($telefone)
    {
        $telefone = preg_replace('/\D/', '', $telefone);

        if(substr($telefone, 0, 2) == '55'){
            $telefone = substr($telefone, 2);
        }

        if(strlen($telefone) == 11){
            return '(' . substr($telefone, 0, 2) . ') '
                . substr($telefone, 2, 5)
                . '-'
                . substr($telefone, 7);
        }

        if(strlen($telefone) == 10){
            return '(' . substr($telefone, 0, 2) . ') '
                . substr($telefone, 2, 4)
                . '-'
                . substr($telefone, 6);
        }

        return $telefone;
    }

}

?>
<?php if(isset($_SESSION['sucesso'])){ ?>

<div class="alert alert-success">
<?= htmlspecialchars($_SESSION['sucesso'], ENT_QUOTES, 'UTF-8'); ?>
</div>

<?php unset($_SESSION['sucesso']); ?>

<?php } ?>

<?php if(isset($_SESSION['erro'])){ ?>

<div class="alert alert-danger">
<?= htmlspecialchars($_SESSION['erro'], ENT_QUOTES, 'UTF-8'); ?>
</div>

<?php unset($_SESSION['erro']); ?>

<?php } ?>

<?php if(!empty($listaSelecionadaDados)){ ?>

<div class="alert alert-info" id="alertaListaImportacao">
    <i class="fas fa-info-circle"></i>
    Importando contatos para a lista:
    <strong id="nomeListaImportacao"><?= htmlspecialchars($listaSelecionadaDados['LST_Nome'], ENT_QUOTES, 'UTF-8'); ?></strong>
</div>

<?php }else{ ?>

<div class="alert alert-info" id="alertaListaImportacao" style="display:none;">
    <i class="fas fa-info-circle"></i>
    Importando contatos para a lista:
    <strong id="nomeListaImportacao"></strong>
</div>

<?php } ?>

<div class="card card-outline card-info">

<div class="card-header">
<h3 class="card-title">
<i class="fas fa-address-book"></i>
Formatos aceitos
</h3>
</div>

<div class="card-body">

<div class="row align-items-center">

<div class="col-md-8">

<div class="alert alert-info mb-0">
<strong>Você pode importar contatos de duas formas:</strong>
<ul class="mb-0 pl-3">
<li><strong>Planilha XLS/XLSX:</strong> primeira linha com os nomes das colunas e telefone na segunda coluna.</li>
<li><strong>Arquivo VCF (vCard):</strong> exportado diretamente dos contatos do celular, Google Contatos, iPhone ou outros aplicativos compatíveis.</li>
<li>Telefone é obrigatório; contatos sem telefone são ignorados.</li>
<li>Se um contato no VCF tiver mais de um telefone, cada número é processado individualmente.</li>
<li>Nome, telefone e e-mail do VCF são preservados como dados do contato quando disponíveis.</li>
</ul>
</div>

</div>

<div class="col-md-4 text-md-right mt-3 mt-md-0">
<a
href="<?= BASE_URL; ?>/index.php?url=listaContato/baixarModelo"
class="btn btn-primary"
download
>
<i class="fas fa-download"></i>
Baixar Planilha Modelo
</a>
</div>

</div>

</div>

</div>

<div class="card">

<div class="card-header">

<form
action="<?= BASE_URL; ?>/index.php?url=importacao/importar"
method="POST"
enctype="multipart/form-data"
>

<?= \Core\Csrf::input(); ?>

<div class="row">

<div class="col-md-4">

<div class="form-group">

<label>Lista de Contatos</label>

<select
name="lista_id"
id="lista_id"
class="form-control"
required
>

<option value="">
Selecione uma lista
</option>

<?php foreach($listas as $lista){ ?>

<option
value="<?= (int) $lista['LST_ID']; ?>"
data-nome="<?= htmlspecialchars($lista['LST_Nome'], ENT_QUOTES, 'UTF-8'); ?>"
<?= isset($listaSelecionada) && (int) $listaSelecionada === (int) $lista['LST_ID'] ? 'selected' : ''; ?>
>
<?= htmlspecialchars($lista['LST_Nome'], ENT_QUOTES, 'UTF-8'); ?>
(<?= (int) $lista['total_contatos']; ?> contatos)
</option>

<?php } ?>

<option value="nova">
+ Criar nova lista
</option>

</select>

</div>

</div>

<div
class="col-md-4"
id="areaNovaLista"
style="display:none;"
>

<div class="form-group">

<label>Nome da Nova Lista</label>

<input
type="text"
name="nova_lista"
id="nova_lista"
class="form-control"
placeholder="Ex: Clientes Junho"
>

</div>

</div>

<div class="col-md-4">

<div class="form-group">

<label>Arquivo XLS, XLSX ou VCF</label>

<div class="custom-file">

<input
type="file"
name="arquivo"
class="custom-file-input"
accept=".xls,.xlsx,.vcf,text/vcard,text/x-vcard"
required
>

<label class="custom-file-label">
Escolher arquivo
</label>

</div>

<small class="form-text text-muted">
Para importar contatos do celular, exporte-os como arquivo .vcf e selecione o arquivo aqui.
</small>

</div>

</div>

</div>

<button
type="submit"
class="btn btn-success"
>

<i class="fas fa-upload"></i>
Importar

</button>

</form>

</div>

<div class="card-body">

<table
id="tabelaContatos"
class="table table-bordered table-striped table-hover datatable"
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

<td><?= (int) $contato['CON_ID']; ?></td>

<td><?= htmlspecialchars($contato['CON_Nome'], ENT_QUOTES, 'UTF-8'); ?></td>

<td><?= htmlspecialchars(formatarTelefone($contato['CON_Telefone']), ENT_QUOTES, 'UTF-8'); ?></td>

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

    function atualizarAlertaListaSelecionada()
    {
        const option = $('#lista_id option:selected');
        const nome = option.data('nome');

        if(nome){
            $('#nomeListaImportacao').text(nome);
            $('#alertaListaImportacao').show();
        }else{
            $('#nomeListaImportacao').text('');
            $('#alertaListaImportacao').hide();
        }
    }

    function alternarNovaLista()
    {
        atualizarAlertaListaSelecionada();

        if($('#lista_id').val() === 'nova'){
            $('#areaNovaLista').show();
            $('#nova_lista').prop('required', true);
        }else{
            $('#areaNovaLista').hide();
            $('#nova_lista').prop('required', false);
        }
    }

    $('#lista_id').on('change', alternarNovaLista);
    alternarNovaLista();

    $('.custom-file-input').on('change', function(){
        const nomeArquivo = (this.files && this.files.length)
            ? this.files[0].name
            : 'Escolher arquivo';

        $(this).next('.custom-file-label').text(nomeArquivo);
    });

    $('#tabelaContatos').DataTable({
        language: {
            url:
            '//cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json'
        }
    });

});

</script>
