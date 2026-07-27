<?php use Services\NotificacaoFormatador; use Services\CanalNotificacao; use Services\WhatsAppInstitucionalService; ?>

<div class="row">
    <?php foreach([
        ['Total de notificações', $resumo['total'] ?? 0, 'info', 'fa-bell'],
        ['Enviadas', $resumo['enviadas'] ?? 0, 'success', 'fa-check-circle'],
        ['Pendentes', $resumo['pendentes'] ?? 0, 'warning', 'fa-clock'],
        ['Com erro', $resumo['erros'] ?? 0, 'danger', 'fa-exclamation-triangle'],
        ['Enviadas hoje', $resumo['enviadas_hoje'] ?? 0, 'primary', 'fa-calendar-day'],
    ] as $card){ ?>
    <div class="col-lg col-md-4 col-6">
        <div class="small-box bg-<?= $card[2]; ?>">
            <div class="inner"><h3><?= number_format((int) $card[1], 0, ',', '.'); ?></h3><p><?= htmlspecialchars($card[0]); ?></p></div>
            <div class="icon"><i class="fas <?= $card[3]; ?>"></i></div>
        </div>
    </div>
    <?php } ?>
</div>
<p class="text-muted">Os cards acima exibem totais gerais. A tabela abaixo pode ser filtrada para auditoria e suporte.</p>

<div class="card card-outline card-primary">
    <div class="card-header"><h3 class="card-title">Notificações</h3></div>
    <div class="card-body">
        <p class="text-muted">Acompanhe as comunicações enviadas pelo sistema, seus canais, tentativas e eventuais falhas.</p>
        <div class="row mb-3">
            <div class="col-md-2 mb-2"><input type="number" min="1" id="filtroCliente" class="form-control" placeholder="ID do cliente"></div>
            <div class="col-md-2 mb-2"><select id="filtroEvento" class="form-control"><option value="">Evento</option><?php foreach($eventos as $evento){ ?><option value="<?= htmlspecialchars($evento); ?>"><?= htmlspecialchars(NotificacaoFormatador::evento($evento)); ?></option><?php } ?></select></div>
            <div class="col-md-2 mb-2"><select id="filtroCanal" class="form-control"><option value="">Canal</option><?php foreach($canais as $canal){ ?><option value="<?= htmlspecialchars($canal); ?>"><?= htmlspecialchars(NotificacaoFormatador::canal($canal)); ?></option><?php } ?></select></div>
            <div class="col-md-2 mb-2"><select id="filtroStatus" class="form-control"><option value="">Status</option><option value="enviada">Enviada</option><option value="pendente">Pendente</option><option value="erro">Erro</option><option value="lida">Lida</option><option value="cancelada">Cancelada</option></select></div>
            <div class="col-md-2 mb-2"><input type="date" id="filtroDataInicial" class="form-control" title="Data inicial"></div>
            <div class="col-md-2 mb-2"><input type="date" id="filtroDataFinal" class="form-control" title="Data final"></div>
            <div class="col-md-3 mb-2"><input type="text" id="filtroDestino" class="form-control" placeholder="Destino"></div>
            <div class="col-md-5 mb-2"><input type="text" id="filtroTexto" class="form-control" placeholder="Texto livre"></div>
            <div class="col-md-4 mb-2"><button type="button" id="btnFiltrar" class="btn btn-primary">Filtrar</button> <button type="button" id="btnLimparFiltros" class="btn btn-outline-secondary">Limpar filtros</button></div>
        </div>
        <table id="tabelaNotificacoes" class="table table-bordered table-striped table-sm w-100">
            <thead><tr><th>Data</th><th>Cliente</th><th>Evento</th><th>Canal</th><th>Destino</th><th>Assunto</th><th>Status</th><th>Tentativas</th><th>Última atualização</th><th>Ações</th></tr></thead>
            <tbody><tr><td colspan="10" class="text-center text-muted">Nenhuma notificação foi registrada até o momento. As notificações aparecerão aqui após eventos como cadastro de clientes, conexão da conta Meta, avisos de trial e pagamentos.</td></tr></tbody>
        </table>
    </div>
