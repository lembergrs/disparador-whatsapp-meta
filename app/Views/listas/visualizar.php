<?php

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

<div class="card">

    <div class="card-header">

        <h3 class="card-title">
            <?= $lista['LST_Nome']; ?>
        </h3>

        <div class="card-tools">

            <a
            href="<?= BASE_URL; ?>/index.php?url=importacao&lista=<?= $lista['LST_ID']; ?>"
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
                    <th>Nome</th>
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

                <td><?= $contato['CON_Nome']; ?></td>

                <td>
                    <?= formatarTelefone(
                        $contato['CON_Telefone']
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
                    href="<?= BASE_URL; ?>/index.php?url=listaContato/removerContato&lista=<?= $lista['LST_ID']; ?>&contato=<?= $contato['CON_ID']; ?>"
                    class="btn btn-danger btn-sm"
                    onclick="return confirm('Deseja remover este contato da lista?')"
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
id="modalAdicionarContato"
>

<div class="modal-dialog">

<div class="modal-content">

<form
method="POST"
action="<?= BASE_URL; ?>/index.php?url=listaContato/adicionarContato"
>

<input
type="hidden"
name="lista_id"
value="<?= $lista['LST_ID']; ?>"
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

$('#modalAdicionarContato').on('hidden.bs.modal', function(){
    limparModalAdicionarContato();
});

$('#modalAdicionarContato').on('show.bs.modal', function(){
    limparModalAdicionarContato();
});

</script>