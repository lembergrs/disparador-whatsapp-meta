<div class="card">

    <div class="card-header">

        <h3 class="card-title">
            Listas de Contatos
        </h3>

    </div>

    <div class="card-body">

        <table
        id="tabelaListas"
        class="table table-bordered table-striped table-hover datatable"
        >

            <thead>

                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>Contatos</th>
                    <th>Campanhas</th>
                    <th>Criada em</th>
                    <th>Ações</th>
                </tr>

            </thead>

            <tbody>

            <?php foreach($listas as $lista){ ?>

            <tr>

                <td><?= $lista['LST_ID']; ?></td>

                <td><?= $lista['LST_Nome']; ?></td>

                <td><?= $lista['total_contatos']; ?></td>

                <td><?= $lista['total_campanhas']; ?></td>

                <td>
                    <?= date(
                        'd/m/Y H:i',
                        strtotime($lista['LST_DataCadastro'])
                    ); ?>
                </td>

                <td>

                    <a
                    href="<?= BASE_URL; ?>/index.php?url=listacontato/visualizar&id=<?= $lista['LST_ID']; ?>"
                    class="btn btn-info btn-sm"
                    >
                        <i class="fas fa-eye"></i>
                        Ver
                    </a>

                    <button
                    type="button"
                    class="btn btn-primary btn-sm"
                    onclick="abrirModalEditarLista(
                        '<?= $lista['LST_ID']; ?>',
                        '<?= htmlspecialchars($lista['LST_Nome'], ENT_QUOTES, 'UTF-8'); ?>'
                    )"
                    >
                        <i class="fas fa-edit"></i>
                        Editar
                    </button>

                    <a
                    href="<?= BASE_URL; ?>/index.php?url=importacao&lista=<?= $lista['LST_ID']; ?>"
                    class="btn btn-success btn-sm"
                    >
                        <i class="fas fa-upload"></i>
                        Importar
                    </a>

                    <a
                    href="<?= BASE_URL; ?>/index.php?url=listacontato/duplicar&id=<?= $lista['LST_ID']; ?>"
                    class="btn btn-warning btn-sm"
                    onclick="return confirm('Deseja duplicar esta lista?')"
                    >
                        <i class="fas fa-copy"></i>
                        Duplicar
                    </a>

                    <a
                    href="<?= BASE_URL; ?>/index.php?url=listacontato/inativar&id=<?= $lista['LST_ID']; ?>"
                    class="btn btn-danger btn-sm"
                    onclick="return confirm('Deseja realmente inativar esta lista?')"
                    >
                        <i class="fas fa-ban"></i>
                        Inativar
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
id="modalEditarLista"
data-backdrop="static"
data-keyboard="false"
>

<div class="modal-dialog">

<div class="modal-content">

<form
method="POST"
action="<?= BASE_URL; ?>/index.php?url=listacontato/salvarEdicao"
>

<input
type="hidden"
name="id"
id="lista_id_editar"
>

<div class="modal-header">

<h4 class="modal-title">
Editar Lista
</h4>

<button
type="button"
class="close btnFecharModalEditarLista"
>
    <span>&times;</span>
</button>

</div>

<div class="modal-body">

<div class="form-group">

<label>Nome da Lista</label>

<input
type="text"
name="nome"
id="lista_nome_editar"
class="form-control"
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

$(document).ready(function(){

    $('#tabelaListas').DataTable({
        language: {
            url:
            '//cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json'
        }
    });

});

function abrirModalEditarLista(id, nome)
{
    $('#lista_id_editar').val(id);
    $('#lista_nome_editar').val(nome);

    $('#modalEditarLista').modal({
        backdrop: 'static',
        keyboard: false
    });

    $('#modalEditarLista').modal('show');
}

$('#modalEditarLista').on('hidden.bs.modal', function(){

    $('#lista_id_editar').val('');
    $('#lista_nome_editar').val('');

});

</script>