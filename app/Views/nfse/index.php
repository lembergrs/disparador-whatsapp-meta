<?php
use Core\Csrf;

$cobrancasElegiveisJson = json_encode(
    $cobrancasElegiveisPorCliente ?? [],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
);

function nfse_e($valor){ return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8'); }
function nfse_data($valor){ return !empty($valor) ? date('d/m/Y', strtotime((string) $valor)) : '-'; }
function nfse_data_hora($valor){ return !empty($valor) ? date('d/m/Y H:i', strtotime((string) $valor)) : '-'; }
function nfse_money($valor){ return 'R$ ' . number_format((float) $valor, 2, ',', '.'); }
function nfse_short($valor){
    $valor = (string) ($valor ?? '');
    if($valor === ''){ return '-'; }
    return strlen($valor) > 18 ? substr($valor, 0, 8) . '...' . substr($valor, -6) : $valor;
}
function nfse_badge($status){
    $classes = [
        'emitida' => 'success',
        'erro_temporario' => 'danger',
        'erro_definitivo' => 'danger',
        'pendente' => 'warning',
        'processando' => 'info',
        'reconciliacao_pendente' => 'warning',
        'pendente_dados' => 'orange',
        'cancelada' => 'secondary',
        'cancelamento_pendente' => 'secondary'
    ];
    $labels = [
        'emitida' => 'Emitida',
        'erro_temporario' => 'Erro temporário',
        'erro_definitivo' => 'Erro definitivo',
        'pendente' => 'Pendente',
        'processando' => 'Processando',
        'reconciliacao_pendente' => 'Reconciliação',
        'pendente_dados' => 'Pendente Dados',
        'cancelada' => 'Cancelada',
        'cancelamento_pendente' => 'Cancelamento'
    ];
    $class = $classes[$status] ?? 'secondary';
    return '<span class="badge badge-' . $class . '">' . nfse_e($labels[$status] ?? $status) . '</span>';
}
function nfse_doc($valor){
    $valor = (string) ($valor ?? '');
    if($valor === ''){ return '<span class="text-muted">-</span>'; }
    return '<code>' . nfse_e(nfse_short($valor)) . '</code> <button type="button" class="btn btn-xs btn-outline-secondary nfse-copy" data-copy="' . nfse_e($valor) . '">Copiar</button>';
}

function nfse_detail_item($label, $html){
    if(trim(strip_tags((string) $html)) === '' || trim(strip_tags((string) $html)) === '-'){ return ''; }
    return '<dt class="col-sm-4">' . nfse_e($label) . '</dt><dd class="col-sm-8">' . $html . '</dd>';
}
function nfse_timeline(array $emissao){
    $eventos = [];
    if(!empty($emissao['NFE_DataCriacao'])){ $eventos[] = ['Emissão iniciada', $emissao['NFE_DataCriacao'], 'primary']; }
    if(!empty($emissao['NFE_DataReserva']) || !empty($emissao['NFE_NumDps'])){ $eventos[] = ['DPS reservada', $emissao['NFE_DataReserva'] ?? $emissao['NFE_DataAtualizacao'] ?? null, 'info']; }
    if(!empty($emissao['NFE_ChaveDps'])){ $eventos[] = ['XML assinado', $emissao['NFE_DataEmissao'] ?? $emissao['NFE_DataAtualizacao'] ?? null, 'info']; }
    if(!empty($emissao['NFE_DataEmissao'])){ $eventos[] = ['NFS-e emitida', $emissao['NFE_DataEmissao'], 'success']; }
    if(!empty($emissao['NFE_RequestIdConsulta'])){ $eventos[] = ['Consulta executada', $emissao['NFE_DataAtualizacao'] ?? null, 'secondary']; }
    if(!empty($emissao['NFE_PdfStoragePath'])){ $eventos[] = ['PDF armazenado', $emissao['NFE_DataAtualizacao'] ?? null, 'success']; }
    if(!empty($emissao['NFE_XmlStoragePath'])){ $eventos[] = ['XML armazenado', $emissao['NFE_DataAtualizacao'] ?? null, 'success']; }
    if(!empty($emissao['NFE_DataCancelamento'])){ $eventos[] = ['Cancelada', $emissao['NFE_DataCancelamento'], 'dark']; }
    $html = '<div class="nfse-timeline">';
    foreach($eventos as $evento){
        $html .= '<div class="nfse-timeline-item"><span class="badge badge-' . $evento[2] . '">' . nfse_e($evento[0]) . '</span> <small class="text-muted">' . nfse_data_hora($evento[1]) . '</small></div>';
    }
    return $html . '</div>';
}

function nfse_aptidao_item($campo, $pendencias){
    $faltante = in_array($campo, $pendencias, true) || in_array($campo . '_valido', $pendencias, true);
    return '<li class="list-inline-item mr-3">' . ($faltante ? '<span class="text-danger">✗</span>' : '<span class="text-success">✓</span>') . ' ' . nfse_e(ucwords(str_replace('_', ' ', $campo))) . '</li>';
}

$nfseConfigPublica = is_array($nfseConfigPublica ?? null) ? $nfseConfigPublica : [];
$nfseCodigoConfigurado = !empty($nfseConfigPublica['codigo_tributacao_configurado']);
$nfseDescricaoConfigurada = !empty($nfseConfigPublica['descricao_servico_configurada']);
$nfseConfigFiscalCompleta = $nfseCodigoConfigurado && $nfseDescricaoConfigurada;
$nfseFiscalPreview = is_array($nfseFiscalPreview ?? null) ? $nfseFiscalPreview : [];
$nfseCodigoPreview = trim((string) ($nfseFiscalPreview['codigo_tributacao_nacional'] ?? ''));
$nfseDescricaoPreview = trim((string) ($nfseFiscalPreview['descricao_servico'] ?? ''));
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0">Painel operacional de NFS-e</h3>
        <span class="badge badge-info">Emissão manual homologada</span>
    </div>
    <div class="card-body py-2">
        <div class="alert alert-warning py-2 mb-2">
            Esta tela é exclusiva para administradores. A emissão é manual, não aciona webhook, Worker, retry automático, e-mail ou download pelo cliente.
        </div>

        <?php if(!$nfseConfigFiscalCompleta){ ?>
            <div class="alert alert-danger">
                <strong>⚠ Configuração fiscal incompleta</strong><br>
                <?php if(!$nfseCodigoConfigurado){ ?>Código tributário não configurado.<br><?php } ?>
                <?php if(!$nfseDescricaoConfigurada){ ?>Descrição do serviço não configurada.<br><?php } ?>
                A emissão manual ficará desabilitada até a configuração fiscal ser concluída no ambiente.
            </div>
        <?php } ?>

        <div class="alert alert-info py-2 mb-3">
            <strong>Prévia fiscal para conferência:</strong><br>
            Código tributário: <?= $nfseCodigoConfigurado ? nfse_e($nfseCodigoPreview) : '<span class="text-muted">Não configurado</span>'; ?><br>
            Descrição do serviço: <?= $nfseDescricaoConfigurada ? nfse_e($nfseDescricaoPreview) : '<span class="text-muted">Não configurada</span>'; ?>
        </div>

        <form method="post" action="<?= BASE_URL; ?>/index.php?url=nfse/emitir" class="mb-3">
            <?= Csrf::input(); ?>
            <div class="row">
                <div class="col-md-5">
                    <label for="nfse_cliente_id">Cliente PJ</label>
                    <select name="cliente_id" id="nfse_cliente_id" class="form-control" required>
                        <option value="">Selecione</option>
                        <?php foreach($clientes as $cliente){ ?>
                            <option value="<?= (int) $cliente['CLI_ID']; ?>">
                                #<?= (int) $cliente['CLI_ID']; ?> - <?= nfse_e($cliente['CLI_Nome'] ?? ''); ?>
                            </option>
                        <?php } ?>
                    </select>
                    <div id="nfse_aptidao" class="small text-muted mt-2">Selecione um cliente para conferir pendências fiscais.</div>
                </div>
                <div class="col-md-5">
                    <label for="nfse_cobranca_id">Cobrança</label>
                    <select name="cobranca_id" id="nfse_cobranca_id" class="form-control" required disabled>
                        <option value="">Selecione um cliente primeiro</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" id="nfse_emitir_btn" class="btn btn-primary btn-block" data-config-fiscal-completa="<?= $nfseConfigFiscalCompleta ? '1' : '0'; ?>" disabled onclick="return confirm('Esta ação emitirá uma NFS-e real no ambiente configurado. Confirma a emissão manual desta cobrança paga?');">
                        Emitir manualmente
                    </button>
                </div>
            </div>
            <small class="form-text text-muted">A aptidão fiscal, o vínculo cliente/cobrança e o status pago serão validados novamente no servidor antes de qualquer chamada à API.</small>
        </form>

        <form method="get" action="<?= BASE_URL; ?>/index.php" class="form-inline mb-3">
            <input type="hidden" name="url" value="nfse">
            <label class="mr-2" for="status">Status</label>
            <select name="status" id="status" class="form-control mr-2">
                <option value="">Todos</option>
                <?php foreach($statusPermitidos as $status){ ?>
                    <option value="<?= nfse_e($status); ?>" <?= $statusFiltro === $status ? 'selected' : ''; ?>><?= nfse_e($status); ?></option>
                <?php } ?>
            </select>
            <button class="btn btn-secondary" type="submit">Filtrar</button>
        </form>

        <div class="table-responsive">
            <table class="table table-striped table-sm table-hover" id="nfse-table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Cliente</th>
                        <th>Cobrança</th>
                        <th>Valor</th>
                        <th>Status</th>
                        <th>Documento</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($emissoes as $emissao){
                        $modalId = 'nfse-detalhes-' . (int) $emissao['NFE_ID'];
                        $status = $emissao['NFE_Status'] ?? '';
                        $temChave = !empty($emissao['NFE_ChaveAcesso']);
                        $temPdf = !empty($emissao['NFE_PdfStoragePath']);
                        $temXml = !empty($emissao['NFE_XmlStoragePath']);
                    ?>
                        <tr class="nfse-master-row">
                            <td class="text-nowrap"><button type="button" class="btn btn-xs btn-link nfse-toggle-row" aria-label="Expandir linha">+</button> <?= nfse_data($emissao['NFE_DataEmissao'] ?? $emissao['NFE_DataCriacao'] ?? null); ?></td>
                            <td class="text-nowrap">
                                <strong><?= nfse_e($emissao['CLI_Nome'] ?? 'Cliente não identificado'); ?></strong><br>
                                <small class="text-muted">CLI_ID <?= (int) $emissao['CLI_ID']; ?></small>
                            </td>
                            <td>
                                <strong>Cobrança #<?= (int) $emissao['COB_ID']; ?></strong><br>
                                <small class="text-muted"><?= nfse_money($emissao['COB_Valor'] ?? $emissao['NFE_ValorFiscal'] ?? 0); ?> · Pago em <?= nfse_data($emissao['COB_DataPagamento'] ?? null); ?></small>
                            </td>
                            <td><?= nfse_money($emissao['NFE_ValorFiscal'] ?? $emissao['COB_Valor'] ?? 0); ?></td>
                            <td><?= nfse_badge($status); ?></td>
                            <td>
                                <div><small class="text-muted">Chave</small><br><?= nfse_doc($emissao['NFE_ChaveAcesso'] ?? ''); ?></div>
                                <div class="mt-1"><small class="text-muted">RequestId</small><br><?= nfse_doc($emissao['NFE_RequestIdEmissao'] ?? ''); ?></div>
                            </td>
                            <td class="text-nowrap">
                                <div class="dropdown nfse-actions-dropdown">
                                    <button class="btn btn-xs btn-outline-secondary dropdown-toggle" type="button" data-toggle="dropdown">Ações</button>
                                    <div class="dropdown-menu dropdown-menu-right">
                                        <button type="button" class="dropdown-item" data-toggle="modal" data-target="#<?= $modalId; ?>">Detalhes</button>
                                        <?php if($temPdf){ ?><a class="dropdown-item" href="<?= BASE_URL; ?>/index.php?url=nfse/pdf/<?= (int) $emissao['NFE_ID']; ?>">PDF</a><?php } ?>
                                        <?php if($temXml){ ?><a class="dropdown-item" href="<?= BASE_URL; ?>/index.php?url=nfse/xml/<?= (int) $emissao['NFE_ID']; ?>">XML</a><?php } ?>
                                        <?php if($temChave && $status !== 'cancelada'){ ?><form method="post" action="<?= BASE_URL; ?>/index.php?url=nfse/reconsultar"><?= Csrf::input(); ?><input type="hidden" name="nfse_id" value="<?= (int) $emissao['NFE_ID']; ?>"><button type="submit" class="dropdown-item">Reconsultar</button></form><?php } ?>
                                        <?php if($status === 'emitida' && $temChave){ ?><button type="button" class="dropdown-item text-danger" data-toggle="modal" data-target="#cancelar-<?= (int) $emissao['NFE_ID']; ?>">Cancelar</button><?php } ?>
                                        <?php if($status === 'cancelada'){ ?><form method="post" action="<?= BASE_URL; ?>/index.php?url=nfse/emitir"><?= Csrf::input(); ?><input type="hidden" name="cliente_id" value="<?= (int) $emissao['CLI_ID']; ?>"><input type="hidden" name="cobranca_id" value="<?= (int) $emissao['COB_ID']; ?>"><button type="submit" class="dropdown-item text-primary" onclick="return confirm('Emitir nova NFS-e para esta cobrança mantendo a cancelada no histórico?');">Emitir nova NFS-e</button></form><?php } ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="nfse-child-row d-none"><td colspan="7">
                            <div class="p-2 bg-light border rounded">
                                <div class="row">
                                    <div class="col-md-8"><strong>DPS:</strong> <?= nfse_e($emissao['NFE_NumDps'] ?? '-'); ?> · <strong>NFS-e:</strong> <?= nfse_e($emissao['NFE_NumeroNota'] ?? '-'); ?> · <strong>Datas:</strong> <?= nfse_data_hora($emissao['NFE_DataEmissao'] ?? $emissao['NFE_DataCriacao'] ?? null); ?><br><strong>Último retorno:</strong> <?= nfse_e(!empty($emissao['NFE_RetornoSanitizado']) ? 'retorno armazenado' : 'sem retorno'); ?> · <strong>Último erro:</strong> <?= nfse_e($emissao['NFE_UltimoErroMensagem'] ?? 'sem erro'); ?></div>
                                    <div class="col-md-4 text-right"><?php if($temPdf){ ?><a class="btn btn-xs btn-outline-primary" href="<?= BASE_URL; ?>/index.php?url=nfse/pdf/<?= (int) $emissao['NFE_ID']; ?>">PDF</a><?php } ?> <?php if($temXml){ ?><a class="btn btn-xs btn-outline-success" href="<?= BASE_URL; ?>/index.php?url=nfse/xml/<?= (int) $emissao['NFE_ID']; ?>">XML</a><?php } ?> <?= nfse_doc($emissao['NFE_ChaveAcesso'] ?? ''); ?> <?php if($status === 'emitida' && $temChave){ ?><button type="button" class="btn btn-xs btn-outline-danger" data-toggle="modal" data-target="#cancelar-<?= (int) $emissao['NFE_ID']; ?>">Cancelar</button><?php } ?></div>
                                </div>
                            </div>
                        </td></tr>
                    <?php } ?>
                    <?php if(empty($emissoes)){ ?>
                        <tr><td colspan="7" class="text-center text-muted">Nenhuma emissão local encontrada.</td></tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php foreach($emissoes as $emissao){
    $modalId = 'nfse-detalhes-' . (int) $emissao['NFE_ID'];
    $pendencias = [];
?>
<div class="modal fade" id="<?= $modalId; ?>" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Detalhes da NFS-e #<?= (int) $emissao['NFE_ID']; ?></h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
        <div class="modal-body">
            <h6 class="text-muted">Documento Fiscal</h6><dl class="row mb-3">
                <?= nfse_detail_item('Status', nfse_badge($emissao['NFE_Status'] ?? '')); ?>
                <?= nfse_detail_item('Número NFS-e', nfse_e($emissao['NFE_NumeroNota'] ?? '')); ?>
                <?= nfse_detail_item('Chave', nfse_doc($emissao['NFE_ChaveAcesso'] ?? '')); ?>
            </dl>
            <h6 class="text-muted">DPS</h6><dl class="row mb-3">
                <?= nfse_detail_item('Número DPS', nfse_e($emissao['NFE_NumDps'] ?? '')); ?>
                <?= nfse_detail_item('RequestId emissão', nfse_doc($emissao['NFE_RequestIdEmissao'] ?? '')); ?>
            </dl>
            <h6 class="text-muted">Datas</h6><dl class="row mb-3">
                <?= nfse_detail_item('Data emissão', nfse_data_hora($emissao['NFE_DataEmissao'] ?? null)); ?>
                <?= nfse_detail_item('Data cancelamento', nfse_data_hora($emissao['NFE_DataCancelamento'] ?? null)); ?>
            </dl>
            <h6 class="text-muted">Arquivos</h6><dl class="row mb-3">
                <?= !empty($emissao['NFE_PdfStoragePath']) ? nfse_detail_item('PDF', '<span class="badge badge-success">armazenado</span>') : ''; ?>
                <?= !empty($emissao['NFE_XmlStoragePath']) ? nfse_detail_item('XML', '<span class="badge badge-success">armazenado</span>') : ''; ?>
            </dl>
            <h6 class="text-muted">Retorno</h6><dl class="row mb-3">
                <?= nfse_detail_item('Último retorno', !empty($emissao['NFE_RetornoSanitizado']) ? '<span class="text-muted">retorno sanitizado armazenado</span>' : ''); ?>
                <?= nfse_detail_item('Último erro', nfse_e($emissao['NFE_UltimoErroMensagem'] ?? '')); ?>
            </dl>
            <h6 class="text-muted">Timeline da emissão</h6>
            <?= nfse_timeline($emissao); ?>
        </div>
    </div></div>
</div>

<div class="modal fade" id="cancelar-<?= (int) $emissao['NFE_ID']; ?>" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document"><div class="modal-content">
        <form method="post" action="<?= BASE_URL; ?>/index.php?url=nfse/cancelar">
            <?= Csrf::input(); ?><input type="hidden" name="nfse_id" value="<?= (int) $emissao['NFE_ID']; ?>">
            <div class="modal-header"><h5 class="modal-title">Cancelar NFS-e #<?= (int) $emissao['NFE_ID']; ?></h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
            <div class="modal-body">
                <div class="alert alert-danger">Cancelamento é ação fiscal real e exige confirmação administrativa explícita.</div>
                <label>Código motivo</label>
                <select name="codigo_motivo" class="form-control mb-2" required>
                    <option value="9">9 — Outros</option><option value="1">1 — Desenquadramento do Simples Nacional</option><option value="2">2 — Enquadramento no Simples Nacional</option><option value="3">3 — Inclusão retroativa de imunidade/isenção</option><option value="4">4 — Exclusão retroativa de imunidade/isenção</option><option value="5">5 — Rejeição pelo tomador/intermediário</option>
                </select>
                <label>Descrição</label>
                <textarea name="motivo" class="form-control" maxlength="255" required></textarea>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button><button type="submit" class="btn btn-danger" onclick="return confirm('Confirmar cancelamento fiscal real desta NFS-e?');">Confirmar cancelamento</button></div>
        </form>
    </div></div>
</div>
<?php } ?>

<script>
document.addEventListener('DOMContentLoaded', function(){
    var cobrancasPorCliente = <?= $cobrancasElegiveisJson ?: '{}'; ?>;
    var clienteSelect = document.getElementById('nfse_cliente_id');
    var cobrancaSelect = document.getElementById('nfse_cobranca_id');
    var emitirBtn = document.getElementById('nfse_emitir_btn');
    var aptidaoBox = document.getElementById('nfse_aptidao');
    var form = clienteSelect ? clienteSelect.closest('form') : null;

    function limparCobrancas(mensagem){
        cobrancaSelect.innerHTML = '';
        var option = document.createElement('option');
        option.value = '';
        option.textContent = mensagem || 'Selecione';
        cobrancaSelect.appendChild(option);
        cobrancaSelect.value = '';
        cobrancaSelect.disabled = true;
        atualizarEstadoBotaoEmissao();
    }

    function atualizarCobrancasPorCliente(){
        if(!clienteSelect || !cobrancaSelect){ return; }
        var clienteId = String(clienteSelect.value || '');
        limparCobrancas(clienteId === '' ? 'Selecione um cliente primeiro' : 'Selecione');
        if(aptidaoBox){ aptidaoBox.innerHTML = clienteId === '' ? 'Selecione um cliente para conferir pendências fiscais.' : 'Pendências: <span class="text-muted">validadas no servidor antes da emissão</span>'; }
        if(clienteId === ''){ return; }
        var cobrancas = cobrancasPorCliente[clienteId] || [];
        if(!Array.isArray(cobrancas) || cobrancas.length === 0){ limparCobrancas('Nenhuma cobrança elegível'); return; }
        cobrancas.forEach(function(cobranca){
            var option = document.createElement('option');
            option.value = String(cobranca.COB_ID || '');
            option.textContent = String(cobranca.descricao || ('Cobrança #' + option.value));
            cobrancaSelect.appendChild(option);
        });
        cobrancaSelect.disabled = false;
        atualizarEstadoBotaoEmissao();
    }

    function atualizarEstadoBotaoEmissao(){
        if(!emitirBtn || !clienteSelect || !cobrancaSelect){ return; }
        var configFiscalCompleta = emitirBtn.getAttribute('data-config-fiscal-completa') === '1';
        emitirBtn.disabled = !(configFiscalCompleta && String(clienteSelect.value || '') !== '' && String(cobrancaSelect.value || '') !== '' && !cobrancaSelect.disabled);
    }

    document.querySelectorAll('.nfse-toggle-row').forEach(function(btn){
        btn.addEventListener('click', function(){
            var child = btn.closest('tr').nextElementSibling;
            if(child && child.classList.contains('nfse-child-row')){ child.classList.toggle('d-none'); btn.textContent = child.classList.contains('d-none') ? '+' : '-'; }
        });
    });

    document.querySelectorAll('.nfse-copy').forEach(function(btn){
        btn.addEventListener('click', function(){
            var valor = btn.getAttribute('data-copy') || '';
            if(navigator.clipboard && valor !== ''){ navigator.clipboard.writeText(valor); }
            btn.textContent = 'Copiado';
            setTimeout(function(){ btn.textContent = 'Copiar'; }, 1500);
        });
    });

    if(clienteSelect && cobrancaSelect){
        limparCobrancas('Selecione um cliente primeiro');
        clienteSelect.addEventListener('change', atualizarCobrancasPorCliente);
        cobrancaSelect.addEventListener('change', atualizarEstadoBotaoEmissao);
    }

    if(form && emitirBtn){
        form.addEventListener('submit', function(){
            if(!emitirBtn.disabled){ emitirBtn.disabled = true; emitirBtn.textContent = 'Enviando...'; }
        });
    }
});
</script>
