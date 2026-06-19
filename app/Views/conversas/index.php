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

<div class="row" id="conversasContainer">

    <div class="col-md-4">

        <div class="card conversas-resizable-card">

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

                    <div class="col-<?= !empty($podeAtribuirConversa) ? '4' : '6'; ?>">

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

                    <div class="col-<?= !empty($podeAtribuirConversa) ? '4' : '6'; ?>">

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

                    <?php if(!empty($podeAtribuirConversa)){ ?>

                    <div class="col-4">

                        <select
                            id="filtroResponsavel"
                            class="form-control form-control-sm"
                        >
                            <option value="" <?= empty($responsavel) ? 'selected' : ''; ?>>
                                Todos responsáveis
                            </option>

                            <option value="sem" <?= (($responsavel ?? '') == 'sem') ? 'selected' : ''; ?>>
                                Sem responsável
                            </option>

                            <?php foreach(($atendentes ?? []) as $atendente){ ?>

                                <option
                                    value="<?= (int) $atendente['USU_ID']; ?>"
                                    <?= (($responsavel ?? '') == $atendente['USU_ID']) ? 'selected' : ''; ?>
                                >
                                    <?= htmlspecialchars($atendente['USU_Nome']); ?>
                                </option>

                            <?php } ?>

                        </select>

                    </div>

                    <?php } ?>

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
            class="card conversas-resizable-card"
        >

            <?php require __DIR__ . '/partials/painel.php'; ?>

        </div>

    </div>

</div>

<div id="conversasResizeHandle" class="conversas-resize-handle" title="Arraste para ajustar a altura"></div>

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



