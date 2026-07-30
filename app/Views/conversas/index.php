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

            <div class="card-header bg-light d-flex flex-wrap align-items-center justify-content-between">
                <strong class="mr-2 mb-2 mb-sm-0">
                    <i class="fas fa-comments"></i>
                    Conversas
                </strong>

                <div class="d-flex flex-wrap align-items-center ml-sm-auto">
                    <?php if(($usuario['nivel'] ?? null) == 'admin'){ ?>
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-secondary mr-2 mb-2 mb-sm-0"
                            id="btnFerramentasConversas"
                            data-toggle="collapse"
                            data-target="#collapseFerramentasConversas"
                            aria-expanded="false"
                            aria-controls="collapseFerramentasConversas"
                        >
                            <i class="fas fa-tools"></i> Ferramentas
                        </button>
                    <?php } ?>

                    <?php if(!empty($podeNovaConversa)){ ?>
                        <button type="button" class="btn btn-sm btn-success mb-2 mb-sm-0" id="btnNovaConversa">
                            <i class="fas fa-plus"></i> Nova conversa
                        </button>
                    <?php } ?>
                </div>
            </div>

            <?php if(($usuario['nivel'] ?? null) == 'admin'){ ?>
                <div class="collapse" id="collapseFerramentasConversas">
                    <div class="card card-outline card-warning m-2 mb-0">
                        <div class="card-header">
                            <h3 class="card-title">Manutenção de conversas</h3>
                            <div class="card-tools">
                                <button
                                    type="button"
                                    class="btn btn-tool"
                                    data-toggle="collapse"
                                    data-target="#collapseFerramentasConversas"
                                    aria-label="Fechar ferramentas"
                                >
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-2">Identifica duplicidades por Conta Meta + telefone normalizado.</p>
                            <p class="text-warning mb-3"><i class="fas fa-exclamation-triangle"></i> A unificação não é automática e exige confirmação antes da execução.</p>
                            <button type="button" class="btn btn-warning btn-sm" id="btnLocalizarDuplicadas">Localizar conversas duplicadas</button>
                            <div class="table-responsive mt-3">
                                <table class="table table-sm table-bordered d-none" id="tabelaDuplicadas">
                                    <thead>
                                        <tr>
                                            <th>Cliente</th>
                                            <th>Conta Meta</th>
                                            <th>Telefone</th>
                                            <th>Conversas</th>
                                            <th>Não lidas</th>
                                            <th>Última mensagem</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php } ?>

            <div class="border-bottom p-2">

                <input
                    type="text"
                    id="filtroBusca"
                    class="form-control form-control-sm mb-2"
                    placeholder="Buscar nome ou telefone..."
                    value="<?= htmlspecialchars($busca ?? '', ENT_QUOTES, 'UTF-8'); ?>"
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
                                    <?= htmlspecialchars($etq['ETQ_Nome'], ENT_QUOTES, 'UTF-8'); ?>
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
                                    <?= htmlspecialchars($atendente['USU_Nome'], ENT_QUOTES, 'UTF-8'); ?>
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


