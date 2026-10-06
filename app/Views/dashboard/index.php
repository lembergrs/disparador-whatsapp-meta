<?php

function formatarTelefoneDashboard($telefone)
{
    $telefone = preg_replace('/\D/', '', $telefone);

    if(substr($telefone, 0, 2) == '55'){
        $telefone = substr($telefone, 2);
    }

    if(strlen($telefone) == 11){
        return '(' . substr($telefone, 0, 2) . ') '
            . substr($telefone, 2, 5)
            . '-'
            . substr($telefone, 7);
    }

    if(strlen($telefone) == 10){
        return '(' . substr($telefone, 0, 2) . ') '
            . substr($telefone, 2, 4)
            . '-'
            . substr($telefone, 6);
    }

    return $telefone;
}

function formatarDataDashboard($data)
{
    if(!$data){
        return '-';
    }

    return date('d/m/Y H:i', strtotime($data));
}

function avisoMetaDashboard($metaConta)
{
    return \Services\MetaService::avisoDesatualizacaoMeta($metaConta['MTA_UltimaVerificacao'] ?? null);
}

?>

<?php if($usuario['nivel'] == 'admin'){ ?>
<?php
$da = $dashboardAdmin ?: [];
$resumoAdmin = $da['resumo'] ?? [];
$funilAdmin = $da['funil'] ?? [];
$funilAtivacao30 = $da['funilAtivacao30'] ?? [];
$clientesAtivacao30 = $da['clientesAtivacao30'] ?? [];
$situacaoAdmin = $da['situacao'] ?? [];
$cadastrosAdmin = $da['cadastros'] ?? ['labels'=>[],'valores'=>[]];
$pagamentosAdmin = $da['pagamentos'] ?? ['labels'=>[],'valores'=>[]];
$assinaturasAdmin = $da['assinaturas'] ?? ['labels'=>[],'novas'=>[],'canceladas'=>[]];
$totalFunil = max(1, (int)($funilAdmin['cadastros'] ?? 0));
$totalFunilAtivacao30 = max(1, (int)($funilAtivacao30['cadastros'] ?? 0));
$totalSituacao = max(1, array_sum($situacaoAdmin));
$variacaoCadastros = (int)($resumoAdmin['novosAnterior'] ?? 0) > 0
    ? round((((int)($resumoAdmin['novos30'] ?? 0) - (int)$resumoAdmin['novosAnterior']) / (int)$resumoAdmin['novosAnterior']) * 100)
    : null;
?>
<style>
.admin-kpi .small-box{min-height:126px;border-radius:.45rem;box-shadow:0 2px 8px rgba(0,0,0,.08)}
.admin-kpi .small-box .inner h3{font-size:1.8rem;margin-bottom:.15rem}.admin-kpi .small-box .inner p{margin-bottom:.25rem}
.admin-kpi .comparativo{font-size:.78rem;opacity:.9}.admin-card{border-radius:.45rem;box-shadow:0 2px 8px rgba(0,0,0,.06)}
.funil-admin{display:flex;gap:6px;overflow-x:auto}.funil-etapa{flex:1;min-width:130px;text-align:center;padding:18px 10px;border-radius:.4rem;background:#eef5ff}
.funil-etapa:nth-child(2){background:#e8f4ff}.funil-etapa:nth-child(3){background:#e8f8ef}.funil-etapa:nth-child(4){background:#fff4d8}.funil-etapa:nth-child(5){background:#ffeadc}.funil-etapa:nth-child(6){background:#ffe1e5}
.funil-etapa strong{display:block;font-size:1.55rem}.funil-etapa small{display:block;color:#6c757d}
.funil-ativacao .funil-etapa{min-width:120px;padding:14px 8px}.etapa-ok{color:#28a745}.etapa-nao{color:#ced4da}.ativacao-scroll{max-height:590px;overflow:auto}.table-ativacao{margin-bottom:0}.table-ativacao th{text-align:center;white-space:nowrap;position:sticky;top:0;z-index:2;background:#fff;box-shadow:0 1px 0 #dee2e6}.table-ativacao th:first-child,.table-ativacao td:first-child{text-align:left}.table-ativacao td{text-align:center;white-space:nowrap;height:54px;vertical-align:middle}.cliente-ativacao{cursor:pointer}.cliente-ativacao:hover strong{text-decoration:underline}
.situacao-item{display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #eee;padding:7px 0}.situacao-item:last-child{border:0}
.situacao-dot{display:inline-block;width:10px;height:10px;border-radius:50%;margin-right:7px}.chart-admin{width:100%;height:220px;display:block}
.table-admin td,.table-admin th{vertical-align:middle;font-size:.88rem}.badge-admin{font-size:.75rem;padding:.35rem .5rem}
</style>

<div class="d-flex justify-content-between align-items-start mb-3">
    <div><h4 class="mb-0">Visão administrativa</h4><small class="text-muted">Crescimento, integração e conversão dos clientes do Disparador.</small></div>
    <span class="badge badge-light border p-2">Indicadores: últimos 30 dias</span>
</div>

<div class="row admin-kpi">
<?php
$cardsAdmin = [
 ['valor'=>number_format((int)($resumoAdmin['novos30'] ?? 0),0,',','.'),'titulo'=>'Novos cadastros','icone'=>'fas fa-user-plus','cor'=>'bg-primary'],
 ['valor'=>number_format((int)($resumoAdmin['metaConectadas'] ?? 0),0,',','.'),'titulo'=>'Contas Meta conectadas','icone'=>'fab fa-whatsapp','cor'=>'bg-success'],
 ['valor'=>number_format((int)($resumoAdmin['emAvaliacao'] ?? 0),0,',','.'),'titulo'=>'Em avaliação','icone'=>'fas fa-clock','cor'=>'bg-warning'],
 ['valor'=>number_format((int)($resumoAdmin['assinaturasAtivas'] ?? 0),0,',','.'),'titulo'=>'Assinaturas ativas','icone'=>'fas fa-check-circle','cor'=>'bg-purple'],
 ['valor'=>number_format((int)($resumoAdmin['novosPagantes30'] ?? 0),0,',','.'),'titulo'=>'Novos pagantes','icone'=>'fas fa-dollar-sign','cor'=>'bg-danger'],
 ['valor'=>'R$ '.number_format((float)($resumoAdmin['mrr'] ?? 0),2,',','.'),'titulo'=>'Receita mensal (MRR)','icone'=>'fas fa-chart-bar','cor'=>'bg-info']
];
foreach($cardsAdmin as $i=>$card){ ?>
<div class="col-xl-2 col-lg-4 col-md-6 col-12">
 <div class="small-box <?= $card['cor']; ?>"><div class="inner"><h3><?= $card['valor']; ?></h3><p><?= $card['titulo']; ?></p>
 <?php if($i===0 && $variacaoCadastros !== null){ ?><span class="comparativo"><?= $variacaoCadastros >= 0 ? '+' : ''; ?><?= $variacaoCadastros; ?>% vs. 30 dias anteriores</span><?php } ?>
 </div><div class="icon"><i class="<?= $card['icone']; ?>"></i></div></div>
</div>
<?php } ?>
</div>

<div class="row">
 <div class="col-lg-8"><div class="card admin-card h-100"><div class="card-header"><h3 class="card-title"><i class="fas fa-filter text-primary mr-2"></i>Funil de integração de clientes</h3></div><div class="card-body">
  <div class="funil-admin">
  <?php foreach([
   'cadastros'=>'Cadastros','contasCriadas'=>'Contas criadas','metaConectada'=>'Meta conectada','avaliacao'=>'Avaliação iniciada','pagamento'=>'Primeiro pagamento','assinaturaAtiva'=>'Assinatura ativa'
  ] as $chave=>$rotulo){ $v=(int)($funilAdmin[$chave]??0); ?>
   <div class="funil-etapa"><strong><?= number_format($v,0,',','.'); ?></strong><span><?= $rotulo; ?></span><small><?= round(($v/$totalFunil)*100); ?>% dos cadastros</small></div>
  <?php } ?>
  </div>
 </div></div></div>
 <div class="col-lg-4 mt-3 mt-lg-0"><div class="card admin-card h-100"><div class="card-header"><h3 class="card-title"><i class="fas fa-users text-info mr-2"></i>Situação dos clientes</h3></div><div class="card-body">
 <?php foreach(['ativa'=>['Assinatura ativa','#28a745'],'avaliacao'=>['Em avaliação','#ffc107'],'meta'=>['Meta conectada / pré-trial','#007bff'],'aguardandoMeta'=>['Aguardando Meta','#6cb2eb'],'pendente'=>['Cadastro pendente','#adb5bd'],'inativo'=>['Inativo / suspenso','#dc3545']] as $k=>$cfg){$v=(int)($situacaoAdmin[$k]??0); ?>
  <div class="situacao-item"><span><i class="situacao-dot" style="background:<?= $cfg[1]; ?>"></i><?= $cfg[0]; ?></span><strong><?= $v; ?> <small class="text-muted">(<?= round($v/$totalSituacao*100); ?>%)</small></strong></div>
 <?php } ?>
 </div></div></div>
</div>

<div class="card admin-card mt-3">
 <div class="card-header d-flex align-items-center"><h3 class="card-title"><i class="fas fa-route text-success mr-2"></i>Funil de ativação — clientes cadastrados nos últimos 30 dias</h3></div>
 <div class="card-body">
  <p class="text-muted small mb-3">A mesma coorte de clientes é acompanhada até a etapa mais avançada alcançada. Os percentuais usam os cadastros dos últimos 30 dias como base.</p>
  <div class="funil-admin funil-ativacao">
  <?php foreach([
   'cadastros'=>'Cadastro','metaConectada'=>'WhatsApp conectado','pagamentoMeta'=>'Pagamento Meta OK','templateCriado'=>'Template criado',
   'templateAprovado'=>'Template aprovado','primeiroEnvio'=>'1º envio entregue','contratacao'=>'Iniciou contratação','pagamento'=>'1º pagamento'
  ] as $chave=>$rotulo){ $v=(int)($funilAtivacao30[$chave]??0); ?>
   <div class="funil-etapa"><strong><?= number_format($v,0,',','.'); ?></strong><span><?= $rotulo; ?></span><small><?= round(($v/$totalFunilAtivacao30)*100); ?>% dos cadastros</small></div>
  <?php } ?>
  </div>
 </div>
</div>

<div class="card admin-card mt-3">
 <div class="card-header"><h3 class="card-title"><i class="fas fa-search text-primary mr-2"></i>Diagnóstico da ativação — últimos 30 dias</h3></div>
 <div class="card-body p-0">
  <div class="ativacao-scroll">
  <table class="table table-hover table-admin table-ativacao">
   <thead><tr><th>Cliente</th><th>Cadastro</th><th title="WhatsApp conectado">WhatsApp</th><th title="Pagamento da Meta confirmado pelo cliente">Pgto. Meta</th><th>Template</th><th>Aprovado</th><th>1º envio</th><th>Contratação</th><th>Pagamento</th></tr></thead>
   <tbody>
   <?php if(empty($clientesAtivacao30)){ ?>
    <tr><td colspan="9" class="text-center text-muted py-4">Nenhum cliente cadastrado nos últimos 30 dias.</td></tr>
   <?php }else{ foreach($clientesAtivacao30 as $cli){ ?>
    <tr class="cliente-ativacao" data-toggle="modal" data-target="#modalDiagnosticoAtivacao"
     data-cliente="<?= htmlspecialchars($cli['CLI_NomeFantasia'] ?: $cli['CLI_Nome'], ENT_QUOTES, 'UTF-8'); ?>"
     data-id="<?= (int)$cli['CLI_ID']; ?>"
     data-cadastro="<?= date('d/m/Y H:i',strtotime($cli['CLI_DataCadastro'])); ?>"
     data-email="<?= htmlspecialchars($cli['CLI_Email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
     data-telefone="<?= htmlspecialchars($cli['CLI_Telefone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
     data-status-cadastro="<?= htmlspecialchars($cli['CLI_StatusCadastro'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
     data-status-pagamento="<?= htmlspecialchars($cli['CLI_StatusPagamento'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
     data-proxima-etapa="<?= htmlspecialchars($cli['proxima_etapa'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
     data-acao="<?= htmlspecialchars($cli['acao_sugerida'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
     <td><strong><?= htmlspecialchars($cli['CLI_NomeFantasia'] ?: $cli['CLI_Nome']); ?></strong><br><small class="text-muted">#<?= (int)$cli['CLI_ID']; ?> · clique para diagnosticar</small></td>
     <td><?= date('d/m/Y',strtotime($cli['CLI_DataCadastro'])); ?></td>
     <?php foreach(['meta_conectada','pagamento_meta','template_criado','template_aprovado','primeiro_envio','contratacao','pagamento'] as $etapa){ $ok=!empty($cli[$etapa]); ?>
      <td><i class="fas <?= $ok?'fa-check-circle etapa-ok':'fa-minus-circle etapa-nao'; ?>" title="<?= $ok?'Etapa alcançada':'Sem evidência desta etapa'; ?>"></i></td>
     <?php } ?>
    </tr>
   <?php }} ?>
   </tbody>
  </table>
  </div>
 </div>
</div>

<div class="modal fade" id="modalDiagnosticoAtivacao" tabindex="-1" role="dialog" aria-hidden="true">
 <div class="modal-dialog modal-lg" role="document"><div class="modal-content">
  <div class="modal-header"><div><h5 class="modal-title mb-0" id="diagClienteNome">Diagnóstico do cliente</h5><small class="text-muted" id="diagClienteId"></small></div><button type="button" class="close" data-dismiss="modal" aria-label="Fechar"><span aria-hidden="true">&times;</span></button></div>
  <div class="modal-body">
   <div class="row">
    <div class="col-md-6"><p class="mb-1"><strong>Cadastro:</strong> <span id="diagCadastro"></span></p><p class="mb-1"><strong>E-mail:</strong> <span id="diagEmail"></span></p><p class="mb-1"><strong>Telefone:</strong> <span id="diagTelefone"></span></p></div>
    <div class="col-md-6"><p class="mb-1"><strong>Status do cadastro:</strong> <span id="diagStatusCadastro"></span></p><p class="mb-1"><strong>Status financeiro:</strong> <span id="diagStatusPagamento"></span></p></div>
   </div>
   <hr>
   <div class="alert alert-info mb-2"><strong>Próxima etapa esperada:</strong> <span id="diagProximaEtapa"></span></div>
   <p class="mb-0"><strong>Ação sugerida:</strong> <span id="diagAcao"></span></p>
  </div>
  <div class="modal-footer"><a href="<?= BASE_URL; ?>/index.php?url=cliente" class="btn btn-outline-secondary btn-sm"><i class="fas fa-users mr-1"></i>Gerenciar clientes</a><button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Fechar</button></div>
 </div></div>
</div>

<div class="row mt-3">
 <div class="col-lg-6"><div class="card admin-card"><div class="card-header"><h3 class="card-title">Evolução de cadastros — 30 dias</h3></div><div class="card-body"><canvas id="chartCadastrosAdmin" class="chart-admin"></canvas></div></div></div>
 <div class="col-lg-6"><div class="card admin-card"><div class="card-header"><h3 class="card-title">Cadastros x primeiros pagamentos — 30 dias</h3></div><div class="card-body"><canvas id="chartConversaoAdmin" class="chart-admin"></canvas></div></div></div>
</div>
<div class="row">
 <div class="col-lg-6"><div class="card admin-card"><div class="card-header"><h3 class="card-title">Novas assinaturas x cancelamentos — 6 meses</h3></div><div class="card-body"><canvas id="chartAssinaturasAdmin" class="chart-admin"></canvas><small class="text-muted">Cancelamentos usam a última atualização da assinatura como referência.</small></div></div></div>
 <div class="col-lg-6"><div class="card admin-card"><div class="card-header"><h3 class="card-title">Leitura dos indicadores</h3></div><div class="card-body">
  <p class="mb-2"><strong>MRR:</strong> valor mensal equivalente das assinaturas ativas; ciclos trimestral, semestral e anual são rateados por mês.</p>
  <p class="mb-2"><strong>Novo pagante:</strong> cliente cujo primeiro pagamento de mensalidade foi confirmado nos últimos 30 dias.</p>
  <p class="mb-0"><strong>Meta conectada:</strong> cliente com conta Meta ativa e status <code>conectado</code>. O sistema ainda não possui uma data histórica imutável da conclusão da conexão.</p>
 </div></div></div>
</div>

<div class="row">
 <div class="col-xl-7"><div class="card admin-card"><div class="card-header"><h3 class="card-title">Últimos clientes cadastrados</h3></div><div class="card-body table-responsive p-0"><table class="table table-hover table-admin mb-0"><thead><tr><th>Cliente</th><th>Cadastro</th><th>Meta</th><th>Situação</th><th>Plano</th></tr></thead><tbody>
 <?php foreach(($da['ultimosClientes']??[]) as $cli){
  $sit='Cadastro ativo';$badge='badge-secondary';
  if(($cli['assinatura_status']??'')==='ativa'){$sit='Assinatura ativa';$badge='badge-success';}
  elseif(($cli['CLI_StatusCadastro']??'')==='pendente'){$sit='Cadastro pendente';$badge='badge-secondary';}
  elseif(!empty($cli['CLI_DataLiberacao']) && ($cli['CLI_StatusPagamento']??'')==='pendente'){$sit='Em avaliação';$badge='badge-warning';}
  elseif(empty($cli['meta_conectada'])){$sit='Aguardando Meta';$badge='badge-info';}
 ?>
 <tr><td><strong><?= htmlspecialchars($cli['CLI_NomeFantasia'] ?: $cli['CLI_Nome']); ?></strong><br><small class="text-muted">#<?= (int)$cli['CLI_ID']; ?></small></td><td><?= date('d/m/Y H:i',strtotime($cli['CLI_DataCadastro'])); ?></td><td><?= !empty($cli['meta_conectada'])?'<span class="badge badge-success">Conectada</span>':'<span class="badge badge-light border">Não conectada</span>'; ?></td><td><span class="badge <?= $badge; ?> badge-admin"><?= $sit; ?></span></td><td><?= htmlspecialchars($cli['plano'] ?: '-'); ?></td></tr>
 <?php } ?>
 </tbody></table></div></div></div>
 <div class="col-xl-5"><div class="card admin-card"><div class="card-header"><h3 class="card-title">Contas Meta recentes</h3></div><div class="card-body table-responsive p-0"><table class="table table-hover table-admin mb-0"><thead><tr><th>Cliente</th><th>Número</th><th>Criada em</th><th>Status</th></tr></thead><tbody>
 <?php foreach(($da['contasMetaRecentes']??[]) as $meta){ ?>
 <tr><td><?= htmlspecialchars($meta['CLI_Nome']); ?></td><td><?= htmlspecialchars(formatarTelefoneDashboard($meta['MTA_NumeroTelefone'])); ?></td><td><?= date('d/m/Y H:i',strtotime($meta['MTA_DataCadastro'])); ?></td><td><span class="badge <?= $meta['MTA_Status']==='conectado'?'badge-success':'badge-warning'; ?>"><?= htmlspecialchars($meta['MTA_Status']); ?></span></td></tr>
 <?php } ?>
 </tbody></table></div></div></div>
</div>

<script>
(function(){
 $('#modalDiagnosticoAtivacao').on('show.bs.modal', function(event){
  var row=$(event.relatedTarget);
  $('#diagClienteNome').text(row.data('cliente') || 'Cliente');
  $('#diagClienteId').text('#' + (row.data('id') || ''));
  $('#diagCadastro').text(row.data('cadastro') || '-');
  $('#diagEmail').text(row.data('email') || '-');
  $('#diagTelefone').text(row.data('telefone') || '-');
  $('#diagStatusCadastro').text(row.data('status-cadastro') || '-');
  $('#diagStatusPagamento').text(row.data('status-pagamento') || '-');
  $('#diagProximaEtapa').text(row.data('proxima-etapa') || '-');
  $('#diagAcao').text(row.data('acao') || '-');
 });
 function desenhar(id, labels, series){
  var canvas=document.getElementById(id); if(!canvas||!canvas.getContext)return;
  var ratio=window.devicePixelRatio||1,w=canvas.clientWidth||600,h=220;canvas.width=w*ratio;canvas.height=h*ratio;
  var ctx=canvas.getContext('2d');ctx.scale(ratio,ratio);ctx.clearRect(0,0,w,h);
  var pad={l:34,r:12,t:16,b:30},cw=w-pad.l-pad.r,ch=h-pad.t-pad.b,max=1;
  series.forEach(function(s){s.data.forEach(function(v){max=Math.max(max,Number(v)||0);});});
  ctx.font='11px Arial';ctx.fillStyle='#6c757d';ctx.strokeStyle='#e9ecef';ctx.lineWidth=1;
  for(var g=0;g<=4;g++){var y=pad.t+ch*g/4;ctx.beginPath();ctx.moveTo(pad.l,y);ctx.lineTo(w-pad.r,y);ctx.stroke();ctx.fillText(String(Math.round(max*(4-g)/4)),3,y+4);}
  var n=Math.max(1,labels.length-1);
  series.forEach(function(s,si){ctx.strokeStyle=s.color;ctx.fillStyle=s.color;ctx.lineWidth=2;ctx.beginPath();s.data.forEach(function(v,i){var x=pad.l+cw*(i/n),y=pad.t+ch-(Number(v)||0)/max*ch;if(i===0)ctx.moveTo(x,y);else ctx.lineTo(x,y);});ctx.stroke();});
  var passo=Math.max(1,Math.ceil(labels.length/6));ctx.fillStyle='#6c757d';labels.forEach(function(l,i){if(i%passo===0||i===labels.length-1){var x=pad.l+cw*(i/n);ctx.fillText(l,Math.max(pad.l-4,Math.min(x-12,w-38)),h-8);}});
  var lx=pad.l;series.forEach(function(s){ctx.fillStyle=s.color;ctx.fillRect(lx,pad.t-11,10,3);ctx.fillStyle='#495057';ctx.fillText(s.nome,lx+14,pad.t-6);lx+=ctx.measureText(s.nome).width+42;});
 }
 var cad=<?= json_encode($cadastrosAdmin, JSON_UNESCAPED_UNICODE); ?>;
 var pag=<?= json_encode($pagamentosAdmin, JSON_UNESCAPED_UNICODE); ?>;
 var ass=<?= json_encode($assinaturasAdmin, JSON_UNESCAPED_UNICODE); ?>;
 function render(){desenhar('chartCadastrosAdmin',cad.labels,[{nome:'Novos cadastros',data:cad.valores,color:'#007bff'}]);desenhar('chartConversaoAdmin',cad.labels,[{nome:'Cadastros',data:cad.valores,color:'#007bff'},{nome:'Primeiros pagamentos',data:pag.valores,color:'#28a745'}]);desenhar('chartAssinaturasAdmin',ass.labels,[{nome:'Novas',data:ass.novas,color:'#007bff'},{nome:'Canceladas',data:ass.canceladas,color:'#dc3545'}]);}
 render();window.addEventListener('resize',render);
})();
</script>

<?php }else{ ?>

<?php
$clienteEmPreTrialDashboard = !empty($onboardingChecklist['pre_trial']);
$mostrarOnboardingDashboard = empty($onboardingChecklist['concluido']) || !empty($onboardingChecklist['recuperacao']);
?>
<div class="d-flex flex-wrap justify-content-between align-items-start mb-3">
    <div>
        <h4 class="mb-0">Olá, <?= htmlspecialchars($usuario['nome'] ?? ''); ?>!</h4>
        <small class="text-muted">Aqui está um resumo da sua conta no Disparador.</small>
    </div>
    <?php if($ultimoAcessoCliente){ ?>
        <small class="text-muted mt-1"><i class="far fa-clock mr-1"></i>Último acesso: <?= date('d/m/Y \\à\\s H:i', strtotime($ultimoAcessoCliente)); ?></small>
    <?php } ?>
</div>
<?php
if($mostrarOnboardingDashboard){
    require __DIR__ . '/_onboarding.php';
}
require __DIR__ . '/_cliente_resumo.php';
?>
<?php } ?>
