<div class="card">
<div class="card-header"><h3 class="card-title">Segurança / Auditoria da Partner API</h3></div>
<div class="card-body">
<form method="get" action="<?= BASE_URL ?>/index.php" class="form-row mb-3">
<input type="hidden" name="url" value="parceiroAdmin/auditoria">
<div class="form-group col-md-3"><label>Partner</label><select name="parceiro_id" class="form-control"><option value="">Todos</option><?php foreach($parceiros as $p){ ?><option value="<?= (int)$p['PAR_ID'] ?>" <?= (int)$filtros['parceiro_id']===(int)$p['PAR_ID']?'selected':'' ?>><?= htmlspecialchars($p['PAR_Nome']) ?></option><?php } ?></select></div>
<div class="form-group col-md-2"><label>Severidade</label><select name="severidade" class="form-control"><option value="">Todas</option><?php foreach(['info','warning','security'] as $s){ ?><option value="<?= $s ?>" <?= $filtros['severidade']===$s?'selected':'' ?>><?= $s ?></option><?php } ?></select></div>
<div class="form-group col-md-2"><label>Evento</label><input name="evento" class="form-control" value="<?= htmlspecialchars($filtros['evento']) ?>"></div>
<div class="form-group col-md-2"><label>De</label><input type="date" name="data_inicio" class="form-control" value="<?= htmlspecialchars($filtros['data_inicio']) ?>"></div>
<div class="form-group col-md-2"><label>Até</label><input type="date" name="data_fim" class="form-control" value="<?= htmlspecialchars($filtros['data_fim']) ?>"></div>
<div class="form-group col-md-1 d-flex align-items-end"><button class="btn btn-primary">Filtrar</button></div>
</form>
<p class="small text-muted">Últimos 200 registros (máximo 500 por consulta). Payloads são sanitizados e limitados; credenciais e segredos não são armazenados.</p>
<div class="table-responsive"><table class="table table-sm table-bordered"><thead><tr><th>Data/hora</th><th>Partner / chave</th><th>Severidade</th><th>Evento</th><th>HTTP</th><th>Endpoint</th><th>IP</th><th>Erro</th><th>Payload</th></tr></thead><tbody>
<?php foreach($registros as $r){ ?><tr>
<td><?= htmlspecialchars($r['PAA_DataHora']) ?></td>
<td><?= htmlspecialchars($r['PAR_Nome']??('#'.(int)$r['PAR_ID'])) ?><br><small><?= htmlspecialchars(($r['PAK_Nome']??'').' '.($r['PAK_Prefixo']??'')) ?></small></td>
<td><span class="badge badge-<?= $r['PAA_Severidade']==='security'?'danger':($r['PAA_Severidade']==='warning'?'warning':'info') ?>"><?= htmlspecialchars($r['PAA_Severidade']) ?></span></td>
<td><?= htmlspecialchars($r['PAA_Evento']) ?></td><td><?= (int)$r['PAA_HttpStatus'] ?></td>
<td><?= htmlspecialchars(trim(($r['PAA_Metodo']??'').' '.($r['PAA_Endpoint']??''))) ?></td><td><?= htmlspecialchars($r['PAA_Ip']??'') ?></td>
<td><code><?= htmlspecialchars($r['PAA_ErroCodigo']??'') ?></code><br><small><?= htmlspecialchars($r['PAA_ErroMensagem']??'') ?></small></td>
<td><?php if(!empty($r['PAA_Payload'])){ ?><details><summary>Ver<?= $r['PAA_PayloadTruncado']==='S'?' (truncado)':'' ?></summary><pre class="small mb-0" style="max-width:420px;white-space:pre-wrap"><?= htmlspecialchars($r['PAA_Payload']) ?></pre></details><?php } ?></td>
</tr><?php } ?>
<?php if(!$registros){ ?><tr><td colspan="9" class="text-center text-muted">Nenhuma ocorrência encontrada.</td></tr><?php } ?>
</tbody></table></div>
</div></div>
