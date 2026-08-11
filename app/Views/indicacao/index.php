<?php
$e = function($valor){ return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8'); };
$data = function($valor){ return $valor ? date('d/m/Y', strtotime((string) $valor)) : '-'; };
$compartilhamento = $indicacao['compartilhamento'];
$resumo = $indicacao['resumo'];
?>

<div class="card card-outline card-success mb-3">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-share-alt mr-2"></i>Indique e Ganhe</h3>
    </div>
    <div class="card-body">
        <?php if(!empty($compartilhamento['disponivel'])){ ?>
            <p class="text-muted mb-4">Compartilhe seu código com outras empresas e acompanhe seus créditos promocionais.</p>
            <div class="row">
                <div class="col-lg-4 mb-3 mb-lg-0">
                    <small class="text-muted d-block">Seu código</small>
                    <strong class="h4 d-block mb-2"><?= $e($compartilhamento['codigo']); ?></strong>
                    <button type="button" class="btn btn-outline-success btn-sm btn-copiar" data-copiar="<?= $e($compartilhamento['codigo']); ?>"><i class="far fa-copy mr-1"></i>Copiar código</button>
                </div>
                <div class="col-lg-8">
                    <small class="text-muted d-block">Seu link de indicação</small>
                    <div class="input-group mb-2">
                        <input type="text" class="form-control" readonly value="<?= $e($compartilhamento['link']); ?>" aria-label="Seu link de indicação">
                        <div class="input-group-append"><button type="button" class="btn btn-outline-success btn-copiar" data-copiar="<?= $e($compartilhamento['link']); ?>"><i class="far fa-copy mr-1"></i>Copiar link</button></div>
                    </div>
                    <a class="btn btn-success btn-sm" target="_blank" rel="noopener noreferrer" href="https://wa.me/?text=<?= rawurlencode('Conheça o Disparador.net usando meu link: ' . $compartilhamento['link']); ?>"><i class="fab fa-whatsapp mr-1"></i>Compartilhar no WhatsApp</a>
                </div>
            </div>
        <?php }else{ ?>
            <div class="alert alert-info mb-0"><i class="fas fa-info-circle mr-1"></i><?= $e($compartilhamento['mensagem']); ?></div>
        <?php } ?>
    </div>
</div>

<div class="row">
    <?php foreach([
        ['Total de indicações', 'total_indicacoes', 'fas fa-user-friends', 'info'],
        ['Aguardando pagamento', 'aguardando_pagamento', 'fas fa-clock', 'warning'],
        ['Em confirmação', 'em_confirmacao', 'fas fa-hourglass-half', 'primary'],
        ['Indicações confirmadas', 'aprovadas', 'fas fa-check-circle', 'success'],
        ['Créditos disponíveis', 'creditos_disponiveis', 'fas fa-gift', 'success'],
        ['Créditos reservados', 'creditos_reservados', 'fas fa-lock', 'info'],
        ['Créditos utilizados', 'creditos_utilizados', 'fas fa-check', 'secondary']
    ] as [$tituloResumo, $chaveResumo, $iconeResumo, $corResumo]){ ?>
        <div class="col-6 col-md-3 col-xl mb-3">
            <div class="small-box bg-<?= $corResumo; ?> mb-0">
                <div class="inner"><h3><?= (int) $resumo[$chaveResumo]; ?></h3><p><?= $e($tituloResumo); ?></p></div>
                <div class="icon"><i class="<?= $e($iconeResumo); ?>"></i></div>
            </div>
        </div>
    <?php } ?>
</div>

<div class="card mb-3">
    <div class="card-header"><h3 class="card-title">Como funciona</h3></div>
    <div class="card-body">
        <p class="mb-2">Todo novo cliente recebe 50% de desconto na primeira cobrança, independentemente de indicação.</p>
        <p class="mb-0">Quando uma indicação é confirmada, ela gera crédito promocional de <?= !empty($compartilhamento['percentual']) ? $e($compartilhamento['percentual']) : '15'; ?>% para cobranças futuras elegíveis. A aplicação segue as regras vigentes do programa.</p>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><h3 class="card-title">Minhas indicações</h3></div>
    <div class="card-body p-0">
        <?php if(empty($indicacao['indicacoes'])){ ?>
            <div class="p-4 text-center text-muted"><i class="fas fa-user-friends fa-2x mb-2 d-block"></i>Ainda não há indicações registradas. Compartilhe seu código quando ele estiver disponível.</div>
        <?php }else{ ?>
            <div class="table-responsive"><table class="table table-striped table-hover mb-0"><thead><tr><th>Cliente indicado</th><th>Data</th><th>Situação</th><th>Crédito</th></tr></thead><tbody>
            <?php foreach($indicacao['indicacoes'] as $item){ ?><tr><td><?= $e($item['exibicao_nome']); ?></td><td><?= $e($data($item['IND_CadastradaEm'])); ?></td><td><span class="badge badge-<?= $e($item['badge_indicacao']); ?>"><?= $e($item['status_indicacao']); ?></span></td><td><span class="badge badge-<?= $e($item['badge_credito']); ?>"><?= $e($item['status_credito']); ?></span></td></tr><?php } ?>
            </tbody></table></div>
        <?php } ?>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3 class="card-title">Histórico de créditos</h3></div>
    <div class="card-body p-0">
        <?php if(empty($indicacao['creditos'])){ ?>
            <div class="p-4 text-center text-muted"><i class="fas fa-gift fa-2x mb-2 d-block"></i>Você ainda não possui créditos de indicação.</div>
        <?php }else{ ?>
            <div class="table-responsive"><table class="table table-striped table-hover mb-0"><thead><tr><th>Gerado em</th><th>Percentual</th><th>Status</th><th>Reserva/uso</th></tr></thead><tbody>
            <?php foreach($indicacao['creditos'] as $item){ $dataHistorico = $item['ICRR_UtilizadoEm'] ?: ($item['ICRR_ReservadoEm'] ?: '-'); ?><tr><td><?= $e($data($item['ICR_LiberadoEm'] ?: $item['ICR_CriadoEm'])); ?></td><td><?= $e($item['ICR_Percentual']); ?>%</td><td><span class="badge badge-<?= $e($item['badge']); ?>"><?= $e($item['status']); ?></span></td><td><?= $e($data($dataHistorico)); ?></td></tr><?php } ?>
            </tbody></table></div>
        <?php } ?>
    </div>
</div>

<script>
document.querySelectorAll('.btn-copiar').forEach(function(botao){
    botao.addEventListener('click', function(){
        var texto = botao.getAttribute('data-copiar') || '';
        var concluir = function(){ var original = botao.innerHTML; botao.innerHTML = '<i class="fas fa-check mr-1"></i>Copiado'; setTimeout(function(){ botao.innerHTML = original; }, 1800); };
        if(navigator.clipboard && window.isSecureContext){ navigator.clipboard.writeText(texto).then(concluir); return; }
        var campo = document.createElement('textarea'); campo.value = texto; campo.style.position = 'fixed'; campo.style.opacity = '0'; document.body.appendChild(campo); campo.select();
        try{ document.execCommand('copy'); concluir(); }finally{ document.body.removeChild(campo); }
    });
});
</script>
