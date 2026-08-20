<?php $h=function($v){ return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }; ?>

<div class="card card-outline card-primary">
    <div class="card-header"><h3 class="card-title">Filtros do relatório</h3></div>
    <div class="card-body">
        <form method="get" action="<?= BASE_URL; ?>/index.php" class="row align-items-end">
            <input type="hidden" name="url" value="metaPricingReport">
            <div class="col-lg-2 col-md-4 form-group"><label>Data inicial</label><input type="date" name="data_inicial" class="form-control" value="<?= $h($filtros['data_inicial']); ?>" required></div>
            <div class="col-lg-2 col-md-4 form-group"><label>Data final</label><input type="date" name="data_final" class="form-control" value="<?= $h($filtros['data_final']); ?>" required></div>
            <div class="col-lg-2 col-md-4 form-group"><label>Cliente</label><select name="cliente_id" class="form-control"><option value="0">Todos</option><?php foreach($opcoes['clientes'] as $cliente){ ?><option value="<?= (int)$cliente['CLI_ID']; ?>" <?= (int)$filtros['cliente_id']===(int)$cliente['CLI_ID']?'selected':''; ?>><?= $h($cliente['nome'].' (#'.$cliente['CLI_ID'].')'); ?></option><?php } ?></select></div>
            <div class="col-lg-2 col-md-4 form-group"><label>Conta Meta</label><select name="meta_id" class="form-control"><option value="0">Todas</option><?php foreach($opcoes['contas'] as $conta){ $rotulo=$conta['cliente'].' — '.$conta['MTA_Nome'].(!empty($conta['MTA_NumeroTelefone'])?' — '.$conta['MTA_NumeroTelefone']:''); ?><option value="<?= (int)$conta['MTA_ID']; ?>" <?= (int)$filtros['meta_id']===(int)$conta['MTA_ID']?'selected':''; ?>><?= $h($rotulo); ?></option><?php } ?></select></div>
            <div class="col-lg-2 col-md-4 form-group"><label>Categoria</label><select name="categoria" class="form-control"><option value="">Todas</option><option value="__null__" <?= $filtros['categoria']==='__null__'?'selected':''; ?>>Sem categoria informada</option><?php foreach($categorias as $categoria){ ?><option value="<?= $h($categoria); ?>" <?= $filtros['categoria']===$categoria?'selected':''; ?>><?= $h($categoria); ?></option><?php } ?></select></div>
            <div class="col-lg-2 col-md-4 form-group"><label>Billable</label><select name="billable" class="form-control"><option value="">Todos</option><option value="1" <?= $filtros['billable']==='1'?'selected':''; ?>>Faturáveis</option><option value="0" <?= $filtros['billable']==='0'?'selected':''; ?>>Não faturáveis</option><option value="null" <?= $filtros['billable']==='null'?'selected':''; ?>>Sem informação</option></select></div>
            <div class="col-12"><button class="btn btn-primary"><i class="fas fa-filter"></i> Aplicar filtros</button> <a class="btn btn-outline-secondary" href="<?= BASE_URL; ?>/index.php?url=metaPricingReport">Limpar</a></div>
        </form>
    </div>
</div>
<?php if(!empty($filtros['periodo_ajustado'])){ ?><div class="alert alert-info">O período foi ajustado para o limite máximo de 366 dias, preservando a data final solicitada.</div><?php } ?>

<div class="row">
<?php foreach([
    ['Mensagens Meta',$resumo['total'],'primary','fa-paper-plane'],['Com pricing',$resumo['com_pricing'],'info','fa-tags'],
    ['Faturáveis',$resumo['faturaveis'],'primary','fa-check-circle'],['Não faturáveis',$resumo['nao_faturaveis'],'info','fa-circle'],
    ['Sem informação de cobrança',$resumo['sem_informacao'],'secondary','fa-question-circle']
] as $card){ ?>
    <div class="col-xl col-md-4 col-6"><div class="small-box bg-<?= $card[2]; ?>"><div class="inner"><h3><?= number_format((int)$card[1],0,',','.'); ?></h3><p><?= $h($card[0]); ?></p></div><div class="icon"><i class="fas <?= $card[3]; ?>"></i></div></div></div>
<?php } ?>
</div>
<p class="text-muted">“Sem informação” significa que a Meta não informou ou que o dado não foi registrado. Não significa mensagem gratuita.</p>

<div class="row">
    <div class="col-lg-8"><div class="card card-outline card-primary"><div class="card-header"><h3 class="card-title">Resumo por categoria</h3></div><div class="card-body table-responsive p-0"><table class="table table-bordered table-striped table-sm mb-0"><thead><tr><th>Categoria</th><th>Total</th><th>Faturáveis</th><th>Não faturáveis</th><th>Sem informação</th></tr></thead><tbody><?php if(!$porCategoria){ ?><tr><td colspan="5" class="text-center text-muted">Nenhuma mensagem no período.</td></tr><?php } foreach($porCategoria as $linha){ ?><tr><td><?= $h($linha['Categoria']??'Sem categoria informada'); ?></td><td><?= number_format((int)$linha['total'],0,',','.'); ?></td><td><?= number_format((int)$linha['faturaveis'],0,',','.'); ?></td><td><?= number_format((int)$linha['nao_faturaveis'],0,',','.'); ?></td><td><?= number_format((int)$linha['sem_informacao'],0,',','.'); ?></td></tr><?php } ?></tbody></table></div></div></div>
    <div class="col-lg-4"><div class="card card-outline card-secondary"><div class="card-header"><h3 class="card-title">Resumo por Pricing Type</h3></div><div class="card-body table-responsive p-0"><table class="table table-bordered table-striped table-sm mb-0"><thead><tr><th>Pricing Type</th><th>Total</th></tr></thead><tbody><?php if(!$porPricingType){ ?><tr><td colspan="2" class="text-center text-muted">Nenhuma mensagem no período.</td></tr><?php } foreach($porPricingType as $linha){ ?><tr><td><?= $h($linha['PricingType']??'Não informado'); ?></td><td><?= number_format((int)$linha['total'],0,',','.'); ?></td></tr><?php } ?></tbody></table></div></div></div>
</div>

<div class="card card-outline card-primary"><div class="card-header"><h3 class="card-title">Mensagens deduplicadas</h3></div><div class="card-body"><div class="table-responsive"><table id="tabelaPricingMeta" class="table table-bordered table-striped table-sm w-100"><thead><tr><th>Data/Hora</th><th>Cliente</th><th>Conta Meta</th><th>Destino</th><th>Tipo</th><th>Categoria Meta</th><th>Billable</th><th>Pricing Model</th><th>Pricing Type</th><th>Market</th><th>Currency</th><th>Status</th><th>wamid</th></tr></thead><tbody></tbody></table></div></div></div>

<script>
$(function(){
    const filtros = <?= json_encode($filtros,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
    $('#tabelaPricingMeta').DataTable({
        processing:true, serverSide:true, pageLength:25, order:[[0,'desc']],
        ajax:{url:BASE_URL+'/index.php?url=metaPricingReport/dados',data:function(d){Object.assign(d,filtros);}},
        columns:[{data:'data'},{data:'cliente'},{data:'conta'},{data:'destino'},{data:'tipo'},{data:'categoria'},{data:'billable'},{data:'modelo'},{data:'pricing_type'},{data:'market'},{data:'currency'},{data:'status'},{data:'wamid'}],
        language:{url:'https://cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json'},
        drawCallback:function(){$('#tabelaPricingMeta [title]').tooltip({container:'body'});}
    });
});
</script>
