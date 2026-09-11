<?php

if(!function_exists('formatarTelefone')){

function formatarTelefone($telefone)
{
    $telefone =
        preg_replace('/\D/', '', $telefone);

    if(substr($telefone, 0, 2) == '55'){
        $telefone =
            substr($telefone, 2);
    }

    if(strlen($telefone) == 11){

        return '('
            . substr($telefone, 0, 2)
            . ') '
            . substr($telefone, 2, 5)
            . '-'
            . substr($telefone, 7);

    }

    if(strlen($telefone) == 10){

        return '('
            . substr($telefone, 0, 2)
            . ') '
            . substr($telefone, 2, 4)
            . '-'
            . substr($telefone, 6);

    }

    return $telefone;
}

}

?>
<div class="row">

    <div class="col-md-4">

        <div class="small-box bg-info">

            <div class="inner">

                <h3>
                    <?= count($contatos); ?>
                </h3>

                <p>
                    Total de contatos
                </p>

            </div>

            <div class="icon">

                <i class="fas fa-users"></i>

            </div>

        </div>

    </div>

    <div class="col-md-4">

        <div class="small-box bg-success">

            <div class="inner">

                <h3>
                    <?= date(
                        'd/m/Y',
                        strtotime($lista['LST_DataCadastro'])
                    ); ?>
                </h3>

                <p>
                    Data de criação
                </p>

            </div>

            <div class="icon">

                <i class="fas fa-calendar"></i>

            </div>

        </div>

    </div>

</div>

<form
id="formRemoverSelecionados"
method="POST"
action="<?= BASE_URL; ?>/index.php?url=listaContato/removerContatosSelecionados"
>

<?= \Core\Csrf::input(); ?>
<input type="hidden" name="lista" value="<?= (int) $lista['LST_ID']; ?>">

