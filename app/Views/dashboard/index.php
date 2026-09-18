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
$situacaoAdmin = $da['situacao'] ?? [];
$cadastrosAdmin = $da['cadastros'] ?? ['labels'=>[],'valores'=>[]];
$pagamentosAdmin = $da['pagamentos'] ?? ['labels'=>[],'valores'=>[]];
$assinaturasAdmin = $da['assinaturas'] ?? ['labels'=>[],'novas'=>[],'canceladas'=>[]];
$totalFunil = max(1, (int)($funilAdmin['cadastros'] ?? 0));
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
$recolherInformacoes = empty($onboardingChecklist['concluido']);
require __DIR__ . '/_onboarding.php';
?>
<?php if($recolherInformacoes){ ?>
<details class="card dashboard-informacoes mb-4">
    <summary>Informações da conta e painel operacional</summary>
    <div class="dashboard-operacional">
<?php } ?>
<?php if($usuario['nivel'] != 'admin'){ ?>
<div class="row mb-3">
    <div class="col-md-6 mb-3">
        <div class="card card-outline card-primary h-100">
            <div class="card-header"><h3 class="card-title">Seu plano no Disparador</h3></div>
            <div class="card-body">
                <?php if($clienteEmPreTrialDashboard){ ?>
                    <p class="mb-1">Você está preparando seu período de avaliação.</p>
                <?php }else{ ?>
                    <p class="mb-1"><strong>Plano:</strong> <?= htmlspecialchars($cliente['PLA_Nome'] ?? 'Avaliação sem plano contratado'); ?></p>
                <?php } ?>
                <?php if(isset($cliente['PLA_LimiteMensagens'])){ ?>
                    <p class="mb-1"><strong>Mensagens incluídas:</strong> <?= number_format((int) $cliente['PLA_LimiteMensagens'], 0, ',', '.'); ?></p>
                <?php } ?>
                <?php if($consumo){ ?>
                    <p class="mb-1"><strong>Mensagens utilizadas:</strong> <?= number_format((int) ($consumo['CMS_Mensagens'] ?? 0), 0, ',', '.'); ?></p>
                <?php } ?>
                <p class="text-muted mb-0">Este limite faz parte do plano contratado no Disparador e considera as mensagens processadas pela plataforma.</p>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-3">
        <div class="card card-outline card-info h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title">Limite de conversas da Meta</h3>
                <button type="button" class="btn btn-tool text-info" data-toggle="modal" data-target="#modalEntendaLimites">Entenda os limites</button>
            </div>
            <div class="card-body">
                <?php if($metaConta && !empty($metaConta['MTA_MessagingLimit'])){ ?>
                    <p class="mb-1"><strong>Limite atual informado pela Meta:</strong><br><?= htmlspecialchars(\Services\MetaService::formatarLimiteConversasMeta($metaConta['MTA_MessagingLimit'])); ?></p>
                <?php }else{ ?>
                    <p class="mb-1"><strong>Limite atual informado pela Meta:</strong><br>Limite da Meta ainda não disponível.</p>
                    <p class="text-muted">Conclua a conexão do número ou aguarde a sincronização dos dados da Meta.</p>
                <?php } ?>
                <?php $qualidadeDashboard = ['GREEN'=>'Boa', 'YELLOW'=>'Média', 'RED'=>'Baixa'][$metaConta['MTA_QualityRating'] ?? ''] ?? 'Informação de qualidade ainda não disponível.'; ?>
                <p class="mb-1"><strong>Qualidade:</strong> <?= htmlspecialchars($qualidadeDashboard); ?></p>
                <p class="mb-1"><strong>Situação do WhatsApp:</strong> <?= !empty($onboardingChecklist['conectado']) ? 'Conectado' : 'Conexão ainda não concluída'; ?></p>
                <?php if(!empty($onboardingChecklist['conectado'])){ ?>
                <p class="mb-1"><strong>Última consulta à Meta:</strong> <?= !empty($metaConta['MTA_UltimaVerificacao']) ? date('d/m/Y \à\s H:i', strtotime($metaConta['MTA_UltimaVerificacao'])) : 'Aguardando atualização'; ?></p>
                <?php } ?>
                <?php $avisoMeta = !empty($onboardingChecklist['conectado']) ? \Services\MetaService::avisoDesatualizacaoMeta($metaConta['MTA_UltimaVerificacao'] ?? null) : null; ?>
                <?php if($avisoMeta){ ?><div class="alert alert-warning py-2"><?= htmlspecialchars($avisoMeta); ?></div><?php } ?>
                <p class="text-muted mb-1">Este limite é definido e controlado exclusivamente pela Meta. O Disparador não consegue aumentá-lo, alterá-lo ou garantir quando ele será ampliado.</p>
                <p class="text-muted mb-0">A Meta pode alterar esse limite conforme seus próprios critérios, como situação da empresa, qualidade das conversas e histórico de uso.</p>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEntendaLimites" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Entenda os limites do Disparador e da Meta</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
        <div class="modal-body">
            <p>O limite do plano Disparador corresponde à quantidade de mensagens incluídas no plano contratado e processadas pela plataforma.</p>
            <p>O limite de conversas da Meta corresponde à quantidade de clientes únicos com os quais a empresa pode iniciar novas conversas dentro de uma janela contínua de 24 horas, conforme as regras e informações fornecidas pela própria Meta.</p>
            <p>O Disparador apenas consulta e exibe esse dado. A definição, atualização e ampliação do limite são de responsabilidade exclusiva da Meta.</p>
            <p>Ter mensagens disponíveis no plano Disparador não garante que a Meta permitirá iniciar novas conversas acima do limite definido por ela.</p>
            <div class="row"><div class="col-md-6"><h6>Limite do plano Disparador</h6><ul><li>definido pelo plano contratado;</li><li>baseado em mensagens;</li><li>controlado pelo Disparador;</li><li>ciclo comercial do plano.</li></ul></div><div class="col-md-6"><h6>Limite de conversas da Meta</h6><ul><li>definido pela Meta;</li><li>relacionado a clientes únicos;</li><li>janela contínua de 24 horas;</li><li>não pode ser alterado pelo Disparador.</li></ul></div></div>
        </div>
    </div></div>
</div>

<?php } ?>

