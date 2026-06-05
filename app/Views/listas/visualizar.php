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

            <a
            href="<?= BASE_URL; ?>/index.php?url=listacontato"
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
                </tr>

            </thead>

            <tbody>

            <?php foreach($contatos as $contato){ ?>

            <tr>

                <td><?= $contato['CON_Nome']; ?></td>

                <td><?= $contato['CON_Telefone']; ?></td>

                <td>
                    <?= date(
                        'd/m/Y H:i',
                        strtotime($contato['CON_DataImportacao'])
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

    $('#tabelaContatosLista').DataTable({
        language: {
            url:
            '//cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json'
        }
    });

});

</script>