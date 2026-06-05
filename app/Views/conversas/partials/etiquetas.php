<?php

$selecionadas = [];

foreach($etiquetasConversa as $etq){
    $selecionadas[] = $etq['ETQ_ID'];
}

?>

<form id="formEtiquetasConversa">

    <input
        type="hidden"
        name="conversa_id"
        value="<?= htmlspecialchars($id); ?>"
    >

    <div class="form-group">

        <label>
            Etiquetas
        </label>

        <?php if(empty($etiquetas)){ ?>

            <p class="text-muted mb-0">
                Nenhuma etiqueta cadastrada.
            </p>

        <?php } ?>

        <?php foreach($etiquetas as $etiqueta){ ?>

            <div class="custom-control custom-checkbox mb-1">

                <input
                    type="checkbox"
                    class="custom-control-input"
                    id="etiqueta_<?= $etiqueta['ETQ_ID']; ?>"
                    name="etiquetas[]"
                    value="<?= $etiqueta['ETQ_ID']; ?>"
                    <?= in_array($etiqueta['ETQ_ID'], $selecionadas) ? 'checked' : ''; ?>
                >

                <label
                    class="custom-control-label"
                    for="etiqueta_<?= $etiqueta['ETQ_ID']; ?>"
                >
                    <span class="badge badge-<?= htmlspecialchars($etiqueta['ETQ_Cor']); ?>">
                        <?= htmlspecialchars($etiqueta['ETQ_Nome']); ?>
                    </span>
                </label>

            </div>

        <?php } ?>

    </div>

    <hr>

    <div class="form-group">

        <label>
            Nova etiqueta
        </label>

        <div class="input-group">

            <input
                type="text"
                class="form-control"
                id="novaEtiquetaNome"
                placeholder="Ex: Urgente, Pago, Retornar"
                autocomplete="off"
            >

            <select
                class="form-control"
                id="novaEtiquetaCor"
                style="max-width:140px;"
            >
                <option value="secondary">Cinza</option>
                <option value="primary">Azul</option>
                <option value="success">Verde</option>
                <option value="danger">Vermelha</option>
                <option value="warning">Amarela</option>
                <option value="info">Ciano</option>
                <option value="dark">Preta</option>
            </select>

            <div class="input-group-append">

                <button
                    type="button"
                    class="btn btn-outline-primary"
                    id="btnCriarEtiqueta"
                >
                    Criar
                </button>

            </div>

        </div>

    </div>

    <button
        type="submit"
        class="btn btn-primary"
    >
        Salvar etiquetas
    </button>

</form>
