<?php

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

?>

<div class="row">

<div class="col-md-4">

<div class="card" style="height:75vh;">

<div class="card-header bg-light">

<strong>
<i class="fas fa-comments"></i>
Conversas
</strong>

</div>

<div
id="listaConversas"
class="list-group list-group-flush"
style="overflow-y:auto;"
>

<?php require '../app/Views/conversas/partials/lista.php'; ?>

</div>

</div>

</div>

<div class="col-md-8">

<div class="card" style="height:75vh;">

<?php if($conversaSelecionada){ ?>

<?php

$nomeSelecionado =
    $conversaSelecionada['CVS_Nome']
    ?: formatarNumeroBR($conversaSelecionada['CVS_Numero']);

?>

<div class="card-header bg-light">

<strong>
<?= $nomeSelecionado; ?>
</strong>

<br>

<small class="text-muted">
<?= formatarNumeroBR($conversaSelecionada['CVS_Numero']); ?>
</small>

</div>

<div
id="areaMensagens"
class="card-body conversa-bg"
style="
    overflow-y:auto;
    background-color:#efeae2;
    background-image:
        radial-gradient(circle at 25px 25px, rgba(0,0,0,0.04) 2px, transparent 0),
        radial-gradient(circle at 75px 75px, rgba(0,0,0,0.03) 2px, transparent 0);
    background-size:100px 100px;
"
>

<?php require '../app/Views/conversas/partials/mensagens.php'; ?>

</div>

<div class="card-footer bg-light">

<?php if($janelaAberta){ ?>

<form
method="POST"
id="formEnviarMensagem"
action="<?= BASE_URL; ?>/index.php?url=conversa/enviarAjax"
>

<input
type="hidden"
name="conversa_id"
value="<?= $conversaSelecionada['CVS_ID']; ?>"
>

<div class="input-group">

<input
type="text"
name="mensagem"
id="campoMensagem"
class="form-control"
placeholder="Digite uma mensagem..."
required
>

<div class="input-group-append">

<button
id="btnEnviarMensagem"
class="btn btn-success"
type="submit"
>

<i class="fas fa-paper-plane"></i>

</button>

</div>

</div>

</form>

<?php }else{ ?>

<div class="alert alert-warning mb-0">

A janela de atendimento de 24 horas está fechada.
Para falar com este contato novamente, envie um template aprovado.

</div>

<?php } ?>

</div>

<?php }else{ ?>

<div class="card-body text-center text-muted">

Selecione uma conversa para visualizar as mensagens.

</div>

<?php } ?>

</div>

</div>

</div>

<script>

document.addEventListener('DOMContentLoaded', function(){

    let conversaAberta =
        '<?= $conversaSelecionada['CVS_ID'] ?? ''; ?>';

    let ultimaAtualizacaoConversas = '';

    function rolarMensagensParaFinal()
    {
        let area =
            document.getElementById('areaMensagens');

        if(area){
            area.scrollTop =
                area.scrollHeight;
        }
    }

    function atualizarListaConversas()
    {
        $('#listaConversas').load(
            '<?= BASE_URL; ?>/index.php?url=conversa/ajaxLista&id='
            + conversaAberta
        );
    }

    function atualizarMensagens()
    {
        if(conversaAberta == ''){
            return;
        }

        $('#areaMensagens').load(
            '<?= BASE_URL; ?>/index.php?url=conversa/ajaxMensagens&id='
            + conversaAberta,
            function(){
                rolarMensagensParaFinal();
            }
        );
    }

    rolarMensagensParaFinal();
    
    setInterval(function(){

        $.getJSON(

            '<?= BASE_URL; ?>/index.php?url=conversa/verificarAtualizacao',

            {
                ultima: ultimaAtualizacaoConversas
            },

            function(retorno){

                if(retorno.atualizar){

                    ultimaAtualizacaoConversas =
                        retorno.ultima;

                    atualizarListaConversas();
                    atualizarMensagens();

                }

            }

        );

    }, 3000);

    $(document).on('submit', '#formEnviarMensagem', function(e){

        e.preventDefault();

        let form =
            $(this);

        let mensagem =
            $('#campoMensagem').val().trim();

        if(mensagem == ''){
            return;
        }

        $('#btnEnviarMensagem')
            .prop('disabled', true)
            .html('<i class="fas fa-spinner fa-spin"></i>');

        $.ajax({

            url:
                form.attr('action'),

            method:
                'POST',

            data:
                form.serialize(),

            dataType:
                'json',

            success: function(retorno){

                if(retorno.sucesso){

                    $('#campoMensagem').val('');

                    atualizarMensagens();
                    atualizarListaConversas();

                }else{

                    alert(
                        retorno.erro
                        || 'Erro ao enviar mensagem.'
                    );

                }

            },

            error: function(){

                alert(
                    'Erro de comunicação com o servidor.'
                );

            },

            complete: function(){

                $('#btnEnviarMensagem')
                    .prop('disabled', false)
                    .html('<i class="fas fa-paper-plane"></i>');

                $('#campoMensagem').focus();

            }

        });

    });
    
});

</script>