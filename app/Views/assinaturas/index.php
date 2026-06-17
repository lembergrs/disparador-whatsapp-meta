<?php
function moedaAssinatura($valor){ return 'R$ ' . number_format((float) $valor, 2, ',', '.'); }
function dataAssinatura($data){ return $data ? date('d/m/Y', strtotime($data)) : '-'; }
function badgeAssinatura($status)
{
    $classes = [
        'ativa' => 'success',
        'pendente' => 'warning',
        'vencida' => 'danger',
        'cancelada' => 'secondary'
    ];

    $class = $classes[$status] ?? 'secondary';

    return '<span class="badge badge-' . $class . '">' . ucfirst($status) . '</span>';
}
?>

<div class="card">
    <div class="card-header">
        <button class="btn btn-primary" data-toggle="modal" data-target="#modalAssinatura">
            <i class="fas fa-plus"></i> Nova Assinatura
        </button>
    </div>

    <div class="card-body">
        <table id="tabelaAssinaturas" class="table table-bordered table-striped table-hover datatable">
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Plano</th>
                    <th>Ciclo</th>
                    <th>Valor</th>
                    <th>Próxima cobrança</th>
                    <th>Status</th>
                    <th>Data início</th>
                    <th>Data fim</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($assinaturas as $assinatura){ ?>
                    <tr>
                        <td><?= htmlspecialchars($assinatura['CLI_Nome']); ?></td>
                        <td><?= htmlspecialchars($assinatura['PLA_Nome']); ?></td>
                        <td><?= htmlspecialchars($assinatura['ASS_Ciclo']); ?></td>
                        <td><?= moedaAssinatura($assinatura['ASS_Valor']); ?></td>
                        <td><?= dataAssinatura($assinatura['ASS_DataProximaCobranca']); ?></td>
                        <td><?= badgeAssinatura($assinatura['ASS_Status']); ?></td>
                        <td><?= dataAssinatura($assinatura['ASS_DataInicio']); ?></td>
                        <td><?= dataAssinatura($assinatura['ASS_DataFim']); ?></td>
                        <td>
                            <button
                                type="button"
                                class="btn btn-info btn-sm btnEditarAssinatura"
                                data-id="<?= $assinatura['ASS_ID']; ?>"
                                data-cliente="<?= $assinatura['CLI_ID']; ?>"
                                data-plano="<?= $assinatura['PLA_ID']; ?>"
                                data-ciclo="<?= htmlspecialchars($assinatura['ASS_Ciclo']); ?>"
                                data-status="<?= htmlspecialchars($assinatura['ASS_Status']); ?>"
                                data-valor="<?= number_format($assinatura['ASS_Valor'], 2, ',', '.'); ?>"
                                data-dia="<?= $assinatura['ASS_DiaVencimento']; ?>"
                                data-inicio="<?= $assinatura['ASS_DataInicio']; ?>"
                                data-fim="<?= $assinatura['ASS_DataFim']; ?>"
                                data-proxima="<?= $assinatura['ASS_DataProximaCobranca']; ?>"
                            >
                                <i class="fas fa-edit"></i>
                            </button>

                            <?php if($assinatura['ASS_Status'] != 'ativa'){ ?>
                                <a href="<?= BASE_URL; ?>/index.php?url=assinatura/ativar&id=<?= $assinatura['ASS_ID']; ?>" class="btn btn-success btn-sm" onclick="return confirm('Ativar esta assinatura?')">
                                    <i class="fas fa-check"></i>
                                </a>
                            <?php } ?>

                            <?php if($assinatura['ASS_Status'] != 'vencida'){ ?>
                                <a href="<?= BASE_URL; ?>/index.php?url=assinatura/marcarVencida&id=<?= $assinatura['ASS_ID']; ?>" class="btn btn-warning btn-sm" onclick="return confirm('Marcar como vencida?')">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </a>
                            <?php } ?>

                            <?php if($assinatura['ASS_Status'] != 'cancelada'){ ?>
                                <a href="<?= BASE_URL; ?>/index.php?url=assinatura/cancelar&id=<?= $assinatura['ASS_ID']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Cancelar esta assinatura?')">
                                    <i class="fas fa-ban"></i>
                                </a>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modalAssinatura" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form method="post" action="<?= BASE_URL; ?>/index.php?url=assinatura/salvar" id="formAssinatura">
                <div class="modal-header">
                    <h5 class="modal-title">Nova Assinatura</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="assinatura_id" id="assinatura_id">

                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Cliente</label>
                            <select name="cliente" id="assinatura_cliente" class="form-control" required>
                                <option value="">Selecione</option>
                                <?php foreach($clientes as $cliente){ ?>
                                    <option value="<?= $cliente['CLI_ID']; ?>"><?= htmlspecialchars($cliente['CLI_Nome']); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Plano</label>
                            <select name="plano" id="assinatura_plano" class="form-control" required>
                                <option value="">Selecione</option>
                                <?php foreach($planos as $plano){ ?>
                                    <option value="<?= $plano['PLA_ID']; ?>" data-ciclo="<?= htmlspecialchars($plano['PLA_Periodicidade']); ?>" data-valor="<?= number_format($plano['PLA_Valor'], 2, ',', '.'); ?>">
                                        <?= htmlspecialchars($plano['PLA_Nome']); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3 form-group">
                            <label>Ciclo</label>
                            <input type="text" name="ciclo" id="assinatura_ciclo" class="form-control" required>
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Status</label>
                            <select name="status" id="assinatura_status" class="form-control" required>
                                <option value="ativa">Ativa</option>
                                <option value="pendente">Pendente</option>
                                <option value="vencida">Vencida</option>
                                <option value="cancelada">Cancelada</option>
                            </select>
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Valor</label>
                            <input type="text" name="valor" id="assinatura_valor" class="form-control" required>
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Dia vencimento</label>
                            <input type="number" min="1" max="31" name="dia_vencimento" id="assinatura_dia" class="form-control" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label>Data início</label>
                            <input type="date" name="data_inicio" id="assinatura_inicio" class="form-control" required>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Data fim</label>
                            <input type="date" name="data_fim" id="assinatura_fim" class="form-control">
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Próxima cobrança</label>
                            <input type="date" name="proxima_cobranca" id="assinatura_proxima" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">Salvar</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(function(){
    if($('#tabelaAssinaturas').length && $.fn.DataTable && !$.fn.DataTable.isDataTable('#tabelaAssinaturas')){
        $('#tabelaAssinaturas').DataTable({
            language: {url: 'https://cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json'},
            order: [[0, 'asc']]
        });
    }

    $('#assinatura_plano').on('change', function(){
        let opt = $(this).find(':selected');
        if(opt.val()){
            $('#assinatura_ciclo').val(opt.data('ciclo'));
            $('#assinatura_valor').val(opt.data('valor'));
        }
    });

    $(document).on('click', '.btnEditarAssinatura', function(){
        $('#assinatura_id').val($(this).data('id'));
        $('#assinatura_cliente').val($(this).data('cliente'));
        $('#assinatura_plano').val($(this).data('plano'));
        $('#assinatura_ciclo').val($(this).data('ciclo'));
        $('#assinatura_status').val($(this).data('status'));
        $('#assinatura_valor').val($(this).data('valor'));
        $('#assinatura_dia').val($(this).data('dia'));
        $('#assinatura_inicio').val($(this).data('inicio'));
        $('#assinatura_fim').val($(this).data('fim'));
        $('#assinatura_proxima').val($(this).data('proxima'));
        $('#modalAssinatura .modal-title').text('Editar Assinatura');
        $('#modalAssinatura').modal('show');
    });

    $('#modalAssinatura').on('hidden.bs.modal', function(){
        $('#formAssinatura')[0].reset();
        $('#assinatura_id').val('');
        $('#modalAssinatura .modal-title').text('Nova Assinatura');
    });
});
</script>
