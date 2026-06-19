<?php foreach($mensagens as $msg){ ?>

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

        <br>

        <small class="text-muted">
            <?= date('d/m/Y H:i', strtotime($msg['MSG_DataMensagem'])); ?>
        </small>

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