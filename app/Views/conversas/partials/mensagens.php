<?php
use Services\MensagemStatusService;

$renderConteudo = function($msg){
    $tipo = strtolower((string) ($msg['MSG_Tipo'] ?? 'text'));
    $texto = (string) ($msg['MSG_Texto'] ?? '');
    $temMidia = !empty($msg['MSG_MediaId']);
    $urlMidia = '?url=conversa/midia&id=' . (int) ($msg['MSG_ID'] ?? 0);
    $escape = function($valor){ return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8'); };

    if($temMidia && in_array($tipo, ['image','sticker'], true)){
        echo '<div class="mb-1"><img src="' . $escape($urlMidia) . '" alt="' . ($tipo === 'sticker' ? 'Sticker' : 'Imagem') . '" style="max-width:100%;max-height:320px;border-radius:8px;object-fit:contain;"></div>';
    }elseif($temMidia && $tipo === 'audio'){
        echo '<div class="mb-1"><audio controls preload="none" src="' . $escape($urlMidia) . '" style="max-width:100%;">Seu navegador não suporta áudio.</audio></div>';
    }elseif($temMidia && $tipo === 'video'){
        echo '<div class="mb-1"><video controls preload="metadata" src="' . $escape($urlMidia) . '" style="max-width:100%;max-height:360px;border-radius:8px;">Seu navegador não suporta vídeo.</video></div>';
    }elseif($temMidia && $tipo === 'document'){
        $nome = trim((string) ($msg['MSG_MediaNome'] ?? '')) ?: 'Abrir documento';
        echo '<div class="mb-1"><a class="btn btn-sm btn-light border" href="' . $escape($urlMidia) . '" target="_blank" rel="noopener"><i class="fas fa-file-alt mr-1"></i>' . $escape($nome) . '</a></div>';
    }

    $placeholders = ['[AUDIO]','[IMAGE]','[VIDEO]','[DOCUMENT]','[STICKER]'];
    if($texto !== '' && !($temMidia && in_array(strtoupper($texto), $placeholders, true))){
        echo nl2br($escape($texto));
    }elseif(!$temMidia && $tipo !== 'text'){
        echo '<span class="text-muted">' . $escape($texto !== '' ? $texto : '[' . strtoupper($tipo) . ']') . '</span>';
    }
};

foreach($mensagens as $msg){
    $enviada = ($msg['MSG_Direcao'] ?? '') === 'enviada';
?>
<div class="d-flex justify-content-<?= $enviada ? 'end' : 'start'; ?> mb-2">
    <div class="p-2 rounded shadow-sm" style="background:<?= $enviada ? '#d9fdd3' : '#ffffff'; ?>;max-width:70%;border-radius:8px;">
        <?php $renderConteudo($msg); ?>
        <div class="text-muted mensagem-meta mensagem-meta-saida">
            <span class="mensagem-horario"><?= date('d/m/Y H:i', strtotime($msg['MSG_DataMensagem'])); ?></span>
            <?php if($enviada){ $statusVisual = MensagemStatusService::apresentacao($msg['MSG_Status'] ?? null, $msg['MSG_CodigoErro'] ?? null, $msg['MSG_MensagemErro'] ?? null, $msg['MSG_FalhouEm'] ?? null); if($statusVisual){ ?>
            <span class="mensagem-status <?= htmlspecialchars($statusVisual['classe'], ENT_QUOTES, 'UTF-8'); ?>" data-message-status-id="<?= (int)$msg['MSG_ID']; ?>" data-status="<?= htmlspecialchars($statusVisual['status'], ENT_QUOTES, 'UTF-8'); ?>" title="<?= htmlspecialchars($statusVisual['tooltip'], ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?= htmlspecialchars($statusVisual['tooltip'], ENT_QUOTES, 'UTF-8'); ?>" role="img"><i class="fas <?= htmlspecialchars($statusVisual['icone'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i></span>
            <?php }} ?>
        </div>
    </div>
</div>
<?php } ?>
