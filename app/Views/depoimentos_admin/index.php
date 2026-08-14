<?php use Core\Csrf; $e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); ?>
<div class="card"><div class="card-header"><h3 class="card-title">Moderação de depoimentos</h3></div><div class="card-body table-responsive p-0">
<table class="table table-hover"><thead><tr><th>Status</th><th>Cliente de origem</th><th>Identificação pública</th><th>Depoimento</th><th>Enviado em</th><th>Ações</th></tr></thead><tbody>
<?php foreach($depoimentos as $item){ ?><tr>
<td><span class="badge badge-<?= $item['DEP_Status']==='aprovado'?'success':($item['DEP_Status']==='pendente'?'warning':'secondary'); ?>"><?= $e($item['DEP_Status']); ?><?= $item['DEP_Ativo']==='N'?' / inativo':''; ?></span></td>
<td><?= $e($item['ClienteNome']); ?><br><small class="text-muted"><?= $e($item['ClienteEmail']); ?></small></td>
<td><strong><?= $e($item['DEP_NomeExibido']); ?></strong><br><?= $e($item['DEP_Empresa']); ?><?= $item['DEP_Cargo'] ? '<br><small>'.$e($item['DEP_Cargo']).'</small>' : ''; ?></td>
<td style="min-width:280px"><?= nl2br($e($item['DEP_Depoimento'])); ?></td><td><?= $e($item['DEP_EnviadoEm']); ?></td><td class="text-nowrap">
<?php if($item['DEP_Status']==='pendente'){ ?>
<form class="d-inline" method="post" action="<?= BASE_URL; ?>/index.php?url=depoimentoAdmin/aprovar"><?= Csrf::input(); ?><input type="hidden" name="id" value="<?= (int)$item['DEP_ID']; ?>"><button class="btn btn-sm btn-success">Aprovar</button></form>
<form class="d-inline" method="post" action="<?= BASE_URL; ?>/index.php?url=depoimentoAdmin/rejeitar"><?= Csrf::input(); ?><input type="hidden" name="id" value="<?= (int)$item['DEP_ID']; ?>"><button class="btn btn-sm btn-outline-danger">Rejeitar</button></form>
<?php } elseif($item['DEP_Status']==='aprovado' && $item['DEP_Ativo']==='S'){ ?>
<form method="post" action="<?= BASE_URL; ?>/index.php?url=depoimentoAdmin/desativar"><?= Csrf::input(); ?><input type="hidden" name="id" value="<?= (int)$item['DEP_ID']; ?>"><button class="btn btn-sm btn-outline-warning">Desativar</button></form>
<?php } ?>
</td></tr><?php } ?>
<?php if(empty($depoimentos)){ ?><tr><td colspan="6" class="text-center text-muted">Nenhum depoimento recebido.</td></tr><?php } ?>
</tbody></table></div></div>