</div>

<div class="card card-outline card-secondary">
    <div class="card-header"><h3 class="card-title">Configuração de canais</h3></div>
    <div class="card-body">
        <p class="text-muted">A configuração abaixo sobrescreve o padrão do arquivo quando salva no banco. Somente canais implementados podem ser ativados.</p>
        <form id="formConfiguracaoNotificacoes" method="post">
            <div class="table-responsive">
                <table class="table table-bordered table-sm">
                    <thead><tr><th>Evento</th><?php foreach($canais as $canal){ ?><th><?= htmlspecialchars(NotificacaoFormatador::canal($canal)); ?></th><?php } ?><th>Template WhatsApp</th><th>Modelo de e-mail</th><th>Ações</th></tr></thead>
                    <tbody>
                        <?php foreach($matrizConfiguracao as $linha){ ?>
                        <tr>
                            <td><?= htmlspecialchars(NotificacaoFormatador::evento($linha['evento'])); ?></td>
                            <?php foreach($linha['canais'] as $canal => $cfg){ ?>
                                <td>
                                    <?php if(!empty($cfg['implementado']) && in_array($canal, [CanalNotificacao::EMAIL, CanalNotificacao::WHATSAPP], true)){ ?>
                                        <label class="mb-0"><input type="checkbox" name="<?= htmlspecialchars($canal); ?>[]" value="<?= htmlspecialchars($linha['evento']); ?>" <?= !empty($cfg['ativo']) ? 'checked' : ''; ?>> Ativo</label>
                                    <?php }else{ ?>
                                        <span class="badge badge-secondary">Em breve</span>
                                    <?php } ?>
                                </td>
                            <?php } ?>
                            <td><code><?= htmlspecialchars(WhatsAppInstitucionalService::template($linha['evento']) ?: '-'); ?></code></td>
                            <td><span class="badge badge-<?= !empty($modelosPersonalizados[$linha['evento']]) ? 'info' : 'light'; ?>"><?= !empty($modelosPersonalizados[$linha['evento']]) ? 'Personalizado' : 'Modelo padrão'; ?></span></td>
                            <td><button type="button" class="btn btn-sm btn-outline-primary js-editar-modelo" data-evento="<?= htmlspecialchars($linha['evento']); ?>" data-canal="email" title="Editar modelo da notificação"><i class="fas fa-edit"></i> Editar</button></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <button type="submit" class="btn btn-primary">Salvar configuração</button>
        </form>
    </div>
</div>

<div class="modal fade" id="modalDetalheNotificacao" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Detalhes da notificação</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
        <div class="modal-body" id="conteudoDetalheNotificacao"><p class="text-muted">Carregando...</p></div>
    </div></div>
</div>



<div class="modal fade" id="modalModeloNotificacao" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl" role="document"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Editar modelo da notificação</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
        <div class="modal-body">
            <form id="formModeloNotificacao">
                <input type="hidden" name="evento" id="modeloEvento"><input type="hidden" name="canal" id="modeloCanal" value="email">
                <p><strong>Evento:</strong> <span id="modeloEventoLabel"></span> · <strong>Canal:</strong> <span id="modeloCanalLabel"></span></p>
                <p><strong>Modelo atual:</strong> <span id="modeloEstado" class="badge badge-light">Padrão do sistema</span></p>
                <div class="form-group"><label>Assunto</label><input name="assunto" id="modeloAssunto" class="form-control" maxlength="190" required></div>
                <div class="form-group"><label>Título</label><input name="titulo" id="modeloTitulo" class="form-control" maxlength="190" required></div>
                <div class="form-group"><label>Corpo da mensagem</label><textarea name="corpo" id="modeloCorpo" class="form-control" rows="8" maxlength="8000" required></textarea><small class="form-text text-muted">Permitido: parágrafos, quebras, negrito, itálico, listas e links. Scripts, iframes e eventos HTML serão removidos.</small></div>
                <div class="row"><div class="col-md-6"><div class="form-group"><label>Texto do botão</label><input name="botao" id="modeloBotao" class="form-control" maxlength="80"></div></div><div class="col-md-6"><div class="form-group"><label>Link do botão</label><input name="link" id="modeloLink" class="form-control" maxlength="255"></div></div></div>
                <div class="form-group"><label>Variáveis disponíveis</label><div id="modeloVariaveis" class="border rounded p-2 bg-light"></div></div>
            </form>
            <div id="modeloPreview" class="border rounded p-2 d-none"><h6>Pré-visualização</h6><p><strong>Assunto:</strong> <span id="previewAssunto"></span></p><iframe id="previewFrame" sandbox="" style="width:100%;min-height:420px;border:1px solid #ddd"></iframe></div>
        </div>
        <div class="modal-footer"><button type="button" id="btnRestaurarModelo" class="btn btn-outline-danger d-none">Restaurar padrão</button><button type="button" id="btnPreviewModelo" class="btn btn-info">Visualizar</button><button type="button" id="btnSalvarModelo" class="btn btn-primary">Salvar</button></div>
    </div></div>
</div>

<script>
$(function(){
    const tabela = $('#tabelaNotificacoes').DataTable({
        processing: true,
        serverSide: true,
        order: [[0, 'desc']],
        ajax: {
            url: BASE_URL + '/index.php?url=notificacao/dados',
            data: function(d){
                d.cliente_id = $('#filtroCliente').val(); d.evento = $('#filtroEvento').val(); d.canal = $('#filtroCanal').val(); d.status = $('#filtroStatus').val();
                d.data_inicial = $('#filtroDataInicial').val(); d.data_final = $('#filtroDataFinal').val(); d.destino = $('#filtroDestino').val(); d.q = $('#filtroTexto').val();
            }
        },
        columns: [{data:'data'},{data:'cliente'},{data:'evento'},{data:'canal'},{data:'destino'},{data:'assunto'},{data:'status'},{data:'tentativas'},{data:'atualizado'},{data:'acoes', orderable:false, searchable:false}],
        language: { url: 'https://cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json' },
        drawCallback: function(settings){
            if(settings.json && settings.json.recordsFiltered === 0){
                $('#tabelaNotificacoes tbody').html('<tr><td colspan="10" class="text-center text-muted">Nenhuma notificação foi registrada até o momento. As notificações aparecerão aqui após eventos como cadastro de clientes, conexão da conta Meta, avisos de trial e pagamentos.</td></tr>');
            }
        }
    });

    $('#btnFiltrar').on('click', function(){ tabela.ajax.reload(); });
    $('#btnLimparFiltros').on('click', function(){ $('#filtroCliente,#filtroEvento,#filtroCanal,#filtroStatus,#filtroDataInicial,#filtroDataFinal,#filtroDestino,#filtroTexto').val(''); tabela.ajax.reload(); });
    $('#tabelaNotificacoes').on('click', '.js-detalhe', function(){
        $('#conteudoDetalheNotificacao').html('<p class="text-muted">Carregando...</p>'); $('#modalDetalheNotificacao').modal('show');
        $.get(BASE_URL + '/index.php?url=notificacao/detalhe', {id: $(this).data('id')}, function(resp){ $('#conteudoDetalheNotificacao').html(resp.html || '<p class="text-danger">Não foi possível carregar.</p>'); }, 'json');
    });
    $('#tabelaNotificacoes').on('click', '.js-reenviar', function(){
        if(!confirm('Esta ação enviará novamente a notificação para o destinatário. Deseja continuar?')) return;
        const btn = $(this); btn.prop('disabled', true).text('Reenviando...');
        $.post(BASE_URL + '/index.php?url=notificacao/reenviar', {id: btn.data('id'), csrf_token: CSRF_TOKEN}, function(resp){ alert(resp.message || 'Processado.'); tabela.ajax.reload(null, false); }, 'json')
            .fail(function(xhr){ alert((xhr.responseJSON && xhr.responseJSON.message) || 'Não foi possível reenviar.'); })
            .always(function(){ btn.prop('disabled', false).text('Reenviar'); });
    });

    let modeloPersonalizadoAtual = false;
    function dadosModelo(){ return $('#formModeloNotificacao').serialize() + '&csrf_token=' + encodeURIComponent(CSRF_TOKEN); }
    $('#formModeloNotificacao').on('click', '.js-var', function(){ const campo = $('#modeloCorpo'); campo.val(campo.val() + ' ' + $(this).data('var')); campo.focus(); });
    $('#formConfiguracaoNotificacoes').on('click', '.js-editar-modelo', function(){
        const evento = $(this).data('evento');
        $.get(BASE_URL + '/index.php?url=notificacao/modelo', {evento: evento, canal: 'email'}, function(resp){
            const m = resp.modelo; modeloPersonalizadoAtual = !!m.personalizado;
            $('#modeloEvento').val(m.evento); $('#modeloCanal').val(m.canal); $('#modeloEventoLabel').text(m.evento_label); $('#modeloCanalLabel').text(m.canal_label);
            $('#modeloAssunto').val(m.assunto); $('#modeloTitulo').val(m.titulo); $('#modeloCorpo').val(m.corpo); $('#modeloBotao').val(m.botao); $('#modeloLink').val(m.link);
            $('#modeloEstado').text(m.estado).toggleClass('badge-info', modeloPersonalizadoAtual).toggleClass('badge-light', !modeloPersonalizadoAtual);
            $('#btnRestaurarModelo').toggleClass('d-none', !modeloPersonalizadoAtual); $('#modeloPreview').addClass('d-none');
            let html = ''; Object.keys(m.variaveis || {}).forEach(function(v){ html += '<button type="button" class="btn btn-xs btn-outline-secondary mr-1 mb-1 js-var" data-var="'+v+'"><code>'+v+'</code> '+m.variaveis[v]+'</button>'; });
            $('#modeloVariaveis').html(html || '<span class="text-muted">Nenhuma variável configurada.</span>'); $('#modalModeloNotificacao').modal('show');
        }, 'json');
    });
    $('#btnPreviewModelo').on('click', function(){ $.post(BASE_URL + '/index.php?url=notificacao/previewModelo', dadosModelo(), function(resp){ $('#previewAssunto').text(resp.assunto); document.getElementById('previewFrame').srcdoc = resp.html; $('#modeloPreview').removeClass('d-none'); }, 'json').fail(function(xhr){ alert((xhr.responseJSON && xhr.responseJSON.message) || 'Não foi possível gerar prévia.'); }); });
    $('#btnSalvarModelo').on('click', function(){ $.post(BASE_URL + '/index.php?url=notificacao/salvarModelo', dadosModelo(), function(resp){ alert(resp.message || 'Modelo salvo.'); location.reload(); }, 'json').fail(function(xhr){ alert((xhr.responseJSON && xhr.responseJSON.message) || 'Não foi possível salvar modelo.'); }); });
    $('#btnRestaurarModelo').on('click', function(){ if(!confirm('Esta ação removerá a personalização e o sistema voltará a utilizar o modelo padrão. Deseja continuar?')) return; $.post(BASE_URL + '/index.php?url=notificacao/restaurarModelo', {evento: $('#modeloEvento').val(), canal: 'email', csrf_token: CSRF_TOKEN}, function(resp){ alert(resp.message || 'Modelo restaurado.'); location.reload(); }, 'json'); });

    $('#formConfiguracaoNotificacoes').on('submit', function(e){
        e.preventDefault();
        $.post(BASE_URL + '/index.php?url=notificacao/salvarConfiguracao', $(this).serialize() + '&csrf_token=' + encodeURIComponent(CSRF_TOKEN), function(resp){ alert(resp.message || 'Configuração salva.'); }, 'json')
            .fail(function(xhr){ alert((xhr.responseJSON && xhr.responseJSON.message) || 'Não foi possível salvar a configuração.'); });
    });
});
</script>
