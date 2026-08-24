<?php

$diagnosticoMeta = \Services\MetaHealthService::consultarConta($conta);
$canSendMeta = strtoupper((string) ($diagnosticoMeta['can_send_message'] ?? ''));
$errosMeta = $diagnosticoMeta['erros'] ?? [];

$statusMetaLabel = [
    'AVAILABLE' => ['classe' => 'success', 'texto' => 'Disponível'],
    'LIMITED' => ['classe' => 'warning', 'texto' => 'Limitado'],
    'BLOCKED' => ['classe' => 'danger', 'texto' => 'Bloqueado']
];

$statusMeta = $statusMetaLabel[$canSendMeta] ?? null;
?>

<div class="mt-3 border-top pt-2">
    <div class="d-flex align-items-center justify-content-between flex-wrap">
        <strong>Diagnóstico da Meta</strong>

        <?php if($statusMeta){ ?>
            <span class="badge badge-<?= $statusMeta['classe']; ?>">
                <?= htmlspecialchars($statusMeta['texto'], ENT_QUOTES, 'UTF-8'); ?>
            </span>
        <?php } ?>
    </div>

    <?php if(!empty($errosMeta)){ ?>
        <?php foreach($errosMeta as $erroMeta){ ?>
            <div class="alert alert-<?= htmlspecialchars($erroMeta['nivel'] ?? 'warning', ENT_QUOTES, 'UTF-8'); ?> py-2 px-3 mt-2 mb-2">
                <div>
                    <strong><?= htmlspecialchars($erroMeta['titulo'] ?? 'Atenção necessária na Meta', ENT_QUOTES, 'UTF-8'); ?></strong>
                    <?php if(!empty($erroMeta['codigo'])){ ?>
                        <span class="badge badge-light ml-1">Código <?= htmlspecialchars($erroMeta['codigo'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php } ?>
                </div>

                <div class="small mt-1">
                    <?= htmlspecialchars($erroMeta['descricao'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                </div>

                <?php if(!empty($erroMeta['solucao'])){ ?>
                    <div class="small mt-1">
                        <strong>Como resolver:</strong>
                        <?= htmlspecialchars($erroMeta['solucao'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php } ?>

                <div class="mt-2">
                    <a
                    href="<?= htmlspecialchars($erroMeta['url'] ?? \Services\MetaHealthService::META_WHATSAPP_MANAGER_URL, ENT_QUOTES, 'UTF-8'); ?>"
                    class="btn btn-sm btn-outline-dark"
                    target="_blank"
                    rel="noopener noreferrer"
                    >
                        <i class="fas fa-external-link-alt"></i>
                        <?= htmlspecialchars($erroMeta['acao'] ?? 'Abrir WhatsApp Manager', ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                </div>
            </div>
        <?php } ?>
    <?php elseif(!empty($diagnosticoMeta['disponivel'])){ ?>
        <div class="alert alert-success py-2 px-3 mt-2 mb-2">
            <i class="fas fa-check-circle"></i>
            A Meta não informou nenhuma pendência de saúde para esta conta.
        </div>
    <?php else{ ?>
        <div class="small text-muted mt-2">
            <?= htmlspecialchars($diagnosticoMeta['mensagem'] ?? 'Diagnóstico da Meta indisponível.', ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php } ?>

    <a
    href="<?= \Services\MetaHealthService::META_WHATSAPP_MANAGER_URL; ?>"
    class="btn btn-link btn-sm px-0 mt-1"
    target="_blank"
    rel="noopener noreferrer"
    >
        <i class="fas fa-external-link-alt"></i>
        Gerenciar conta no WhatsApp Manager
    </a>
</div>
