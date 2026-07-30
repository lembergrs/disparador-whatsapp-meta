<?php use Services\MensagemStatusService; foreach($mensagens as $msg){ ?>

<?php if($msg['MSG_Direcao'] == 'enviada'){ ?>

<div class="d-flex justify-content-end mb-2">

    <div
    class="p-2 rounded shadow-sm"
    style="
        background:#d9fdd3;
        max-width:70%;
        border-radius:8px;
    "
    >

        <?= nl2br(htmlspecialchars($msg['MSG_Texto'] ?? '', ENT_QUOTES, 'UTF-8')); ?>

        <div class="text-muted mensagem-meta mensagem-meta-saida">
            <span class="mensagem-horario"><?= date('d/m/Y H:i', strtotime($msg['MSG_DataMensagem'])); ?></span>
            <?php $statusVisual = MensagemStatusService::apresentacao($msg['MSG_Status'] ?? null, $msg['MSG_CodigoErro'] ?? null, $msg['MSG_MensagemErro'] ?? null, $msg['MSG_FalhouEm'] ?? null); if($statusVisual){ ?>
            <span class="mensagem-status <?= htmlspecialchars($statusVisual['classe'], ENT_QUOTES, 'UTF-8'); ?>" data-message-status-id="<?= (int)$msg['MSG_ID']; ?>" data-status="<?= htmlspecialchars($statusVisual['status'], ENT_QUOTES, 'UTF-8'); ?>" title="<?= htmlspecialchars($statusVisual['tooltip'], ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?= htmlspecialchars($statusVisual['tooltip'], ENT_QUOTES, 'UTF-8'); ?>" role="img"><i class="fas <?= htmlspecialchars($statusVisual['icone'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i></span>
            <?php } ?>
        </div>

    </div>

</div>

<?php }else{ ?>

<div class="d-flex justify-content-start mb-2">

    <div
    class="p-2 rounded shadow-sm"
    style="
        background:#ffffff;
        max-width:70%;
        border-radius:8px;
    "
    >

        <?= nl2br(htmlspecialchars($msg['MSG_Texto'] ?? '', ENT_QUOTES, 'UTF-8')); ?>

        <br>

        <small class="text-muted">
            <?= date('d/m/Y H:i', strtotime($msg['MSG_DataMensagem'])); ?>
        </small>

    </div>

</div>

<?php } ?>

<?php } ?>
