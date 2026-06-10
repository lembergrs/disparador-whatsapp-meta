<div class="card">

<div class="card-header">

<h3 class="card-title">

Novo Disparo

</h3>

</div>

<div class="card-body">

<form
method="POST"
id="formDisparo"
action="<?= BASE_URL; ?>/index.php?url=disparo/enviar"
>

<div class="row">

<div class="col-md-6">

<div class="form-group">

<label>Conta Meta</label>

<select
name="meta"
id="meta"
class="form-control"
required
>

<?php $totalContas = count($contas); ?>

<?php if($totalContas > 1){ ?>

    <option value="">
        Selecione
    </option>

<?php } ?>

<?php foreach($contas as $conta){ ?>

    <option
    value="<?= $conta['MTA_ID']; ?>"
    <?= $totalContas == 1 ? 'selected' : ''; ?>
    >
        <?= $conta['MTA_Nome']; ?>
    </option>

<?php } ?>

</select>

</div>

</div>

<div class="col-md-6">

<div class="form-group">

<label>Template</label>

<select
name="template"
id="template"
class="form-control"
required
disabled
>

<option value="">
Selecione primeiro a Conta Meta
</option>

</select>

</div>

</div>

</div>

<div
id="areaProgressoDisparo"
style="display:none;"
class="mb-3"
>

    <strong id="textoProgressoDisparo">
        Preparando envio...
    </strong>

    <div class="progress mt-2">

        <div
        id="barraProgressoDisparo"
        class="progress-bar progress-bar-striped progress-bar-animated bg-success"
        style="width:0%"
        >
            0%
        </div>

    </div>

</div>

<div id="resumoFinalDisparo"></div>

<div class="row mt-3">

    <div class="col-md-6">

        <div id="previewTemplateDisparo">

            <div class="card card-outline card-success">

                <div class="card-header">

                    <h3 class="card-title">
                        Prévia da mensagem
                    </h3>

                </div>

                <div class="card-body">

                    <div
                    id="conteudoPreviewTemplateDisparo"
                    class="disparo-preview-box"
                    >
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-file-alt fa-3x mb-3"></i>
                            <h5>Preview da mensagem</h5>
                            <p>Selecione um template para exibir a prévia.</p>
                        </div>
                    </div>

                </div>

            </div>

        </div>

    </div>

    <div class="col-md-6">

        <div id="painelEdicaoDisparo">

            <div class="form-group">

                <label>Número(s) Destino</label>

                <textarea
                name="numeros"
                id="numerosDestino"
                class="form-control disparo-numeros-box"
                rows="7"
                placeholder="(41) 99999-9999&#10;(41) 98888-8888"
                required
                ></textarea>

                <small class="text-muted">
                    Informe um número por linha. Também pode separar por vírgula ou ponto e vírgula. <br />
                    Atente para o formato do número (99) 99999-9999 
                </small>

            </div>

            <div id="areaVariaveis"></div>

            <button
            type="submit"
            id="btnEnviarDisparo"
            class="btn btn-success"
            >
                <i class="fas fa-paper-plane"></i>
                Enviar Template
            </button>

        </div>

        <div
        id="painelExecucaoDisparo"
        style="display:none;"
        >

            <div class="d-flex justify-content-between align-items-center mb-2">

                <h5 class="mb-0">
                    Status dos Envios
                </h5>

                <button
                type="button"
                id="btnPararDisparo"
                class="btn btn-danger btn-sm"
                >
                    <i class="fas fa-stop"></i>
                    Parar envio
                </button>

            </div>

            <div
            id="boxStatusNumeros"
            class="status-envios-box"
            >

                <table class="table table-sm table-bordered mb-0">

                    <thead>
                        <tr>
                            <th>Número</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody id="listaStatusNumeros"></tbody>

                </table>

            </div>

        </div>

    </div>

</div>

<script>

window.TEMPLATES_DISPARO = <?= json_encode($templates, JSON_UNESCAPED_UNICODE); ?>;
window.TOTAL_CONTAS_META = <?= count($contas); ?>;

$('#template').change(function(){

    let option =
        $(this).find(':selected');





    let componentes =
        option.attr(
            'data-componentes'
        );





    if(!componentes){

        return;

    }

    componentes =
        atob(componentes);

    try{

        componentes =
            JSON.parse(componentes);

    }catch(e){

        return;

    }


    let variaveis = [];

    componentes.forEach(function(comp){

        if(comp.text){

            let matches =
                comp.text.match(
                    /{{(.*?)}}/g
                );


            if(matches){

                matches.forEach(function(v){

                    v = v
                        .replace('{{','')
                        .replace('}}','');

                    if(!variaveis.includes(v)){

                        variaveis.push(v);

                    }

                });

            }

        }

    });


    let html = '';

    variaveis.forEach(function(v){

        html += `

            <div class="form-group">

                <label>
                    Variável ${v}
                </label>

                <input
                type="text"
                name="variaveis[${v}]"
                class="form-control"
                required
                >

            </div>

        `;

    });


    $('#areaVariaveis').html(
        html
    );

});

</script>