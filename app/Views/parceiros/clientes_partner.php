<?php use Core\Session; ?>
<?php
$webhookEventos=json_decode((string)($parceiro['PAR_WebhookEventos']??'[]'),true);
if(!is_array($webhookEventos)) $webhookEventos=[];
$eventosDisponiveis=[
    'message.received'=>'Mensagem recebida',
    'message.sent'=>'Mensagem enviada',
    'message.delivered'=>'Mensagem entregue',
    'message.read'=>'Mensagem lida',
    'message.failed'=>'Falha no envio',
    'message.reaction'=>'Reação'
];
?>
<?php if(!empty($novoConvite)){ ?><div class="alert alert-success"><strong>Link do convite:</strong><div class="input-group mt-2"><input id="partnerInviteUrl" class="form-control" readonly value="<?= htmlspecialchars($novoConvite,ENT_QUOTES,'UTF-8') ?>"><div class="input-group-append"><button type="button" class="btn btn-outline-dark" onclick="navigator.clipboard.writeText(document.getElementById('partnerInviteUrl').value)">Copiar</button></div></div><small>Este link é exibido após a geração. O token não é armazenado em texto puro.</small></div><?php } ?>
<?php if(!empty($novoWebhookSecret)){ ?><div class="alert alert-warning"><strong>Copie o segredo do webhook agora.</strong><div class="input-group mt-2"><input id="partnerWebhookSecret" class="form-control" readonly value="<?= htmlspecialchars($novoWebhookSecret,ENT_QUOTES,'UTF-8') ?>"><div class="input-group-append"><button type="button" class="btn btn-outline-dark" onclick="navigator.clipboard.writeText(document.getElementById('partnerWebhookSecret').value)">Copiar</button></div></div><small>Use este segredo para validar a assinatura HMAC dos eventos enviados pelo Disparador.</small></div><?php } ?>
<div class="card"><div class="card-header"><h3 class="card-title">Webhook da integração</h3></div><div class="card-body">
<p class="text-muted">Informe um único endpoint da sua plataforma. Os eventos de todos os seus clientes autorizados serão enviados para esta URL identificados por cliente e canal.</p>
<form method="post" action="<?= BASE_URL ?>/index.php?url=parceiroClientes/salvarWebhook"><?= \Core\Csrf::input() ?>
<div class="form-group"><label>URL do webhook</label><input type="url" name="webhook_url" class="form-control" maxlength="500" placeholder="https://api.exemplo.com/webhooks/disparador" value="<?= htmlspecialchars($parceiro['PAR_WebhookUrl']??'',ENT_QUOTES,'UTF-8') ?>"></div>
<div class="form-group"><div class="custom-control custom-switch"><input type="checkbox" class="custom-control-input" id="partnerWebhookAtivo" name="webhook_ativo" value="1" <?= ($parceiro['PAR_WebhookAtivo']??'N')==='S'?'checked':'' ?>><label class="custom-control-label" for="partnerWebhookAtivo">Webhook ativo</label></div></div>
<div class="form-group"><label>Eventos</label><div class="row"><?php foreach($eventosDisponiveis as $codigo=>$rotulo){ ?><div class="col-md-4 mb-2"><div class="custom-control custom-checkbox"><input type="checkbox" class="custom-control-input" id="evt_<?= md5($codigo) ?>" name="eventos[]" value="<?= htmlspecialchars($codigo) ?>" <?= in_array($codigo,$webhookEventos,true)?'checked':'' ?>><label class="custom-control-label" for="evt_<?= md5($codigo) ?>"><?= htmlspecialchars($rotulo) ?></label></div></div><?php } ?></div></div>
<div class="form-group"><div class="custom-control custom-checkbox"><input type="checkbox" class="custom-control-input" id="regenerarWebhookSecret" name="regenerar_segredo" value="1"><label class="custom-control-label" for="regenerarWebhookSecret">Gerar/regenerar segredo de assinatura</label></div><small class="text-muted">Ao regenerar, atualize o segredo na sua plataforma antes de depender das novas entregas.</small></div>
<button class="btn btn-primary"><i class="fas fa-save mr-1"></i> Salvar webhook</button>
</form>
</div></div>
<div class="card"><div class="card-header"><h3 class="card-title">Últimas entregas do webhook</h3></div><div class="card-body table-responsive">
<table class="table table-sm table-bordered"><thead><tr><th>Evento</th><th>Tipo</th><th>Status</th><th>Tentativas</th><th>HTTP</th><th>Criado</th><th>Entregue</th><th>Erro</th></tr></thead><tbody>
<?php foreach(($webhookEntregas??[]) as $e){ ?><tr><td><code><?= htmlspecialchars($e['PWE_EventId']) ?></code></td><td><?= htmlspecialchars($e['PWE_Tipo']) ?></td><td><?= htmlspecialchars($e['PWE_Status']) ?></td><td><?= (int)$e['PWE_Tentativas'] ?></td><td><?= $e['PWE_UltimoHttpStatus']!==null?(int)$e['PWE_UltimoHttpStatus']:'—' ?></td><td><?= htmlspecialchars($e['PWE_CriadoEm']) ?></td><td><?= htmlspecialchars($e['PWE_EntregueEm']??'—') ?></td><td><?= htmlspecialchars($e['PWE_UltimoErro']??'') ?></td></tr><?php } ?>
<?php if(empty($webhookEntregas)){ ?><tr><td colspan="8" class="text-muted text-center">Nenhuma entrega registrada.</td></tr><?php } ?>
</tbody></table>
<?php if(($webhookTotal??0)>0){ ?>
<div class="d-flex flex-wrap justify-content-between align-items-center mt-3">
<small class="text-muted"><?= (int)$webhookTotal ?> entrega(s) registrada(s) · 10 por página</small>
<?php if(($webhookPaginas??1)>1){ ?>
<nav aria-label="Paginação das entregas do webhook"><ul class="pagination pagination-sm mb-0">
<li class="page-item <?= ($webhookPagina??1)<=1?'disabled':'' ?>"><a class="page-link" href="<?= BASE_URL ?>/index.php?url=parceiroClientes&webhook_page=<?= max(1,($webhookPagina??1)-1) ?>">Anterior</a></li>
<?php
$inicio=max(1,($webhookPagina??1)-2);
$fim=min(($webhookPaginas??1),($webhookPagina??1)+2);
for($pagina=$inicio;$pagina<=$fim;$pagina++){ ?>
<li class="page-item <?= $pagina===($webhookPagina??1)?'active':'' ?>"><a class="page-link" href="<?= BASE_URL ?>/index.php?url=parceiroClientes&webhook_page=<?= $pagina ?>"><?= $pagina ?></a></li>
<?php } ?>
<li class="page-item <?= ($webhookPagina??1)>=($webhookPaginas??1)?'disabled':'' ?>"><a class="page-link" href="<?= BASE_URL ?>/index.php?url=parceiroClientes&webhook_page=<?= min(($webhookPaginas??1),($webhookPagina??1)+1) ?>">Próxima</a></li>
</ul></nav>
<?php } ?>
</div>
<?php } ?>
</div></div>
<div class="card"><div class="card-header"><h3 class="card-title">Convidar cliente</h3></div><div class="card-body">
<p class="text-muted">Gere um link para o cliente cadastrar a própria empresa. O cadastro não gera cobrança; o cliente passa a ser faturável somente após conectar e ativar o WhatsApp.</p>
<form method="post" action="<?= BASE_URL ?>/index.php?url=parceiroClientes/gerarConvite" class="form-inline"><?= \Core\Csrf::input() ?><input name="nome_referencia" class="form-control mr-2" maxlength="150" placeholder="Identificação opcional do cliente"><button class="btn btn-primary"><i class="fas fa-link mr-1"></i> Gerar link</button></form>
</div></div>
<div class="card"><div class="card-header"><h3 class="card-title">Clientes cadastrados</h3></div><div class="card-body table-responsive"><table class="table table-bordered table-sm"><thead><tr><th>Cliente</th><th>E-mail</th><th>Cadastro</th><th>Canais vinculados</th><th>Ações</th></tr></thead><tbody>
<?php foreach($clientesPartner as $c){ ?><tr><td><?= htmlspecialchars($c['CLI_Nome']) ?></td><td><?= htmlspecialchars($c['CLI_Email']) ?></td><td><?= htmlspecialchars($c['PCI_AceitoEm']) ?></td><td><?= (int)$c['total_canais'] ?></td><td><?php if((int)$c['total_canais']>0){ ?><a class="btn btn-sm btn-outline-primary" href="<?= BASE_URL ?>/index.php?url=parceiroTemplates&cliente_id=<?= (int)$c['CLI_ID'] ?>"><i class="fas fa-file-alt mr-1"></i> Templates</a><?php }else{ ?><span class="text-muted">Conecte um WhatsApp</span><?php } ?></td></tr><?php } ?>
<?php if(!$clientesPartner){ ?><tr><td colspan="5" class="text-muted text-center">Nenhum cliente concluiu o cadastro ainda.</td></tr><?php } ?>
</tbody></table></div></div>
<div class="card"><div class="card-header"><h3 class="card-title">Convites</h3></div><div class="card-body table-responsive"><table class="table table-bordered table-sm"><thead><tr><th>Referência</th><th>Status</th><th>Expira em</th><th>Cliente</th></tr></thead><tbody>
<?php foreach($convites as $i){ ?><tr><td><?= htmlspecialchars($i['PCI_NomeReferencia']??'') ?></td><td><?= htmlspecialchars($i['PCI_Status']) ?></td><td><?= htmlspecialchars($i['PCI_ExpiraEm']) ?></td><td><?= htmlspecialchars($i['CLI_Nome']??'—') ?></td></tr><?php } ?>
</tbody></table></div></div>
