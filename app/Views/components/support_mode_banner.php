<?php if(\Core\Auth::isImpersonating()){ ?>
<?php $impersonacaoSuporte = \Core\Auth::impersonacao(); ?>
<div class="alert alert-warning d-flex flex-column flex-md-row align-items-md-center justify-content-between" role="status">
    <div class="mb-2 mb-md-0">
        <strong>MODO SUPORTE</strong> — Você está acessando a conta de
        <strong><?= htmlspecialchars($impersonacaoSuporte['cliente_nome'] ?? 'cliente', ENT_QUOTES, 'UTF-8'); ?></strong>
        como administrador.
    </div>
    <form method="post" action="<?= BASE_URL; ?>/index.php?url=suporte/encerrar" class="ml-md-3">
        <?= \Core\Csrf::input(); ?>
        <button type="submit" class="btn btn-dark btn-sm text-nowrap">
            <i class="fas fa-undo" aria-hidden="true"></i>
            Voltar para administrador
        </button>
    </form>
</div>
<?php } ?>