<div class="row">

    <div class="col-lg-3 col-6">
        <div class="small-box bg-info">
            <div class="inner">
                <h3><?= number_format($conversas, 0, ',', '.'); ?></h3>
                <p>Conversas</p>
            </div>
            <div class="icon">
                <i class="fas fa-comments"></i>
            </div>
            <a href="<?= BASE_URL; ?>/index.php?url=conversa" class="small-box-footer">
                Abrir conversas <i class="fas fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>

    <div class="col-lg-3 col-6">
        <div class="small-box bg-danger">
            <div class="inner">
                <h3><?= number_format($naoLidas, 0, ',', '.'); ?></h3>
                <p>Não lidas</p>
            </div>
            <div class="icon">
                <i class="fas fa-envelope"></i>
            </div>
            <a href="<?= BASE_URL; ?>/index.php?url=conversa" class="small-box-footer">
                Ver pendências <i class="fas fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>

    <div class="col-lg-3 col-6">
        <div class="small-box bg-success">
            <div class="inner">
                <h3><?= number_format($contatos, 0, ',', '.'); ?></h3>
                <p>Contatos</p>
            </div>
            <div class="icon">
                <i class="fas fa-address-book"></i>
            </div>
            <a href="<?= BASE_URL; ?>/index.php?url=listaContato" class="small-box-footer">
                Ver listas <i class="fas fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>

    <div class="col-lg-3 col-6">
        <div class="small-box bg-warning">
            <div class="inner">
                <h3><?= number_format($campanhas, 0, ',', '.'); ?></h3>
                <p>Campanhas</p>
            </div>
            <div class="icon">
                <i class="fas fa-bullhorn"></i>
            </div>
            <a href="<?= BASE_URL; ?>/index.php?url=campanha" class="small-box-footer">
                Ver campanhas <i class="fas fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>

</div>

