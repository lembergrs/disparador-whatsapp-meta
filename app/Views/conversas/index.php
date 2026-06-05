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
                        <?= htmlspecialchars($nomeSelecionado); ?>
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
                            action="<?= rtrim(BASE_URL, '/'); ?>/index.php?url=conversa/enviarAjax"
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
                                    autocomplete="off"
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

                <div class="card-body text-center text-muted d-flex align-items-center justify-content-center">
                    Selecione uma conversa para visualizar as mensagens.
                </div>

            <?php } ?>

        </div>

    </div>

</div>

<div class="modal fade" id="modalEtiquetas" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">
                    Etiquetas da conversa
                </h5>

                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>

            <div class="modal-body" id="conteudoEtiquetas">
                Carregando...
            </div>

        </div>
    </div>
</div>

<script>

document.addEventListener('DOMContentLoaded', function(){

    const urlBase =
        '<?= rtrim(BASE_URL, '/'); ?>/index.php?url=';

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
            urlBase + 'conversa/ajaxLista&id=' + conversaAberta
        );
    }

    function atualizarMensagens(marcarLida)
    {
        if(conversaAberta == ''){
            return;
        }

        if($('#areaMensagens').length == 0){
            return;
        }

        $('#areaMensagens').load(
            urlBase
                + 'conversa/ajaxMensagens&id='
                + conversaAberta
                + '&marcar_lida='
                + (marcarLida || 'N'),
            function(){
                rolarMensagensParaFinal();
            }
        );
    }

    rolarMensagensParaFinal();

    setInterval(function(){

        $.getJSON(

            urlBase + 'conversa/verificarAtualizacao',

            {
                ultima: ultimaAtualizacaoConversas
            },

            function(retorno){

                if(retorno.atualizar){

                    ultimaAtualizacaoConversas =
                        retorno.ultima;

                    atualizarListaConversas();
                    atualizarMensagens('N');

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

                    atualizarMensagens('N');
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

    $(document).on('click', '.item-conversa', function(e){

        if($(e.target).closest('button, a.btn, .btn-marcar-nao-lida, .btn-etiquetas').length){
            return;
        }

        let conversaId =
            $(this).data('id');

        if(!conversaId){
            return;
        }

        window.location.href =
            urlBase + 'conversa&id=' + conversaId;

    });

    $(document).on('click', '.btn-marcar-nao-lida', function(e){

        e.preventDefault();
        e.stopPropagation();

        let conversaId =
            $(this).data('id');

        if(!conversaId){
            return;
        }

        $.post(

            urlBase + 'conversa/marcarNaoLidaAjax',

            {
                conversa_id: conversaId
            },

            function(retorno){

                if(retorno.sucesso){

                    atualizarListaConversas();

                }else{

                    alert(
                        retorno.erro
                        || 'Erro ao marcar conversa como não lida.'
                    );

                }

            },

            'json'

        );

    });

    $(document).on('click', '.btn-etiquetas', function(e){

        e.preventDefault();
        e.stopPropagation();

        let conversaId =
            $(this).data('id');

        if(!conversaId){
            return;
        }

        $('#conteudoEtiquetas').html('Carregando...');
        $('#modalEtiquetas').modal('show');

        $('#conteudoEtiquetas').load(
            urlBase + 'conversa/etiquetasAjax&conversa_id=' + conversaId
        );

    });

    $(document).on('submit', '#formEtiquetasConversa', function(e){

        e.preventDefault();

        $.post(

            urlBase + 'conversa/salvarEtiquetasAjax',

            $(this).serialize(),

            function(retorno){

                if(retorno.sucesso){

                    $('#modalEtiquetas').modal('hide');
                    atualizarListaConversas();

                }else{

                    alert(
                        retorno.erro
                        || 'Erro ao salvar etiquetas.'
                    );

                }

            },

            'json'

        );

    });

    $(document).on('click', '#btnCriarEtiqueta', function(){

        let nome =
            $('#novaEtiquetaNome').val().trim();

        let cor =
            $('#novaEtiquetaCor').val();

        let conversaId =
            $('#formEtiquetasConversa input[name="conversa_id"]').val();

        if(nome == ''){
            alert('Informe o nome da etiqueta.');
            $('#novaEtiquetaNome').focus();
            return;
        }

        $.post(

            urlBase + 'conversa/criarEtiquetaAjax',

            {
                nome: nome,
                cor: cor
            },

            function(retorno){

                if(retorno.sucesso){

                    $('#conteudoEtiquetas').load(
                        urlBase + 'conversa/etiquetasAjax&conversa_id=' + conversaId
                    );

                }else{

                    alert(
                        retorno.erro
                        || 'Erro ao criar etiqueta.'
                    );

                }

            },

            'json'

        );

    });

});

</script>
