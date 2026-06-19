<div class="card">

    <div class="card-header">

        <h3 class="card-title">
            Listas de Contatos
        </h3>

        <div class="card-tools">

            <button
            type="button"
            class="btn btn-success btn-sm"
            onclick="abrirModalNovaLista()"
            >
                <i class="fas fa-plus"></i>
                Nova Lista
            </button>

        </div>

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

                <td><?= (int) $lista['LST_ID']; ?></td>

                <td><?= htmlspecialchars($lista['LST_Nome'], ENT_QUOTES, 'UTF-8'); ?></td>

                <td><?= (int) $lista['total_contatos']; ?></td>

                <td><?= (int) $lista['total_campanhas']; ?></td>

                <td>
                    <?= date(
                        'd/m/Y H:i',
                        strtotime($lista['LST_DataCadastro'])
                    ); ?>
                </td>

                <td>

                    <a
                    href="<?= BASE_URL; ?>/index.php?url=listaContato/visualizar&id=<?= (int) $lista['LST_ID']; ?>"
                    class="btn btn-info btn-sm"
                    >
                        <i class="fas fa-eye"></i>
                        Ver
                    </a>

                    <button
                    type="button"
                    class="btn btn-primary btn-sm"
                    onclick="abrirModalEditarLista(
                        '<?= (int) $lista['LST_ID']; ?>',
                        '<?= htmlspecialchars($lista['LST_Nome'], ENT_QUOTES, 'UTF-8'); ?>'
                    )"
                    >
                        <i class="fas fa-edit"></i>
                        Editar
                    </button>

                    <a
                    href="<?= BASE_URL; ?>/index.php?url=importacao&lista=<?= (int) $lista['LST_ID']; ?>"
                    class="btn btn-success btn-sm"
                    >
                        <i class="fas fa-upload"></i>
                        Importar
                    </a>

                    <a
                    href="#"
                    data-post-url="<?= BASE_URL; ?>/index.php?url=listaContato/duplicar"
                    data-field-id="<?= (int) $lista['LST_ID']; ?>"
                    data-confirm="Deseja duplicar esta lista?"
                    class="btn btn-warning btn-sm"
                    >
                        <i class="fas fa-copy"></i>
                        Duplicar
                    </a>

                    <a
                    href="#"
                    data-post-url="<?= BASE_URL; ?>/index.php?url=listaContato/inativar"
                    data-field-id="<?= (int) $lista['LST_ID']; ?>"
                    data-confirm="Deseja realmente inativar esta lista?"
                    class="btn btn-danger btn-sm"
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
id="modalNovaLista"
data-backdrop="static"
data-keyboard="false"
>

<div class="modal-dialog">

<div class="modal-content">

<form
method="POST"
action="<?= BASE_URL; ?>/index.php?url=listaContato/criar"
>

<?= \Core\Csrf::input(); ?>

<div class="modal-header">

<h4 class="modal-title">
Nova Lista
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

<div class="form-group">

<label>Nome da Lista</label>

<input
type="text"
name="nome"
id="lista_nome_nova"
class="form-control"
placeholder="Ex: Clientes Junho"
required
>

</div>

</div>

<div class="modal-footer">

<button
type="submit"
class="btn btn-success"
>
<i class="fas fa-plus"></i>
Criar Lista
</button>

</div>

</form>

</div>

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
action="<?= BASE_URL; ?>/index.php?url=listaContato/salvarEdicao"
>

<?= \Core\Csrf::input(); ?>

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
class="close"
data-dismiss="modal"
aria-label="Close"
>
    <span aria-hidden="true">&times;</span>
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

function abrirModalNovaLista()
{
    $('#lista_nome_nova').val('');

    $('#modalNovaLista').modal({
        backdrop: 'static',
        keyboard: false
    });

    $('#modalNovaLista').modal('show');
}

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

$('#modalNovaLista').on('hidden.bs.modal', function(){

    $('#lista_nome_nova').val('');

});

$('#modalEditarLista').on('hidden.bs.modal', function(){

    $('#lista_id_editar').val('');
    $('#lista_nome_editar').val('');

});

</script>
