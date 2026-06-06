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

            <div class="border-bottom p-2">

                <input
                    type="text"
                    id="filtroBusca"
                    class="form-control form-control-sm mb-2"
                    placeholder="Buscar nome ou telefone..."
                    value="<?= htmlspecialchars($busca ?? ''); ?>"
                    autocomplete="off"
                >

                <div class="row">

                    <div class="col-6">

                        <select
                            id="filtroStatus"
                            class="form-control form-control-sm"
                        >
                            <option value="" <?= empty($status) ? 'selected' : ''; ?>>
                                Todas
                            </option>

                            <option value="N" <?= ($status ?? '') == 'N' ? 'selected' : ''; ?>>
                                Não lidas
                            </option>

                            <option value="L" <?= ($status ?? '') == 'L' ? 'selected' : ''; ?>>
                                Lidas
                            </option>
                        </select>

                    </div>

                    <div class="col-6">

                        <select
                            id="filtroEtiqueta"
                            class="form-control form-control-sm"
                        >
                            <option value="" <?= empty($etiqueta) ? 'selected' : ''; ?>>
                                Todas etiquetas
                            </option>

                            <?php foreach(($etiquetas ?? []) as $etq){ ?>

                                <option
                                    value="<?= $etq['ETQ_ID']; ?>"
                                    <?= (($etiqueta ?? '') == $etq['ETQ_ID']) ? 'selected' : ''; ?>
                                >
                                    <?= htmlspecialchars($etq['ETQ_Nome']); ?>
                                </option>

                            <?php } ?>

                        </select>

                    </div>

                </div>

            </div>

            <div
                id="listaConversas"
                class="list-group list-group-flush"
                style="overflow-y:auto;"
            >
                <?php require __DIR__ . '/partials/lista.php'; ?>
            </div>

        </div>

    </div>

    <div class="col-md-8">

        <div
            id="painelConversa"
            class="card"
            style="height:75vh;"
        >

            <?php require __DIR__ . '/partials/painel.php'; ?>

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

                <button
                type="button"
                class="close"
                data-dismiss="modal"
                aria-label="Close"
                >
                    <span aria-hidden="true">&times;</span>
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

    let filtroBusca =
        $('#filtroBusca').val() || '';

    let filtroStatus =
        $('#filtroStatus').val() || '';

    let filtroEtiqueta =
        $('#filtroEtiqueta').val() || '';

    let timerBusca = null;

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
            urlBase
                + 'conversa/ajaxLista'
                + '&id=' + conversaAberta
                + '&busca=' + encodeURIComponent(filtroBusca)
                + '&status=' + encodeURIComponent(filtroStatus)
                + '&etiqueta=' + encodeURIComponent(filtroEtiqueta)
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
    
    let intervaloAtualizacao = null;
    let atualizandoConversas = false;

    function deveAtualizarConversas()
    {
        if(document.hidden){
            return false;
        }

        if(conversaAberta == ''){
            return false;
        }

        return true;
    }

    function verificarAtualizacoes()
    {
        if(!deveAtualizarConversas()){
            return;
        }

        if(atualizandoConversas){
            return;
        }

        atualizandoConversas = true;

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

                    if(conversaAberta != ''){
                        atualizarMensagens('N');
                    }

                }

            }

        ).always(function(){

            atualizandoConversas = false;

        });
    }

    function iniciarAtualizacaoAutomatica()
    {
        if(intervaloAtualizacao){
            clearInterval(intervaloAtualizacao);
        }

        intervaloAtualizacao =
            setInterval(function(){

                verificarAtualizacoes();

            }, 60000);
    }

    document.addEventListener('visibilitychange', function(){

        if(!document.hidden){
            verificarAtualizacoes();
        }

    });

    iniciarAtualizacaoAutomatica();
    

    $('#filtroBusca').on('keyup', function(){

        clearTimeout(timerBusca);

        timerBusca = setTimeout(function(){

            filtroBusca =
                $('#filtroBusca').val() || '';

            atualizarListaConversas();

        }, 300);

    });

    $('#filtroStatus').on('change', function(){

        filtroStatus =
            $(this).val() || '';

        atualizarListaConversas();

    });

    $('#filtroEtiqueta').on('change', function(){

        filtroEtiqueta =
            $(this).val() || '';

        atualizarListaConversas();

    });

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

        conversaAberta =
            conversaId;

        $('#painelConversa').html(
            '<div class="card-body text-center text-muted d-flex align-items-center justify-content-center">' +
                '<i class="fas fa-spinner fa-spin mr-2"></i> Carregando conversa...' +
            '</div>'
        );

        $('#painelConversa').load(
            urlBase + 'conversa/ajaxConversa&id=' + conversaId,
            function(){

                rolarMensagensParaFinal();
                atualizarListaConversas();

            }
        );

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