<?php if(!empty($podeNovaConversa)){ ?>
<div class="modal fade" id="modalNovaConversa" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="formNovaConversa">
                <div class="modal-header">
                    <h5 class="modal-title">Nova conversa por template</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="alert alert-danger d-none" id="erroNovaConversa"></div>
                    <div class="form-group">
                        <label>Número remetente</label>
                        <select name="meta_id" id="novaMetaId" class="form-control" required>
                            <option value="">Selecione...</option>
                            <?php foreach(($contasNovaConversa ?? []) as $conta){ ?>
                                <option value="<?= (int) $conta['MTA_ID']; ?>">
                                    <?= htmlspecialchars(($conta['MTA_Nome'] ?? 'Conta Meta') . ' - ' . ($conta['MTA_NumeroTelefone'] ?? $conta['MTA_PhoneNumberId'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="form-group position-relative">
                        <label>Contato cadastrado</label>
                        <input type="hidden" name="contato_id" id="novaContatoId">
                        <div class="input-group">
                            <input type="text" id="novaContatoBusca" class="form-control" placeholder="Pesquisar contato por nome ou telefone" autocomplete="off">
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-secondary" id="btnLimparContatoNova" title="Limpar contato">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <div id="novaContatoResultados" class="list-group position-absolute w-100 shadow-sm d-none" style="z-index: 1060; max-height: 220px; overflow-y: auto;"></div>
                        <small class="form-text text-muted">Opcional: selecione um contato existente ou preencha nome e telefone manualmente.</small>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Telefone do destinatário</label>
                            <input type="text" name="telefone" id="novaTelefoneDestino" class="form-control telefone-br" placeholder="(41) 99999-9999" required>
                            <small class="form-text text-muted">Informe o telefone com DDD. O código do Brasil (+55) será incluído automaticamente.</small>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Nome do contato</label>
                            <input type="text" name="nome" id="novaNomeContato" class="form-control" placeholder="Nome para novo contato">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Template aprovado</label>
                        <select name="template_id" id="novaTemplateId" class="form-control" required disabled>
                            <option value="">Selecione um número remetente...</option>
                        </select>
                    </div>
                    <div id="novaTemplateVariaveis" class="border rounded p-2 bg-light text-muted">Selecione um template para preencher variáveis.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success" id="btnEnviarNovaConversa">Enviar template</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php } ?>

<div id="conversasResizeHandle" class="conversas-resize-handle" title="Arraste para ajustar a altura">
    <span class="conversas-resize-indicator">
        <i class="fas fa-arrows-alt-v"></i>
    </span>
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
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="form-group">
                        <label>Responsável</label>
                        <select name="responsavel_id" id="responsavelUsuarioId" class="form-control">
                            <option value="">Sem responsável</option>
                            <?php foreach(($atendentes ?? []) as $atendente){ ?>
                                <option value="<?= (int) $atendente['USU_ID']; ?>">
                                    <?= htmlspecialchars($atendente['USU_Nome'], ENT_QUOTES, 'UTF-8'); ?>
                                    (<?= htmlspecialchars($atendente['USU_Nivel'], ENT_QUOTES, 'UTF-8'); ?>)
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

    const csrfTokenConversas =
        '<?= htmlspecialchars(\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>';

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
    let ultimoMetaTemplatesCarregado = '';
    let requisicaoTemplatesNovaConversa = null;
    let timerBuscaContatoNova = null;
    let requisicaoContatosNovaConversa = null;
    let contatoSelecionadoTelefone = '';
    let contatoSelecionadoNome = '';


    function extrairVariaveisComponentes(componentes)
    {
        let vars = [];
        (componentes || []).forEach(function(comp){
            ['text'].forEach(function(campo){
                let texto = comp[campo] || '';
                let match;
                let regex = /{{\s*([^}]+)\s*}}/g;
                while((match = regex.exec(texto)) !== null){
                    if(vars.indexOf(match[1]) === -1){ vars.push(match[1]); }
                }
            });
        });
        return vars;
    }

    function telefoneSemDdiBrasil(valor)
    {
        const digitos = (valor || '').replace(/\D/g, '');
        return digitos.indexOf('55') === 0 && digitos.length > 11 ? digitos.substring(2) : digitos;
    }

    function aplicarMascaraTelefoneBrasileiro()
    {
        const comportamento = function(valor){
            const semDdi = telefoneSemDdiBrasil(valor);
            return semDdi.length > 10 ? '(00) 00000-0000' : '(00) 0000-00009';
        };
        const opcoes = {
            onKeyPress: function(valor, evento, campo, opcoes){
                campo.mask(comportamento.apply({}, arguments), opcoes);
            }
        };
        $('.telefone-br').unmask().mask(comportamento, opcoes);
    }

    $(document).off('input.novaConversaTelefone', '.telefone-br').on('input.novaConversaTelefone', '.telefone-br', function(){
        const digitos = ($(this).val() || '').replace(/\D/g, '');
        if((digitos.length === 12 || digitos.length === 13) && digitos.indexOf('55') === 0){
            $(this).val(digitos.substring(2)).trigger('input');
        }
    });

    function telefoneBrasileiroValido(valor)
    {
        const digitos = (valor || '').replace(/\D/g, '');
        return (digitos.length === 10 || digitos.length === 11 || ((digitos.length === 12 || digitos.length === 13) && digitos.indexOf('55') === 0));
    }


    function limparContatoNovaConversa(limparCampos)
    {
        $('#novaContatoId').val('');
        contatoSelecionadoTelefone = '';
        contatoSelecionadoNome = '';
        $('#novaContatoBusca').val('');
        $('#novaContatoResultados').addClass('d-none').empty();

        if(limparCampos){
            $('#novaNomeContato').val('');
            $('#novaTelefoneDestino').val('').trigger('input');
        }
    }

    function resetarModalNovaConversa()
    {
        $('#formNovaConversa')[0].reset();
        limparContatoNovaConversa(true);
        ultimoMetaTemplatesCarregado = '';
        $('#novaTemplateId').prop('disabled', true).html('<option value="">Selecione um número remetente...</option>');
        $('#novaTemplateVariaveis').html('Selecione um template para preencher variáveis.');
        aplicarMascaraTelefoneBrasileiro();
    }

    function buscarContatosNovaConversa(termo)
    {
        const metaId = $('#novaMetaId').val();
        const resultados = $('#novaContatoResultados');
        termo = (termo || '').trim();

        if(!metaId || termo.length < 2){
            resultados.addClass('d-none').empty();
            return;
        }

        if(requisicaoContatosNovaConversa){
            requisicaoContatosNovaConversa.abort();
        }

        resultados.removeClass('d-none').html('<div class="list-group-item text-muted">Pesquisando...</div>');

        requisicaoContatosNovaConversa = $.getJSON(urlBase + 'conversa/buscarContatosAjax', {
            meta_id: metaId,
            q: termo,
            page: 1
        }, function(retorno){
            resultados.empty();
            if(!retorno.sucesso || !retorno.results || retorno.results.length === 0){
                resultados.html('<div class="list-group-item text-muted">Nenhum contato encontrado.</div>');
                return;
            }

            retorno.results.forEach(function(contato){
                $('<button type="button" class="list-group-item list-group-item-action item-contato-nova"></button>')
                    .attr('data-id', contato.id)
                    .attr('data-nome', contato.nome || '')
                    .attr('data-telefone', contato.telefone || '')
                    .html('<strong>' + $('<div>').text(contato.nome || 'Sem nome').html() + '</strong><br><small>' + $('<div>').text(contato.telefone_formatado || contato.telefone || '').html() + '</small>')
                    .appendTo(resultados);
            });
        }).fail(function(xhr){
            if(xhr.statusText === 'abort'){
                return;
            }
            resultados.html('<div class="list-group-item text-danger">Erro ao pesquisar contatos.</div>');
        }).always(function(){
            requisicaoContatosNovaConversa = null;
        });
    }

    function selecionarRemetenteUnico()
    {
        const select = $('#novaMetaId');
        const opcoesValidas = select.find('option').filter(function(){ return $(this).val() !== ''; });

        if(opcoesValidas.length === 1){
            const valorUnico = opcoesValidas.first().val();
            if(select.val() !== valorUnico){
                select.val(valorUnico).trigger('change');
            }else{
                carregarTemplatesNovaConversa(valorUnico);
            }
        }
    }

    $('#btnNovaConversa').on('click', function(){
        $('#erroNovaConversa').addClass('d-none').text('');
        resetarModalNovaConversa();
        selecionarRemetenteUnico();
        $('#modalNovaConversa').modal('show');
    });

    $('#modalNovaConversa').on('shown.bs.modal', function(){
        aplicarMascaraTelefoneBrasileiro();
        selecionarRemetenteUnico();
    });

    function carregarTemplatesNovaConversa(metaId)
    {
        $('#novaTemplateVariaveis').html('Selecione um template para preencher variáveis.');

        if(!metaId){
            ultimoMetaTemplatesCarregado = '';
            $('#novaTemplateId').prop('disabled', true).html('<option value="">Selecione um número remetente...</option>');
            return;
        }

        if(ultimoMetaTemplatesCarregado === String(metaId)){
            if(requisicaoTemplatesNovaConversa || $('#novaTemplateId option').length > 1){
                return;
            }
        }

        ultimoMetaTemplatesCarregado = String(metaId);
        $('#novaTemplateId').prop('disabled', true).html('<option value="">Carregando...</option>');

        if(requisicaoTemplatesNovaConversa){
            requisicaoTemplatesNovaConversa.abort();
        }

        requisicaoTemplatesNovaConversa = $.getJSON(urlBase + 'conversa/templatesAprovadosAjax', {meta_id: metaId}, function(retorno){
            if(!retorno.sucesso || !retorno.templates.length){
                $('#novaTemplateId').html('<option value="">Nenhum template aprovado nesta conta.</option>');
                return;
            }
            $('#novaTemplateId').html('<option value="">Selecione...</option>');
            retorno.templates.forEach(function(t){
                $('<option>')
                    .val(t.TMP_ID)
                    .text(t.TMP_Nome + ' (' + t.TMP_Idioma + ')')
                    .attr('data-componentes', btoa(unescape(encodeURIComponent(t.TMP_Componentes || '[]'))))
                    .appendTo('#novaTemplateId');
            });
            $('#novaTemplateId').prop('disabled', false);
        }).fail(function(xhr){
            if(xhr.statusText === 'abort'){
                return;
            }
            $('#novaTemplateId').html('<option value="">Erro ao carregar templates.</option>');
        }).always(function(){
            requisicaoTemplatesNovaConversa = null;
        });
    }

    $('#novaMetaId').on('change', function(){
        limparContatoNovaConversa(true);
        carregarTemplatesNovaConversa($(this).val());
    });

    $(document).off('input.novaConversaContato', '#novaContatoBusca').on('input.novaConversaContato', '#novaContatoBusca', function(){
        clearTimeout(timerBuscaContatoNova);
        const termo = $(this).val();
        timerBuscaContatoNova = setTimeout(function(){
            buscarContatosNovaConversa(termo);
        }, 300);
    });

    $(document).off('click.novaConversaContato', '.item-contato-nova').on('click.novaConversaContato', '.item-contato-nova', function(){
        const id = $(this).data('id');
        const nome = $(this).data('nome') || '';
        const telefone = $(this).data('telefone') || '';

        $('#novaContatoId').val(id);
        $('#novaContatoBusca').val($(this).find('strong').text() + ' — ' + $(this).find('small').text());
        $('#novaNomeContato').val(nome);
        $('#novaTelefoneDestino').val(telefone).trigger('input');
        aplicarMascaraTelefoneBrasileiro();
        contatoSelecionadoTelefone = telefoneSemDdiBrasil(telefone);
        contatoSelecionadoNome = nome;
        $('#novaContatoResultados').addClass('d-none').empty();
    });

    $('#btnLimparContatoNova').off('click.novaConversaContato').on('click.novaConversaContato', function(){
        limparContatoNovaConversa(true);
    });

    $('#novaTelefoneDestino').off('input.novaConversaContatoTelefone').on('input.novaConversaContatoTelefone', function(){
        if(contatoSelecionadoTelefone !== '' && telefoneSemDdiBrasil($(this).val()) !== contatoSelecionadoTelefone){
            limparContatoNovaConversa(false);
        }
    });

    $('#novaTemplateId').on('change', function(){
        let option = $(this).find(':selected');
        let componentes = [];
        try{ componentes = JSON.parse(decodeURIComponent(escape(atob(option.data('componentes') || 'W10=')))); }catch(e){}
        let vars = extrairVariaveisComponentes(componentes);
        if(vars.length === 0){
            $('#novaTemplateVariaveis').html('<span class="text-muted">Template sem variáveis.</span>');
            return;
        }
        let html = '<label>Variáveis do template</label>';
        vars.forEach(function(v){
            html += '<div class="form-group mb-2"><label>{{' + $('<div>').text(v).html() + '}}</label>' +
                '<input type="text" class="form-control" name="variaveis[' + $('<div>').text(v).html() + ']" required></div>';
        });
        $('#novaTemplateVariaveis').html(html);
    });

    $('#formNovaConversa').on('submit', function(e){
        e.preventDefault();
        $('#erroNovaConversa').addClass('d-none').text('');

        if(!telefoneBrasileiroValido($('#novaTelefoneDestino').val())){
            $('#erroNovaConversa').removeClass('d-none').text('Informe um telefone brasileiro válido com DDD.');
            return;
        }

        $('#btnEnviarNovaConversa').prop('disabled', true);
        $.post(urlBase + 'conversa/iniciarPorTemplateAjax', $(this).serialize(), function(retorno){
            if(retorno.sucesso){
                $('#modalNovaConversa').modal('hide');
                resetarModalNovaConversa();
                conversaAberta = retorno.conversa_id;
                atualizarListaConversas(true);
                carregarConversa(retorno.conversa_id);
                return;
            }
            $('#erroNovaConversa').removeClass('d-none').text(retorno.erro || 'Erro ao iniciar conversa.');
        }, 'json').fail(function(xhr){
            $('#erroNovaConversa').removeClass('d-none').text(xhr.status == 403 ? 'Permissão negada.' : 'Erro de comunicação.');
        }).always(function(){
            $('#btnEnviarNovaConversa').prop('disabled', false);
        });
    });

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
                + conversaAberta,
            method: 'GET'
        }).done(function(html){
            $('#areaMensagens').html(html);
            rolarMensagensParaFinal();

            if(marcarLida == 'S'){
                marcarConversaComoLida(conversaAberta);
            }
        }).fail(function(xhr){
            if(xhr.status == 403){
                bloquearConversaPorPerdaDeAcesso();
            }
        }).always(function(){
            atualizandoMensagens = false;
        });
    }

    function marcarConversaComoLida(conversaId)
    {
        $.ajax({
            url: urlBase + 'conversa/marcarLidaAjax',
            method: 'POST',
            data: {
                id: conversaId,
                csrf_token: csrfTokenConversas
            }
        }).done(function(){
            marcarConversaComoLidaVisualmente(conversaId);
        }).fail(function(xhr){
            if(xhr.status == 403){
                bloquearConversaPorPerdaDeAcesso();
            }
        });
    }

    function marcarConversaComoLidaVisualmente(conversaId)
    {
        const item = $('#listaConversas .item-conversa[data-id="' + conversaId + '"]');

        item.removeClass('font-weight-bold');
        item.find('.badge-nao-lida').remove();
    }


    function bloquearConversaPorPerdaDeAcesso()
    {
        conversaAberta = '';

        $('#painelConversa').html(
            '<div class="card-body text-center text-muted d-flex align-items-center justify-content-center">' +
                'Esta conversa foi transferida ou não está mais atribuída a você.' +
            '</div>'
        );

        atualizarListaConversas(true);
    }

    function carregarConversa(conversaId)
    {
        $('#painelConversa').html(
            '<div class="card-body text-center text-muted d-flex align-items-center justify-content-center">' +
                '<i class="fas fa-spinner fa-spin mr-2"></i> Carregando conversa...' +
            '</div>'
        );

        $.ajax({
            url:
                urlBase
                + 'conversa/ajaxConversa&id='
                + conversaId
                + '&csrf_token='
                + encodeURIComponent(csrfTokenConversas),
            method: 'GET'
        }).done(function(html){
            $('#painelConversa').html(html);
            rolarMensagensParaFinal();

            $('#listaConversas .item-conversa').removeClass('active');
            $('#listaConversas .item-conversa[data-id="' + conversaAberta + '"]').addClass('active');
            marcarConversaComoLida(conversaId);
        }).fail(function(xhr){
            if(xhr.status == 403){
                bloquearConversaPorPerdaDeAcesso();
            }
        });
    }

    rolarMensagensParaFinal();

    if(conversaAberta != ''){
        marcarConversaComoLida(conversaAberta);
    }
    
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
                ultima: ultimaAtualizacaoConversas,
                conversa_id: conversaAberta
            },

            function(retorno){

                atualizarIndicadoresStatus(retorno.statuses || []);

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

    function atualizarIndicadoresStatus(statuses)
    {
        (statuses || []).forEach(function(status){
            const indicador = $('[data-message-status-id="' + status.id + '"]');
            if(!indicador.length || indicador.attr('data-status') === status.status){ return; }
            indicador.attr('data-status', status.status)
                .attr('title', status.tooltip)
                .attr('aria-label', status.tooltip)
                .removeClass('mensagem-status-pendente mensagem-status-enviada mensagem-status-entregue mensagem-status-lida mensagem-status-falha')
                .addClass(status.classe);
            indicador.find('i').attr('class', 'fas ' + status.icone);
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

            }, 120000);
    }

    document.addEventListener('visibilitychange', function(){

        if(document.hidden){
            if(intervaloAtualizacao){
                clearInterval(intervaloAtualizacao);
                intervaloAtualizacao = null;
            }
            return;
        }

        iniciarAtualizacaoAutomatica();
        verificarAtualizacoes();

    });


    window.addEventListener('pagehide', function(){
        if(intervaloAtualizacao){
            clearInterval(intervaloAtualizacao);
            intervaloAtualizacao = null;
        }
    });

    window.addEventListener('beforeunload', function(){
        if(intervaloAtualizacao){
            clearInterval(intervaloAtualizacao);
            intervaloAtualizacao = null;
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
                form.serialize() + '&csrf_token=' + encodeURIComponent(csrfTokenConversas),

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

        carregarConversa(conversaId);

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
                        carregarConversa(conversaAberta);
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
                conversa_id: conversaId,
                csrf_token: csrfTokenConversas
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
                cor: cor,
                csrf_token: csrfTokenConversas
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


<script>
$(function(){
    $('#collapseFerramentasConversas').on('shown.bs.collapse', function(){
        $('#btnFerramentasConversas').addClass('active').attr('aria-expanded', 'true');
    }).on('hidden.bs.collapse', function(){
        $('#btnFerramentasConversas').removeClass('active').attr('aria-expanded', 'false');
    });

    $('#btnLocalizarDuplicadas').on('click', function(){
        $.get(BASE_URL + '/index.php?url=conversa/duplicadas', function(resp){
            const tabela = $('#tabelaDuplicadas'); const tbody = tabela.find('tbody'); tbody.empty();
            (resp.duplicadas || []).forEach(function(item){
                tbody.append('<tr><td>'+item.CLI_ID+'</td><td>'+item.MTA_ID+'</td><td>'+item.numero_normalizado+'</td><td>'+item.total_conversas+'</td><td>'+item.nao_lidas+'</td><td>'+(item.ultima_mensagem || '-')+'</td><td><button class="btn btn-sm btn-danger js-unificar-duplicada" data-cliente="'+item.CLI_ID+'" data-meta="'+item.MTA_ID+'" data-numero="'+item.numero_normalizado+'">Unificar</button></td></tr>');
            });
            if(!(resp.duplicadas || []).length){ tbody.append('<tr><td colspan="7" class="text-muted text-center">Nenhuma duplicidade encontrada.</td></tr>'); }
            tabela.removeClass('d-none');
        }, 'json');
    });
    $('#tabelaDuplicadas').on('click', '.js-unificar-duplicada', function(){
        if(!confirm('Esta ação moverá mensagens e etiquetas para uma conversa principal e inativará duplicadas. Deseja continuar?')) return;
        const btn = $(this).prop('disabled', true);
        $.post(BASE_URL + '/index.php?url=conversa/unificarDuplicadas', {cliente_id:btn.data('cliente'), meta_id:btn.data('meta'), numero:btn.data('numero'), csrf_token:CSRF_TOKEN}, function(resp){ alert(resp.message || 'Processado.'); $('#btnLocalizarDuplicadas').click(); }, 'json')
            .always(function(){ btn.prop('disabled', false); });
    });
});
</script>
