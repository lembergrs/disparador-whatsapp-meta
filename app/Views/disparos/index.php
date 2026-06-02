<div class="card">

<div class="card-header">

<h3 class="card-title">

Novo Disparo

</h3>

</div>

<div class="card-body">

<form
method="POST"
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





<div class="form-group">

<label>Número Destino</label>

<input
type="text"
name="numero"
class="form-control telefone"
required
>

<small class="text-muted">

Ex:
5544999999999

</small>

</div>





<div id="areaVariaveis"></div>





<button
type="submit"
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