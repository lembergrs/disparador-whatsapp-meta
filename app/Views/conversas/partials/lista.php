<?php

if(!function_exists('formatarNumeroBR')){

    function formatarNumeroBR($numero)
    {
        $numero = preg_replace('/\D/', '', $numero);

        if(substr($numero, 0, 2) == '55'){
            $numero = substr($numero, 2);
        }

        if(strlen($numero) == 11){

            return '(' . substr($numero, 0, 2) . ') '
                . substr($numero, 2, 5)
                . '-'
                . substr($numero, 7);

        }

        if(strlen($numero) == 10){

            return '(' . substr($numero, 0, 2) . ') '
                . substr($numero, 2, 4)
                . '-'
                . substr($numero, 6);

        }

        return $numero;
    }
}

?>

<?php foreach($conversas as $conversa){ ?>

<?php

$nome =
    $conversa['CVS_Nome']
    ?: formatarNumeroBR($conversa['CVS_Numero']);

$numeroFormatado =
    formatarNumeroBR($conversa['CVS_Numero']);

$ativa =
    isset($conversaSelecionada['CVS_ID'])
    && $conversaSelecionada['CVS_ID'] == $conversa['CVS_ID'];

$itemClass =
    $ativa
    ? 'active'
    : '';

if(!$ativa && $conversa['CVS_NaoLida'] == 'S'){
    $itemClass .= ' font-weight-bold';
}

?>

<a
href="<?= BASE_URL; ?>/index.php?url=conversa&id=<?= $conversa['CVS_ID']; ?>"
class="list-group-item list-group-item-action item-conversa <?= $itemClass; ?>"
data-id="<?= $conversa['CVS_ID']; ?>"
>

<div class="d-flex justify-content-between align-items-start">

    <div style="max-width:75%;">

        <strong>
            <?= htmlspecialchars($nome); ?>
        </strong>

        <br>

        <small class="<?= $ativa ? 'text-white' : 'text-muted'; ?>">
            <?= htmlspecialchars($numeroFormatado); ?>
        </small>

    </div>

    <div class="text-right">

        <?php if($conversa['CVS_NaoLida'] == 'S'){ ?>

            <span class="badge badge-success mb-1">
                <?= $conversa['CVS_QtdeNaoLidas'] > 0 ? $conversa['CVS_QtdeNaoLidas'] : 'Novo'; ?>
            </span>

            <br>

        <?php } ?>

        <button
            type="button"
            class="btn btn-xs <?= $ativa ? 'btn-light' : 'btn-outline-secondary'; ?> btn-marcar-nao-lida"
            data-id="<?= $conversa['CVS_ID']; ?>"
            title="Marcar como não lida"
        >
            <i class="far fa-envelope"></i>
        </button>

        <button
            type="button"
            class="btn btn-xs <?= $ativa ? 'btn-light' : 'btn-outline-primary'; ?> btn-etiquetas"
            data-id="<?= $conversa['CVS_ID']; ?>"
            title="Etiquetas"
        >
            <i class="fas fa-tags"></i>
        </button>

    </div>

</div>

<?php if(!empty($conversa['Etiquetas'])){ ?>

    <div class="mt-1">
        <?php foreach(explode('|', $conversa['Etiquetas']) as $etiquetaTexto){ ?>

            <?php
            $partes = explode('#', $etiquetaTexto);
            $nomeEtiqueta = $partes[0] ?? '';
            $corEtiqueta = $partes[1] ?? 'secondary';
            ?>

            <?php if($nomeEtiqueta != ''){ ?>

                <span class="badge badge-<?= htmlspecialchars($corEtiqueta); ?>">
                    <?= htmlspecialchars($nomeEtiqueta); ?>
                </span>

            <?php } ?>

        <?php } ?>
    </div>

<?php } ?>

<br>

<small class="<?= $ativa ? 'text-white' : 'text-muted'; ?>">
    <?= htmlspecialchars($conversa['CVS_UltimaMensagem']); ?>
</small>

</a>

<?php } ?>