<?php if(!empty($podeAtribuirConversa)){ ?>
<div class="modal fade" id="modalResponsavel" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formAtribuirResponsavel">
                <div class="modal-header">
                    <h5 class="modal-title">Atribuir conversa</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="conversa_id" id="responsavelConversaId">
                    <div class="form-group">
                        <label>Responsável</label>
                        <select name="responsavel_id" id="responsavelUsuarioId" class="form-control">
                            <option value="">Sem responsável</option>
                            <?php foreach(($atendentes ?? []) as $atendente){ ?>
                                <option value="<?= (int) $atendente['USU_ID']; ?>">
                                    <?= htmlspecialchars($atendente['USU_Nome']); ?>
                                    (<?= htmlspecialchars($atendente['USU_Nivel']); ?>)
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="alert alert-danger d-none" id="erroResponsavel"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnSalvarResponsavel">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php } ?>

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

    let filtroResponsavel =
        $('#filtroResponsavel').val() || '';

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

    let atualizandoListaConversas = false;
    let atualizandoMensagens = false;

    function atualizarListaConversas(forcar)
    {
        const lista = $('#listaConversas');

        if(atualizandoListaConversas && !forcar){
            return;
        }

        const scrollAtual = lista.scrollTop();
        atualizandoListaConversas = true;

        $.ajax({
            url:
                urlBase
                + 'conversa/ajaxLista'
                + '&id=' + conversaAberta
                + '&manter_aberta=' + encodeURIComponent(conversaAberta || '')
                + '&busca=' + encodeURIComponent(filtroBusca)
                + '&status=' + encodeURIComponent(filtroStatus)
                + '&etiqueta=' + encodeURIComponent(filtroEtiqueta)
                + '&responsavel=' + encodeURIComponent(filtroResponsavel),
            method: 'GET'
        }).done(function(html){
            lista.html(html);
            lista.scrollTop(scrollAtual);
        }).always(function(){
            atualizandoListaConversas = false;
        });
    }

    function atualizarMensagens(marcarLida, forcar)
    {
        if(conversaAberta == ''){
            return;
        }

        if($('#areaMensagens').length == 0){
            return;
        }

        if(atualizandoMensagens && !forcar){
            return;
        }

        atualizandoMensagens = true;

        $.ajax({
            url:
                urlBase
                + 'conversa/ajaxMensagens&id='
                + conversaAberta
                + '&marcar_lida='
                + (marcarLida || 'N'),
            method: 'GET'
        }).done(function(html){
            $('#areaMensagens').html(html);
            rolarMensagensParaFinal();
        }).always(function(){
            atualizandoMensagens = false;
        });
    }

    function marcarConversaComoLidaVisualmente(conversaId)
    {
        const item = $('#listaConversas .item-conversa[data-id="' + conversaId + '"]');

        item.removeClass('font-weight-bold');
        item.find('.badge-nao-lida').remove();
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

                    atualizarListaConversas(false);

                    if(conversaAberta != ''){
                        atualizarMensagens('N', false);
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

            atualizarListaConversas(false);

        }, 300);

    });

    $('#filtroStatus').on('change', function(){

        filtroStatus =
            $(this).val() || '';

        atualizarListaConversas(false);

    });

    $('#filtroEtiqueta').on('change', function(){

        filtroEtiqueta =
            $(this).val() || '';

        atualizarListaConversas(false);

    });

    $('#filtroResponsavel').on('change', function(){

        filtroResponsavel =
            $(this).val() || '';

        atualizarListaConversas(false);

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

                    atualizarMensagens('N', false);
                    atualizarListaConversas(false);

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

        e.preventDefault();

        if($(e.target).closest('button, a.btn, .btn-marcar-nao-lida, .btn-etiquetas, .btn-atribuir').length){
            return;
        }

        let conversaId =
            $(this).data('id');

        if(!conversaId){
            return;
        }

        conversaAberta =
            conversaId;

        marcarConversaComoLidaVisualmente(conversaId);

        $('#painelConversa').html(
            '<div class="card-body text-center text-muted d-flex align-items-center justify-content-center">' +
                '<i class="fas fa-spinner fa-spin mr-2"></i> Carregando conversa...' +
            '</div>'
        );

        $('#painelConversa').load(
            urlBase + 'conversa/ajaxConversa&id=' + conversaId,
            function(){

                rolarMensagensParaFinal();

                $('#listaConversas .item-conversa').removeClass('active');
                $('#listaConversas .item-conversa[data-id="' + conversaAberta + '"]').addClass('active');

            }
        );

    });



    $(document).on('click', '.btn-atribuir', function(e){

        e.preventDefault();
        e.stopPropagation();

        $('#erroResponsavel').addClass('d-none').text('');
        $('#responsavelConversaId').val($(this).data('id'));
        $('#responsavelUsuarioId').val($(this).data('responsavel') || '');
        $('#modalResponsavel').modal('show');

    });

    $(document).on('submit', '#formAtribuirResponsavel', function(e){

        e.preventDefault();

        $('#btnSalvarResponsavel').prop('disabled', true);
        $('#erroResponsavel').addClass('d-none').text('');

        $.post(
            urlBase + 'conversa/atribuirResponsavelAjax',
            $(this).serialize(),
            function(retorno){

                if(retorno.sucesso){
                    $('#modalResponsavel').modal('hide');
                    atualizarListaConversas(false);

                    if(conversaAberta != ''){
                        $('#painelConversa').load(
                            urlBase + 'conversa/ajaxConversa&id=' + conversaAberta,
                            function(){
                                rolarMensagensParaFinal();
                            }
                        );
                    }

                    return;
                }

                $('#erroResponsavel')
                    .removeClass('d-none')
                    .text(retorno.erro || 'Erro ao atribuir conversa.');
            },
            'json'
        ).fail(function(xhr){
            let mensagem = 'Erro de comunicação com o servidor.';

            if(xhr.status == 403){
                mensagem = 'Permissão negada.';
            }

            $('#erroResponsavel')
                .removeClass('d-none')
                .text(mensagem);
        }).always(function(){
            $('#btnSalvarResponsavel').prop('disabled', false);
        });

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

                    atualizarListaConversas(false);

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
                    atualizarListaConversas(false);

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


    function aplicarAlturaConversas(altura)
    {
        const alturaMinima = 420;
        const alturaMaxima = Math.max(alturaMinima, window.innerHeight - 140);

        altura = Math.max(
            alturaMinima,
            Math.min(altura, alturaMaxima)
        );

        $('.conversas-resizable-card').css('height', altura + 'px');

        localStorage.setItem(
            'centralConversasAltura',
            altura
        );
    }

    function iniciarResizeConversas()
    {
        const alturaSalva = parseInt(
            localStorage.getItem('centralConversasAltura'),
            10
        );

        aplicarAlturaConversas(
            isNaN(alturaSalva) ? Math.round(window.innerHeight * 0.75) : alturaSalva
        );

        let redimensionando = false;

        $('#conversasResizeHandle').on('mousedown', function(e){
            redimensionando = true;
            e.preventDefault();
            $('body').addClass('conversas-redimensionando');
        });

        $(document).on('mousemove', function(e){
            if(!redimensionando){
                return;
            }

            const topo = $('#conversasContainer').offset().top;

            aplicarAlturaConversas(
                e.pageY - topo - 8
            );
        });

        $(document).on('mouseup', function(){
            if(!redimensionando){
                return;
            }

            redimensionando = false;
            $('body').removeClass('conversas-redimensionando');
        });
    }

    iniciarResizeConversas();

});

</script>
