<div class="alert alert-info"><strong>Cadastro self-service:</strong> envie ao futuro parceiro o link <code><?= rtrim(BASE_URL,'/') ?>/index.php?url=parceiroCadastro</code>. O cadastro entra como pendente e precisa ser aprovado aqui antes da liberação de API keys.</div>
<div class="card">
<div class="card-header"><h3 class="card-title">Parceiros da API</h3></div>
<div class="card-body">
<form method="post" action="<?= BASE_URL ?>/index.php?url=parceiroAdmin/salvar" class="row">
<?= \Core\Csrf::input(); ?>
<div class="form-group col-md-4"><label>Cadastro do parceiro</label><select name="cliente_id" class="form-control" required><option value="">Selecione</option><?php foreach($clientes as $c){ if(($c['CLI_TipoConta']??'')!=='cliente_partner') continue; ?><option value="<?= (int)$c['CLI_ID'] ?>"><?= htmlspecialchars($c['CLI_Nome']) ?> (#<?= (int)$c['CLI_ID'] ?>)</option><?php } ?></select></div>
<div class="form-group col-md-3"><label>Nome da integração</label><input name="nome" class="form-control" required></div>
<div class="form-group col-md-2"><label>Identificador</label><input name="identificador" class="form-control" placeholder="ex.: zain" required></div>
<div class="form-group col-md-3"><label>Webhook <small>(opcional)</small></label><input name="webhook_url" type="url" class="form-control"></div>
<div class="col-12"><button class="btn btn-primary"><i class="fas fa-plus mr-1"></i>Cadastrar parceiro</button></div>
</form>
<hr>
<table class="table table-bordered table-hover"><thead><tr><th>Parceiro</th><th>Cadastro</th><th>Canais</th><th>Chaves</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach($parceiros as $p){ ?><tr><td><?= htmlspecialchars($p['PAR_Nome']) ?><br><small><?= htmlspecialchars($p['PAR_Identificador']) ?></small></td><td><?= htmlspecialchars($p['CLI_Nome']) ?> (#<?= (int)$p['CLI_ID'] ?>)</td><td><?= (int)$p['total_canais'] ?></td><td><?= (int)$p['total_chaves'] ?></td><td><?= $p['PAR_Ativo']==='S'?'Ativo':'Inativo' ?></td><td><a class="btn btn-sm btn-outline-primary" href="<?= BASE_URL ?>/index.php?url=parceiroAdmin/detalhe&id=<?= (int)$p['PAR_ID'] ?>">Gerenciar</a></td></tr><?php } ?>
<?php if(empty($parceiros)){ ?><tr><td colspan="6" class="text-center text-muted">Nenhum parceiro cadastrado.</td></tr><?php } ?>
</tbody></table>
</div></div>