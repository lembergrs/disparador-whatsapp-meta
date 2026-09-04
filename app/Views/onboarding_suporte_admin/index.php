<?php
$e = static function($valor){ return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8'); };
$data = static function($valor){ return $valor ? date('d/m/Y H:i', strtotime($valor)) : '-'; };
$statusLabel = [
    'aberta' => ['Aberta','warning'],
    'em_atendimento' => ['Em atendimento','info'],
    'concluida' => ['Concluída','success'],
    'cancelada' => ['Cancelada','secondary']
];
$periodos = \Models\OnboardingSuporteSolicitacao::PERIODOS;
$assuntos = \Models\OnboardingSuporteSolicitacao::ASSUNTOS;
$whatsapp = static function($telefone){
    $numero = preg_replace('/\D/', '', (string) $telefone);
    if(strlen($numero) === 10 || strlen($numero) === 11){
        $numero = '55' . $numero;
    }
    return $numero;
};
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Solicitações de ajuda no onboarding</h3>
        <div class="card-tools">
            <?php foreach([null=>'Todas','aberta'=>'Abertas','em_atendimento'=>'Em atendimento','concluida'=>'Concluídas','cancelada'=>'Canceladas'] as $chave=>$label){ ?>
                <a class="btn btn-sm <?= $statusFiltro === $chave ? 'btn-primary' : 'btn-outline-primary'; ?>"
                   href="<?= BASE_URL; ?>/index.php?url=onboardingSuporteAdmin<?= $chave ? '&status=' . $e($chave) : ''; ?>">
                    <?= $e($label); ?>
                </a>
            <?php } ?>
        </div>
    </div>

    <div class="card-body table-responsive">
        <table class="table table-bordered table-striped table-hover datatable">
            <thead>
                <tr>
                    <th>Solicitação</th>
                    <th>Cliente</th>
                    <th>WhatsApp / etapa</th>
                    <th>Pedido</th>
                    <th>Preferência</th>
                    <th>Situação</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($solicitacoes as $s){
                $status = $statusLabel[$s['OSS_Status']] ?? [$s['OSS_Status'],'secondary'];
                $numeroContato = $whatsapp($s['CLI_Telefone'] ?? '');
                $mensagem = 'Olá, ' . ($s['CLI_Nome'] ?? '') . '. Recebemos sua solicitação de suporte no onboarding do Disparador.net e estamos entrando em contato para ajudar.';
            ?>
                <tr>
                    <td>
                        <strong>#<?= (int) $s['OSS_ID']; ?></strong><br>
                        <small><?= $data($s['OSS_CriadaEm']); ?></small>
                    </td>
                    <td>
                        <?= $e($s['CLI_Nome']); ?><br>
                        <small class="text-muted"><?= $e($s['CLI_Email']); ?></small>
                    </td>
                    <td>
                        <?php if(!empty($s['MTA_ID'])){ ?>
                            <?= $e(($s['MTA_Nome'] ?: 'Conta Meta') . ' · ' . ($s['MTA_NumeroTelefone'] ?: 'sem número')); ?><br>
                        <?php }else{ ?>
                            <span class="text-muted">Antes da seleção/conexão de uma conta</span><br>
                        <?php } ?>
                        <small><code><?= $e($s['OSS_Etapa']); ?></code></small>
                    </td>
                    <td style="min-width:260px">
                        <strong><?= $e($assuntos[$s['OSS_Assunto']] ?? $s['OSS_Assunto']); ?></strong>
                        <?php if(!empty($s['OSS_Descricao'])){ ?><p class="small mb-0 mt-1"><?= nl2br($e($s['OSS_Descricao'])); ?></p><?php } ?>
                    </td>
                    <td>
                        <?= $e($periodos[$s['OSS_PeriodoPreferido']] ?? $s['OSS_PeriodoPreferido']); ?>
                        <?php if(!empty($s['OSS_HorarioDetalhe'])){ ?><br><small><?= $e($s['OSS_HorarioDetalhe']); ?></small><?php } ?>
                    </td>
                    <td>
                        <span class="badge badge-<?= $e($status[1]); ?>"><?= $e($status[0]); ?></span>
                        <?php if(!empty($s['AdminNome'])){ ?><br><small><?= $e($s['AdminNome']); ?></small><?php } ?>
                    </td>
                    <td style="min-width:190px">
                        <?php if($numeroContato){ ?>
                            <a class="btn btn-sm btn-outline-success mb-1" target="_blank" rel="noopener noreferrer"
                               href="<?= $e('https://wa.me/' . $numeroContato . '?text=' . rawurlencode($mensagem)); ?>">
                                <i class="fab fa-whatsapp"></i> WhatsApp
                            </a>
                        <?php } ?>

                        <?php
                        $acoes = [];
                        if($s['OSS_Status'] === 'aberta') $acoes = ['em_atendimento'=>'Iniciar atendimento','cancelada'=>'Cancelar'];
                        elseif($s['OSS_Status'] === 'em_atendimento') $acoes = ['concluida'=>'Concluir','cancelada'=>'Cancelar'];
                        else $acoes = ['aberta'=>'Reabrir'];
                        foreach($acoes as $novoStatus=>$label){
                        ?>
                            <form method="post" action="<?= BASE_URL; ?>/index.php?url=onboardingSuporteAdmin/alterarStatus" class="d-inline">
                                <?= \Core\Csrf::input(); ?>
                                <input type="hidden" name="id" value="<?= (int) $s['OSS_ID']; ?>">
                                <input type="hidden" name="status" value="<?= $e($novoStatus); ?>">
                                <button class="btn btn-sm btn-outline-secondary mb-1"><?= $e($label); ?></button>
                            </form>
                        <?php } ?>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<script>
$(function(){
    $('.datatable').DataTable({
        language:{url:'//cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json'},
        order:[],
        pageLength:25
    });
});
</script>
