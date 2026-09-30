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

$ultimaMensagemId = 0;
if(!empty($mensagens)){
    $ultimaMensagem = end($mensagens);
    $ultimaMensagemId = (int) ($ultimaMensagem['MSG_ID'] ?? 0);
    reset($mensagens);
}

foreach($mensagens as $msg){
    $enviada = ($msg['MSG_Direcao'] ?? '') === 'enviada';
    $ehUltimaMensagem = ((int) ($msg['MSG_ID'] ?? 0) === $ultimaMensagemId);
?>
<div class="d-flex justify-content-<?= $enviada ? 'end' : 'start'; ?> mb-2"<?= $ehUltimaMensagem ? ' data-ultima-mensagem="1"' : ''; ?>>
    <div
        class="p-2 rounded shadow-sm position-relative mensagem-bolha"
        style="background:<?= $enviada ? '#d9fdd3' : '#ffffff'; ?>;max-width:70%;border-radius:8px;"
        data-mensagem-id="<?= (int) ($msg['MSG_ID'] ?? 0); ?>"
    >
        <?php if(!empty($msg['MSG_MetaMessageId'])){ ?>
        <button
            type="button"
            class="btn btn-sm btn-light border js-abrir-reactions"
            data-mensagem-id="<?= (int) ($msg['MSG_ID'] ?? 0); ?>"
            title="Reagir à mensagem"
            aria-label="Reagir à mensagem"
            style="position:absolute;top:4px;<?= $enviada ? 'left:-34px;' : 'right:-34px;'; ?>width:30px;height:30px;padding:0;border-radius:15px;"
        ><i class="far fa-smile"></i></button>
        <?php } ?>
        <?php $renderConteudo($msg); ?>
        <div class="text-muted mensagem-meta mensagem-meta-saida">
            <span class="mensagem-horario"><?= date('d/m/Y H:i', strtotime($msg['MSG_DataMensagem'])); ?></span>
            <?php if($enviada){ $statusVisual = MensagemStatusService::apresentacao($msg['MSG_Status'] ?? null, $msg['MSG_CodigoErro'] ?? null, $msg['MSG_MensagemErro'] ?? null, $msg['MSG_FalhouEm'] ?? null); if($statusVisual){ ?>
            <span class="mensagem-status <?= htmlspecialchars($statusVisual['classe'], ENT_QUOTES, 'UTF-8'); ?>" data-message-status-id="<?= (int)$msg['MSG_ID']; ?>" data-status="<?= htmlspecialchars($statusVisual['status'], ENT_QUOTES, 'UTF-8'); ?>" title="<?= htmlspecialchars($statusVisual['tooltip'], ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?= htmlspecialchars($statusVisual['tooltip'], ENT_QUOTES, 'UTF-8'); ?>" role="img"><i class="fas <?= htmlspecialchars($statusVisual['icone'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i></span>
            <?php }} ?>
        </div>
        <?php if(!empty($msg['MSG_Reacoes'])){ ?>
        <div class="d-flex flex-wrap" style="gap:3px;margin-top:-3px;margin-bottom:-8px;<?= $enviada ? 'justify-content:flex-end;' : 'justify-content:flex-start;'; ?>">
            <?php foreach($msg['MSG_Reacoes'] as $reacao){ ?>
            <span title="Reação" style="display:inline-flex;align-items:center;justify-content:center;min-width:28px;height:24px;padding:0 6px;background:#fff;border:1px solid #e1e4e8;border-radius:12px;box-shadow:0 1px 2px rgba(0,0,0,.12);font-size:16px;line-height:1;">
                <?= htmlspecialchars((string) ($reacao['emoji'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
            </span>
            <?php } ?>
        </div>
        <?php } ?>
    </div>
</div>
<?php } ?>
