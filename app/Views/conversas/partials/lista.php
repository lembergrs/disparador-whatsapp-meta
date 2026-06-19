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

$conversaSelecionadaId =
    $conversaSelecionada['CVS_ID']
    ?? ($_GET['id'] ?? null);

$ativa =
    !empty($conversaSelecionadaId)
    && (int) $conversaSelecionadaId === (int) $conversa['CVS_ID'];

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

<div class="d-flex justify-content-between align-items-start conversa-lista-topo">

    <div class="conversa-lista-nome">

        <strong>
            <?= htmlspecialchars($nome); ?>
        </strong>

    </div>

    <div class="text-right conversa-lista-acoes">

        <?php if($conversa['CVS_NaoLida'] == 'S'){ ?>

            <span class="badge badge-success mb-1 badge-nao-lida">
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

        <?php if(!empty($podeAtribuirConversa)){ ?>

            <button
                type="button"
                class="btn btn-xs <?= $ativa ? 'btn-light' : 'btn-outline-info'; ?> btn-atribuir"
                data-id="<?= $conversa['CVS_ID']; ?>"
                data-responsavel="<?= (int) ($conversa['ResponsavelId'] ?? 0); ?>"
                title="Atribuir"
            >
                <i class="fas fa-user-plus"></i>
            </button>

        <?php } ?>

    </div>

</div>

<div class="conversa-lista-meta <?= $ativa ? 'text-white' : 'text-muted'; ?>">

    <small>
        <?= htmlspecialchars($numeroFormatado); ?>
    </small>

    <small class="conversa-lista-responsavel">
        <i class="fas fa-user-headset"></i>
        <?= !empty($conversa['ResponsavelNome'])
            ? htmlspecialchars($conversa['ResponsavelNome'])
            : 'Sem responsável'; ?>
    </small>

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

<small class="conversa-ultima-mensagem <?= $ativa ? 'text-white' : 'text-muted'; ?>">
    <?= htmlspecialchars($conversa['CVS_UltimaMensagem']); ?>
</small>

</a>

<?php } ?>