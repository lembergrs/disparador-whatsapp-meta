<?php

function statusBadge($status)
{
    $classes = [
        'rascunho' => 'secondary',
        'agendada' => 'warning',
        'processando' => 'info',
        'finalizada' => 'success',
        'cancelada' => 'danger',
        'pendente' => 'warning',
        'enviado' => 'success',
        'erro' => 'danger'
    ];

    $class = $classes[$status] ?? 'secondary';

    return '<span class="badge badge-' . $class . '">' . ucfirst($status) . '</span>';
}

function dataBR($data)
{
    if(empty($data)){
        return '-';
    }

    return date('d/m/Y H:i', strtotime($data));
}

?>

<div class="card card-primary card-outline">

    <div class="card-header">

        <h3 class="card-title">
            <i class="fas fa-bullhorn"></i>
            <?= $campanha['CAM_Nome']; ?>
        </h3>

        <div class="card-tools">

            <?= statusBadge($campanha['CAM_Status']); ?>

        </div>

    </div>

    <div class="card-body">

        <div class="row">

            <div class="col-md-4">

                <strong>Template</strong>
                <p><?= $campanha['TMP_Nome']; ?></p>

            </div>

            <div class="col-md-4">

                <strong>Agendamento</strong>
                <p><?= dataBR($campanha['CAM_DataAgendamento']); ?></p>

            </div>

            <div class="col-md-4">

                <strong>Data de cadastro</strong>
                <p><?= dataBR($campanha['CAM_DataCadastro']); ?></p>

            </div>

        </div>

        <hr>

        <div class="row">

            <div class="col-md-4">

                <div class="small-box bg-info">

                    <div class="inner">
                        <h3><?= $campanha['CAM_TotalContatos']; ?></h3>
                        <p>Total de Contatos</p>
                    </div>

                    <div class="icon">
                        <i class="fas fa-users"></i>
                    </div>

                </div>

            </div>

            <div class="col-md-4">

                <div class="small-box bg-success">

                    <div class="inner">
                        <h3><?= $campanha['CAM_TotalEnviados']; ?></h3>
                        <p>Enviados</p>
                    </div>

                    <div class="icon">
                        <i class="fas fa-paper-plane"></i>
                    </div>

                </div>

            </div>

            <div class="col-md-4">

                <div class="small-box bg-danger">

                    <div class="inner">
                        <h3><?= $campanha['CAM_TotalErros']; ?></h3>
                        <p>Erros</p>
                    </div>

                    <div class="icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>

                </div>

            </div>

        </div>

        <?php if(!empty($campanha['CAM_Descricao'])){ ?>

            <div class="alert alert-light border">

                <strong>Descrição:</strong><br>
                <?= nl2br($campanha['CAM_Descricao']); ?>

            </div>

        <?php } ?>

    </div>

</div>

<div class="card">

    <div class="card-header">

        <h3 class="card-title">
            <i class="fas fa-list"></i>
            Fila de envio
        </h3>

    </div>

    <div class="card-body">

        <table
        class="table table-bordered table-striped table-hover"
        id="tabelaFila"
        >

            <thead>

                <tr>
                    <th>ID</th>
                    <th>Contato</th>
                    <th>Telefone</th>
                    <th>Status</th>
                    <th>Tentativas</th>
                    <th>Envio</th>
                    <th>Erro</th>
                </tr>

            </thead>

            <tbody>

                <?php foreach($fila as $item){ ?>

                <tr>

                    <td><?= $item['FIL_ID']; ?></td>
                    <td><?= $item['CON_Nome']; ?></td>
                    <td><?= $item['CON_Telefone']; ?></td>
                    <td><?= statusBadge($item['FIL_Status']); ?></td>
                    <td><?= $item['FIL_Tentativas']; ?></td>
                    <td><?= dataBR($item['FIL_DataEnvio']); ?></td>
                    <td><?= $item['FIL_Erro'] ?: '-'; ?></td>

                </tr>

                <?php } ?>

            </tbody>

        </table>

    </div>

</div>

<a
href="<?= BASE_URL; ?>/index.php?url=campanha"
class="btn btn-secondary"
>
    <i class="fas fa-arrow-left"></i>
    Voltar
</a>

<?php if(in_array($campanha['CAM_Status'], ['rascunho','agendada','processando'])){ ?>

<a
href="<?= BASE_URL; ?>/index.php?url=campanha/cancelar&id=<?= $campanha['CAM_ID']; ?>"
class="btn btn-danger"
onclick="return confirm('Deseja cancelar esta campanha?')"
>
    <i class="fas fa-ban"></i>
    Cancelar Campanha
</a>

<?php } ?>

<script>

$(document).ready(function(){

    $('#tabelaFila').DataTable({

        language: {

            url:
            '//cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json'

        },

        order: [[0, 'asc']]

    });

});

</script>