<div class="card">

    <div class="card-header">

        <h3 class="card-title">
            <i class="fas fa-list"></i>
            <?= htmlspecialchars($lista['LST_Nome'], ENT_QUOTES, 'UTF-8'); ?>
        </h3>

        <div class="card-tools">

            <button
            type="submit"
            id="btnExcluirSelecionados"
            class="btn btn-danger btn-sm"
            disabled
            >
                <i class="fas fa-trash"></i>
                <span id="textoExcluirSelecionados">Excluir selecionados</span>
            </button>

            <a
            href="<?= BASE_URL; ?>/index.php?url=importacao&lista=<?= (int) $lista['LST_ID']; ?>"
            class="btn btn-success btn-sm"
            >

                <i class="fas fa-upload"></i>
                Importar contatos

            </a>

            <button
            type="button"
            class="btn btn-primary btn-sm"
            data-toggle="modal"
            data-target="#modalAdicionarContato"
            data-backdrop="static"
            data-keyboard="false"
            >
                <i class="fas fa-user-plus"></i>
                Adicionar contato
            </button>

            <a
            href="<?= BASE_URL; ?>/index.php?url=listaContato"
            class="btn btn-secondary btn-sm"
            >

                <i class="fas fa-arrow-left"></i>
                Voltar

            </a>

        </div>

    </div>

    <div class="card-body">

        <table
        id="tabelaContatosLista"
        class="table table-bordered table-striped table-hover datatable"
        >

            <thead>

                <tr>
                    <th>
                        <input
                        type="checkbox"
                        id="selecionarTodosPagina"
                        aria-label="Selecionar contatos desta página"
                        title="Selecionar contatos desta página"
                        class="mr-2"
                        >
                        Nome
                    </th>
                    <th>Telefone</th>
                    <th>Importação</th>
                    <th width="100">
                        Ações
                    </th>
                </tr>

            </thead>

            <tbody>

            <?php foreach($contatos as $contato){ ?>

            <tr>

                <td>
                    <input
                    type="checkbox"
                    class="contato-selecao mr-2"
                    value="<?= (int) $contato['CON_ID']; ?>"
                    aria-label="Selecionar <?= htmlspecialchars($contato['CON_Nome'], ENT_QUOTES, 'UTF-8'); ?>"
                    >
                    <?= htmlspecialchars($contato['CON_Nome'], ENT_QUOTES, 'UTF-8'); ?>
                </td>

                <td>
                    <?= htmlspecialchars(
                        formatarTelefone(
                            $contato['CON_Telefone']
                        ),
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>
                </td>

                <td>
                    <?= date(
                        'd/m/Y H:i',
                        strtotime($contato['CON_DataImportacao'])
                    ); ?>
                </td>

                <td>

                    <a
                    href="#"
                    data-post-url="<?= BASE_URL; ?>/index.php?url=listaContato/removerContato"
                    data-field-lista="<?= (int) $lista['LST_ID']; ?>"
                    data-field-contato="<?= (int) $contato['CON_ID']; ?>"
                    data-confirm="Deseja remover este contato da lista?"
                    class="btn btn-danger btn-sm"
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

</form>

<div
class="modal fade"
id="modalAdicionarContato"
>

<div class="modal-dialog">

<div class="modal-content">

<form
method="POST"
action="<?= BASE_URL; ?>/index.php?url=listaContato/adicionarContato"
>

<?= \Core\Csrf::input(); ?>

<input
type="hidden"
name="lista_id"
value="<?= (int) $lista['LST_ID']; ?>"
>

<div class="modal-header">

<h4 class="modal-title">
Adicionar Contato
</h4>

<button
type="button"
class="close btn-fechar-modal-contato"
>

<span>&times;</span>
</button>

</div>

<div class="modal-body">

<div class="form-group">

<label>Nome</label>

<input
type="text"
name="nome"
class="form-control"
required
>

</div>

<div class="form-group">

<label>Telefone</label>

<input
type="text"
name="telefone"
id="telefoneManual"
class="form-control"
placeholder="(41) 99999-9999"
maxlength="15"
required
>

</div>

</div>

<div class="modal-footer">

<button
type="submit"
class="btn btn-primary"
>
Salvar
</button>

</div>

</form>

</div>

</div>

</div>

<script>

function limparModalAdicionarContato()
{
    document.querySelector('#modalAdicionarContato input[name="nome"]').value = '';
    document.querySelector('#telefoneManual').value = '';
}

var contatosSelecionados = new Set();

function obterCheckboxesPaginaAtual()
{
    if(
        $.fn.DataTable
        && $.fn.DataTable.isDataTable('#tabelaContatosLista')
    ){
        return $('#tabelaContatosLista')
            .DataTable()
            .rows({page: 'current'})
            .nodes()
            .to$()
            .find('.contato-selecao')
            .toArray();
    }

    return Array.prototype.slice.call(
        document.querySelectorAll('#tabelaContatosLista tbody .contato-selecao')
    );
}

function atualizarBotaoSelecionados()
{
    var total = contatosSelecionados.size;
    var botao = document.getElementById('btnExcluirSelecionados');
    var texto = document.getElementById('textoExcluirSelecionados');

    botao.disabled = total === 0;
    texto.textContent = total > 0
        ? 'Excluir selecionados (' + total + ')'
        : 'Excluir selecionados';
}

function atualizarCheckboxTodosPagina()
{
    var todos = obterCheckboxesPaginaAtual();
    var checkboxTodos = document.getElementById('selecionarTodosPagina');

    if(!checkboxTodos){
        return;
    }

    if(!todos.length){
        checkboxTodos.checked = false;
        checkboxTodos.indeterminate = false;
        return;
    }

    var marcados = todos.filter(function(checkbox){
        return checkbox.checked;
    }).length;

    checkboxTodos.checked = marcados === todos.length;
    checkboxTodos.indeterminate = marcados > 0 && marcados < todos.length;
}

function restaurarSelecaoVisivel()
{
    obterCheckboxesPaginaAtual().forEach(function(checkbox){
        checkbox.checked = contatosSelecionados.has(checkbox.value);
    });

    atualizarCheckboxTodosPagina();
}

var checkboxSelecionarTodosPagina = document.getElementById('selecionarTodosPagina');
if(checkboxSelecionarTodosPagina){
    checkboxSelecionarTodosPagina.addEventListener('click', function(e){
        e.stopPropagation();
    });
}

document.addEventListener('change', function(e){

    if(e.target && e.target.classList.contains('contato-selecao')){
        if(e.target.checked){
            contatosSelecionados.add(e.target.value);
        }else{
            contatosSelecionados.delete(e.target.value);
        }

        atualizarBotaoSelecionados();
        atualizarCheckboxTodosPagina();
    }

    if(e.target && e.target.id === 'selecionarTodosPagina'){
        obterCheckboxesPaginaAtual().forEach(function(checkbox){
            checkbox.checked = e.target.checked;

            if(e.target.checked){
                contatosSelecionados.add(checkbox.value);
            }else{
                contatosSelecionados.delete(checkbox.value);
            }
        });

        atualizarBotaoSelecionados();
        atualizarCheckboxTodosPagina();
    }

});

document.getElementById('formRemoverSelecionados').addEventListener('submit', function(e){
    var total = contatosSelecionados.size;

    if(total === 0){
        e.preventDefault();
        return;
    }

    var mensagem = total === 1
        ? 'Deseja remover o contato selecionado desta lista? O contato continuará cadastrado no sistema.'
        : 'Deseja remover os ' + total + ' contatos selecionados desta lista? Os contatos continuarão cadastrados no sistema.';

    if(!window.confirm(mensagem)){
        e.preventDefault();
        return;
    }

    this.querySelectorAll('input.contato-selecionado-envio').forEach(function(input){
        input.remove();
    });

    contatosSelecionados.forEach(function(contatoId){
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'contatos[]';
        input.value = contatoId;
        input.className = 'contato-selecionado-envio';
        this.appendChild(input);
    }, this);
});

document.addEventListener('click', function(e){

    if(e.target.closest('.btn-fechar-modal-contato')){
        limparModalAdicionarContato();
        $('#modalAdicionarContato').modal('hide');
    }

});

document.addEventListener('input', function(e){

    if(e.target && e.target.id === 'telefoneManual'){

        let valor = e.target.value.replace(/\D/g, '').substring(0, 11);

        if(valor.length > 10){
            e.target.value = '(' + valor.substring(0, 2) + ') ' + valor.substring(2, 7) + '-' + valor.substring(7);
        }else if(valor.length > 6){
            e.target.value = '(' + valor.substring(0, 2) + ') ' + valor.substring(2, 6) + '-' + valor.substring(6);
        }else if(valor.length > 2){
            e.target.value = '(' + valor.substring(0, 2) + ') ' + valor.substring(2);
        }else{
            e.target.value = valor;
        }

    }

});

$('#tabelaContatosLista').on('draw.dt', function(){
    restaurarSelecaoVisivel();
});

$('#modalAdicionarContato').on('hidden.bs.modal', function(){
    limparModalAdicionarContato();
});

$('#modalAdicionarContato').on('show.bs.modal', function(){
    limparModalAdicionarContato();
});

atualizarBotaoSelecionados();
atualizarCheckboxTodosPagina();

</script>