<div class="row">

    <div class="col-md-3">

        <div class="card">

            <div class="card-header">

                <h3 class="card-title">
                    Meu Plano
                </h3>

            </div>

            <div class="card-body">

                <?php if(!empty($cliente['PLA_Nome'])){ ?>

                    <h5>
                        <?= htmlspecialchars($cliente['PLA_Nome']); ?>
                    </h5>

                    <p class="mb-1">
                        <strong>Valor:</strong>
                        <h4 class="text-success">
                        R$ <?= number_format(
                            $cliente['PLA_Valor'],
                            2,
                            ',',
                            '.'
                        ); ?>
                        </h4>
                    </p>

                    <p class="mb-1">
                        <strong>Números:</strong>
                        <?= $cliente['PLA_LimiteNumeros']; ?>
                    </p>

                    <p class="mb-1">
                        <strong>Usuários:</strong>
                        <?= $cliente['PLA_LimiteUsuarios']; ?>
                    </p>

                    <p class="mb-1">
                        <strong>Limite Mensal:</strong>
                        <?= number_format(
                            $cliente['PLA_LimiteMensagens'],
                            0,
                            ',',
                            '.'
                        ); ?> mensagens
                    </p>

                    <?php

                    $mensagensUtilizadas =
                        (int)($consumo['CMS_Mensagens'] ?? 0);

                    $limiteMensagens =
                        (int)$cliente['PLA_LimiteMensagens'];

                    $percentualUso = 0;

                    if($limiteMensagens > 0){

                        $percentualUso =
                            min(
                                100,
                                round(
                                    (
                                        $mensagensUtilizadas
                                        / $limiteMensagens
                                    ) * 100
                                )
                            );
                    }

                    $corBarra = 'success';

                    if($percentualUso >= 80){
                        $corBarra = 'warning';
                    }

                    if($percentualUso >= 100){
                        $corBarra = 'danger';
                    }

                    ?>

                    <hr>

                    <p class="mb-1">

                        <strong>Uso Mensal</strong>

                    </p>

                    <p class="mb-2">

                        <?= number_format(
                            $mensagensUtilizadas,
                            0,
                            ',',
                            '.'
                        ); ?>

                        /

                        <?= number_format(
                            $limiteMensagens,
                            0,
                            ',',
                            '.'
                        ); ?>

                        mensagens

                    </p>

                    <div class="progress mb-2">

                        <div
                        class="progress-bar bg-<?= $corBarra; ?>"
                        style="width: <?= $percentualUso; ?>%;"
                        >

                            <?= $percentualUso; ?>%

                        </div>

                    </div>

                    <?php if($percentualUso >= 100){ ?>

                        <div class="alert alert-danger py-2">

                            Limite mensal atingido.

                        </div>

                    <?php }elseif($percentualUso >= 90){ ?>

                        <div class="alert alert-danger py-2">

                            Atenção: mais de 90% do plano utilizado.

                        </div>

                    <?php }elseif($percentualUso >= 80){ ?>

                        <div class="alert alert-warning py-2">

                            Atenção: mais de 80% do plano utilizado.

                        </div>

                    <?php } ?>

                    <?php if(
                        !empty($excedente)
                        &&
                        $excedente['EXC_Mensagens'] > 0
                    ){ ?>

                        <hr>

                        <p class="mb-1">

                            <strong>
                                Excedente Atual
                            </strong>

                        </p>

                        <p class="mb-1">

                            <?= number_format(
                                $excedente['EXC_Mensagens'],
                                0,
                                ',',
                                '.'
                            ); ?>

                            mensagens

                        </p>

                        <p class="mb-0">

                            R$
                            <?= number_format(
                                $excedente['EXC_ValorTotal'],
                                2,
                                ',',
                                '.'
                            ); ?>

                        </p>

                    <?php } ?>

                    <p class="mb-0">

                        <strong>Status:</strong>

                        <?php if(
                            $cliente['CLI_StatusPagamento']
                            == 'pago'
                        ){ ?>

                            <span class="badge badge-success">
                                Ativo
                            </span>

                        <?php }else{ ?>

                            <span class="badge badge-warning">
                                Pendente
                            </span>

                        <?php } ?>

                    </p>

                <?php }else{ ?>

                    <div class="alert alert-warning mb-0">

                        <?= $clienteEmPreTrialDashboard ? 'Você está preparando seu período de avaliação.' : 'Nenhum plano contratado.'; ?>

                    </div>

                <?php } ?>

            </div>

        </div>

    </div>

    <div class="col-md-3">

        <div class="card">

            <div class="card-header">
                <h3 class="card-title">
                    Conta Meta
                </h3>
            </div>

            <div class="card-body">

                <?php if($usuario['nivel'] == 'admin'){ ?>

                    <p class="text-muted mb-0">
                        Visão administrativa geral.
                    </p>

                <?php }elseif($metaConta){ ?>

                    <h5>
                        <?= htmlspecialchars($metaConta['MTA_Nome']); ?>
                    </h5>

                    <p class="mb-1">
                        <strong>Número:</strong>
                        <?= formatarTelefoneDashboard($metaConta['MTA_NumeroTelefone']); ?>
                    </p>

                    <p class="mb-0">
                        <strong>Status:</strong>

                        <?php if($metaConta['MTA_Status'] == 'conectado'){ ?>

                            <span class="badge badge-success">
                                Conectada
                            </span>

                        <?php }else{ ?>

                            <span class="badge badge-danger">
                                Conexão precisa de atenção
                            </span>

                        <?php } ?>

                    </p>

                <?php }else{ ?>

                    <div class="alert alert-warning mb-0">
                        Nenhuma conta Meta cadastrada.
                    </div>

                <?php } ?>

            </div>

        </div>

    </div>

    <div class="col-md-6">

        <div class="card">

            <div class="card-header">
                <h3 class="card-title">
                    Últimas Campanhas
                </h3>
            </div>

            <div class="card-body table-responsive p-0">

                <table class="table table-hover table-striped mb-0">

                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Status</th>
                            <th>Agendamento</th>
                            <th>Contatos</th>
                            <th>Enviados</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if(empty($ultimasCampanhas)){ ?>

                        <tr>
                            <td colspan="5" class="text-center text-muted">
                                Nenhuma campanha encontrada.
                            </td>
                        </tr>

                    <?php } ?>

                    <?php foreach($ultimasCampanhas as $campanha){ ?>

                        <tr>
                            <td>
                                <?= htmlspecialchars($campanha['CAM_Nome']); ?>
                            </td>

                            <td>
                                <span class="badge badge-secondary">
                                    <?= htmlspecialchars($campanha['CAM_Status']); ?>
                                </span>
                            </td>

                            <td>
                                <?= formatarDataDashboard($campanha['CAM_DataAgendamento']); ?>
                            </td>

                            <td>
                                <?= number_format($campanha['CAM_TotalContatos'], 0, ',', '.'); ?>
                            </td>

                            <td>
                                <?= number_format($campanha['CAM_TotalEnviados'], 0, ',', '.'); ?>
                            </td>
                        </tr>

                    <?php } ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>
<?php if($recolherInformacoes){ ?></div></details><?php } ?>
<?php } ?>
