<?php use Core\Csrf; $e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); ?>
<div class="row"><div class="col-lg-7">
<div class="card card-success"><div class="card-header"><h3 class="card-title">Conte sua experiência</h3></div><div class="card-body">
<p class="text-muted">Seu depoimento será analisado antes de ser publicado.</p>
<form method="post" action="<?= BASE_URL; ?>/index.php?url=depoimento/enviar" data-analytics-event="testimonials_submission">
<?= Csrf::input(); ?>
<div class="form-group"><label for="depNome">Nome que deseja exibir</label><input id="depNome" class="form-control" name="nome_exibido" maxlength="120" required></div>
<div class="form-group"><label for="depEmpresa">Empresa</label><input id="depEmpresa" class="form-control" name="empresa" maxlength="160" required></div>
<div class="form-group"><label for="depCargo">Cargo <span class="text-muted">(opcional)</span></label><input id="depCargo" class="form-control" name="cargo" maxlength="120"></div>
<div class="form-group"><label for="depTexto">Depoimento</label><textarea id="depTexto" class="form-control" name="depoimento" rows="6" maxlength="1000" required></textarea><small class="form-text text-muted">Até 1.000 caracteres.</small></div>
<div class="custom-control custom-checkbox mb-3"><input class="custom-control-input" id="depAutorizacao" name="autorizacao" value="S" type="checkbox" required><label class="custom-control-label" for="depAutorizacao">Autorizo o Disparador.net a publicar este depoimento com meu nome, empresa e cargo informados.</label></div>
<button class="btn btn-success"><i class="fas fa-paper-plane mr-1"></i> Enviar para análise</button>
</form></div></div></div>
<div class="col-lg-5"><div class="card"><div class="card-header"><h3 class="card-title">Meus envios</h3></div><div class="card-body">
<?php if(empty($depoimentos)){ ?><p class="text-muted mb-0">Você ainda não enviou um depoimento.</p><?php } ?>
<?php foreach($depoimentos as $item){ $badge = ['pendente'=>'warning','aprovado'=>'success','rejeitado'=>'secondary'][$item['DEP_Status']] ?? 'secondary'; ?>
<div class="border-bottom pb-3 mb-3"><span class="badge badge-<?= $badge; ?>"><?= $e($item['DEP_Status']); ?></span><p class="mt-2 mb-1"><?= $e($item['DEP_Depoimento']); ?></p><small class="text-muted"><?= $e($item['DEP_Empresa']); ?></small></div>
<?php } ?>
</div></div></div></div>
