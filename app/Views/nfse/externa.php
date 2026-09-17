<?php
use Core\Csrf;
$e = function($v){ return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
?>
<div class="card card-warning">
    <div class="card-header"><h3 class="card-title">Registrar NFS-e emitida externamente</h3></div>
    <div class="card-body">
        <div class="alert alert-warning">
            Use esta operação somente quando a NFS-e desta cobrança já tiver sido emitida fora do Disparador. O sistema consultará a chave no ambiente nacional antes de alterar o histórico.
        </div>
        <dl class="row">
            <dt class="col-sm-3">Tentativa atual</dt><dd class="col-sm-9">#<?= (int) ($emissao['NFE_ID'] ?? 0); ?></dd>
            <dt class="col-sm-3">Cobrança</dt><dd class="col-sm-9">#<?= (int) ($emissao['COB_ID'] ?? 0); ?></dd>
            <dt class="col-sm-3">Status</dt><dd class="col-sm-9"><?= $e($emissao['NFE_Status'] ?? ''); ?></dd>
            <dt class="col-sm-3">DPS reservada</dt><dd class="col-sm-9">Série <?= $e($emissao['NFE_Serie'] ?? '-'); ?> / nº <?= $e($emissao['NFE_NumDps'] ?? '-'); ?></dd>
        </dl>
        <form method="post" action="<?= BASE_URL; ?>/index.php?url=nfse/registrarExterna">
            <?= Csrf::input(); ?>
            <input type="hidden" name="nfse_id" value="<?= (int) ($emissao['NFE_ID'] ?? 0); ?>">
            <div class="form-group">
                <label for="chave_acesso">Chave de acesso da NFS-e emitida externamente</label>
                <input type="text" class="form-control" id="chave_acesso" name="chave_acesso" inputmode="numeric" maxlength="50" pattern="[0-9]{50}" required autocomplete="off">
                <small class="form-text text-muted">50 dígitos. Nenhum registro será substituído se a consulta ou as validações do XML oficial falharem.</small>
            </div>
            <a href="<?= BASE_URL; ?>/index.php?url=nfse" class="btn btn-secondary">Voltar</a>
            <button type="submit" class="btn btn-warning" onclick="return confirm('Confirmar a consulta e, somente se a nota oficial for validada, encerrar a tentativa automática anterior e vincular a NFS-e externa?');">Consultar e reconciliar</button>
        </form>
    </div>
</div>
