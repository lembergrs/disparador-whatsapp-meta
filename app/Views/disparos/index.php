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

<option value="">
Selecione
</option>

<?php foreach($contas as $conta){ ?>

<option value="<?= $conta['MTA_ID']; ?>">

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
id="previewTemplateDisparo"
class="mt-3"
style="display:none;"
>

    <div class="card card-outline card-success">

        <div class="card-header">

            <h3 class="card-title">
                Prévia da mensagem
            </h3>

        </div>

        <div class="card-body">

            <div
            id="conteudoPreviewTemplateDisparo"
            style="white-space: pre-line;"
            ></div>

        </div>

    </div>

</div>



<div class="form-group">

<label>Número(s) Destino</label>

<textarea
name="numeros"
id="numerosDestino"
class="form-control"
rows="5"
placeholder="(41) 99999-9999&#10;(41) 98888-8888"
required
></textarea>

<small class="text-muted">
Informe um número por linha. Também pode separar por vírgula ou ponto e vírgula. Use apenas DDD + número.
</small>

</div>





<div id="areaVariaveis"></div>

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

<div
id="areaStatusNumeros"
style="display:none"
class="mt-3"
>

    <h5>Status dos Envios</h5>

    <table class="table table-sm table-bordered">
        <thead>
            <tr>
                <th>Número</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody id="listaStatusNumeros"></tbody>
    </table>

</div>

<button
type="submit"
id="btnEnviarDisparo"
class="btn btn-success"
>
    <i class="fas fa-paper-plane"></i>
    Enviar Template
</button>

</form>

</div>

</div>

<script>

window.TEMPLATES_DISPARO = <?= json_encode($templates, JSON_UNESCAPED_UNICODE); ?>;

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