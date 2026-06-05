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

?>

<a
href="<?= BASE_URL; ?>/index.php?url=conversa&id=<?= $conversa['CVS_ID']; ?>"
class="list-group-item list-group-item-action <?= $ativa ? 'active' : ''; ?>"
>

<div class="d-flex justify-content-between">

<strong>
<?= $nome; ?>
</strong>

<?php if($conversa['CVS_NaoLida'] == 'S'){ ?>

<span class="badge badge-success">
<?= $conversa['CVS_QtdeNaoLidas'] > 0 ? $conversa['CVS_QtdeNaoLidas'] : 'Novo'; ?>
</span>

<?php } ?>

</div>

<small class="<?= $ativa ? 'text-white' : 'text-muted'; ?>">
<?= $numeroFormatado; ?>
</small>

<br>

<small class="<?= $ativa ? 'text-white' : 'text-muted'; ?>">
<?= $conversa['CVS_UltimaMensagem']; ?>
</small>

</a>

<?php } ?>