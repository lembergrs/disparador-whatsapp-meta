<?php
$clienteId=(int)($clientePartner['CLI_ID']??0);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
 <div><h4 class="mb-1"><?= htmlspecialchars($clientePartner['CLI_Nome']??'',ENT_QUOTES,'UTF-8') ?></h4><small class="text-muted">Templates gerenciados exclusivamente no contexto deste cliente.</small></div>
 <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/index.php?url=parceiroClientes"><i class="fas fa-arrow-left mr-1"></i> Clientes Partner</a>
</div>
<div class="card"><div class="card-header"><h3 class="card-title">Canal WhatsApp</h3></div><div class="card-body">
<form method="get" action="<?= BASE_URL ?>/index.php"><input type="hidden" name="url" value="parceiroTemplates"><input type="hidden" name="cliente_id" value="<?= $clienteId ?>"><div class="form-row align-items-end"><div class="col-md-8"><label>Conta do cliente</label><select class="form-control" name="meta" onchange="this.form.submit()"><option value="">Selecione</option><?php foreach($canaisPartner as $c){ ?><option value="<?= (int)$c['MTA_ID'] ?>" <?= (int)$metaSelecionada===(int)$c['MTA_ID']?'selected':'' ?>><?= htmlspecialchars(($c['MTA_Nome']?:'WhatsApp').' — '.($c['MTA_NumeroTelefone']?:'sem número'),ENT_QUOTES,'UTF-8') ?></option><?php } ?></select></div></div></form>
</div></div>
<?php if($metaSelecionada){ ?>
<div class="card"><div class="card-header"><h3 class="card-title">Gerenciar templates</h3></div><div class="card-body">
<div class="alert alert-light border"><strong>Isolamento ativo:</strong> todas as operações abaixo usam somente o cliente e o canal selecionados.</div>
<form method="post" action="<?= BASE_URL ?>/index.php?url=parceiroTemplates/sincronizar" class="d-inline"><?= \Core\Csrf::input() ?><input type="hidden" name="cliente_id" value="<?= $clienteId ?>"><input type="hidden" name="meta" value="<?= (int)$metaSelecionada ?>"><button class="btn btn-outline-primary mb-3"><i class="fas fa-sync mr-1"></i> Sincronizar com a Meta</button></form>
<div class="table-responsive"><table class="table table-bordered table-sm"><thead><tr><th>Nome</th><th>Idioma</th><th>Categoria</th><th>Status</th><th>Ações</th></tr></thead><tbody>
<?php foreach($templates as $t){ ?><tr><td><?= htmlspecialchars($t['TMP_Nome'],ENT_QUOTES,'UTF-8') ?></td><td><?= htmlspecialchars($t['TMP_Idioma'],ENT_QUOTES,'UTF-8') ?></td><td><?= htmlspecialchars($t['TMP_Categoria'],ENT_QUOTES,'UTF-8') ?></td><td><?= htmlspecialchars($t['TMP_Status'],ENT_QUOTES,'UTF-8') ?></td><td><form method="post" action="<?= BASE_URL ?>/index.php?url=parceiroTemplates/inativar" onsubmit="return confirm('Remover este template da listagem?')"><?= \Core\Csrf::input() ?><input type="hidden" name="cliente_id" value="<?= $clienteId ?>"><input type="hidden" name="id" value="<?= (int)$t['TMP_ID'] ?>"><button class="btn btn-sm btn-outline-danger">Remover</button></form></td></tr><?php } ?>
<?php if(!$templates){ ?><tr><td colspan="5" class="text-center text-muted">Nenhum template encontrado neste canal.</td></tr><?php } ?>
</tbody></table></div>
<hr><h5>Criar template para este cliente</h5>
<p class="text-muted small">A criação será enviada à Meta usando exclusivamente a conta selecionada acima.</p>
<form method="post" enctype="multipart/form-data" action="<?= BASE_URL ?>/index.php?url=parceiroTemplates/criar"><?= \Core\Csrf::input() ?><input type="hidden" name="cliente_id" value="<?= $clienteId ?>"><input type="hidden" name="meta" value="<?= (int)$metaSelecionada ?>">
<div class="form-row"><div class="form-group col-md-4"><label>Nome</label><input class="form-control" name="nome" required></div><div class="form-group col-md-4"><label>Categoria</label><select class="form-control" name="categoria" required><option value="UTILITY">Utilidade</option><option value="MARKETING">Marketing</option><option value="AUTHENTICATION">Autenticação</option></select></div><div class="form-group col-md-4"><label>Idioma</label><input class="form-control" name="idioma" value="pt_BR" required></div></div>
<div class="form-group"><label>Texto</label><textarea class="form-control" name="corpo" rows="4" required></textarea></div>
<button class="btn btn-primary">Enviar para aprovação</button></form>
</div></div>
<?php } ?>
