<?php
$metaAlertaContainerId = $metaAlertaContainerId ?? 'metaSendHealthAlert';
$diagnosticosMetaEnvio = $diagnosticosMetaEnvio ?? [];
?>

<div id="<?= htmlspecialchars($metaAlertaContainerId, ENT_QUOTES, 'UTF-8'); ?>" class="mb-3">
    <?php foreach($diagnosticosMetaEnvio as $metaId => $diagnostico){ ?>
        <?php
        $canSend = strtoupper((string) ($diagnostico['can_send_message'] ?? ''));
        $erros = $diagnostico['erros'] ?? [];
        $temProblema = ($canSend === 'BLOCKED' || $canSend === 'LIMITED' || !empty($erros));
        if(!$temProblema){
            continue;
        }
        $classe = $canSend === 'BLOCKED' ? 'danger' : 'warning';
        ?>

        <div
            class="alert alert-<?= $classe; ?> js-meta-send-health-alert"
            data-meta-id="<?= (int) $metaId; ?>"
            style="display:none;"
        >
            <div class="d-flex align-items-start">
                <i class="fas fa-exclamation-triangle mr-2 mt-1"></i>
                <div class="flex-fill">
                    <strong>
                        <?php if($canSend === 'BLOCKED'){ ?>
                            A Meta está bloqueando o envio de mensagens desta conta.
                        <?php }else{ ?>
                            A Meta informou uma limitação para o envio de mensagens desta conta.
                        <?php } ?>
                    </strong>

                    <?php if(!empty($erros)){ ?>
                        <?php foreach($erros as $erro){ ?>
                            <div class="mt-2">
                                <strong><?= htmlspecialchars($erro['titulo'] ?? 'Pendência na Meta', ENT_QUOTES, 'UTF-8'); ?></strong>
                                <?php if(!empty($erro['codigo'])){ ?>
                                    <span class="badge badge-light ml-1">Código <?= htmlspecialchars($erro['codigo'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php } ?>

                                <?php if(!empty($erro['descricao'])){ ?>
                                    <div class="small mt-1">
                                        <?= htmlspecialchars($erro['descricao'], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                <?php } ?>

                                <?php if(!empty($erro['solucao'])){ ?>
                                    <div class="small mt-1">
                                        <strong>Como resolver:</strong>
                                        <?= htmlspecialchars($erro['solucao'], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                <?php } ?>

                                <?php if(!empty($erro['url'])){ ?>
                                    <a
                                        href="<?= htmlspecialchars($erro['url'], ENT_QUOTES, 'UTF-8'); ?>"
                                        class="btn btn-sm btn-outline-dark mt-2"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        <i class="fas fa-external-link-alt"></i>
                                        <?= htmlspecialchars($erro['acao'] ?? 'Verificar na Meta', ENT_QUOTES, 'UTF-8'); ?>
                                    </a>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    <?php }else{ ?>
                        <div class="small mt-1">
                            Consulte o WhatsApp Manager antes de iniciar novos envios.
                        </div>
                        <a
                            href="<?= \Services\MetaHealthService::META_WHATSAPP_MANAGER_URL; ?>"
                            class="btn btn-sm btn-outline-dark mt-2"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <i class="fas fa-external-link-alt"></i>
                            Abrir WhatsApp Manager
                        </a>
                    <?php } ?>
                </div>
            </div>
        </div>
    <?php } ?>
</div>

<script>
window.DisparadorMetaHealth = window.DisparadorMetaHealth || {};
window.DisparadorMetaHealth.exibirParaConta = function(containerId, metaId){
    var container = document.getElementById(containerId);
    if(!container){ return; }

    container.querySelectorAll('.js-meta-send-health-alert').forEach(function(alerta){
        alerta.style.display = String(alerta.getAttribute('data-meta-id')) === String(metaId || '')
            ? 'block'
            : 'none';
    });
};
</script>
