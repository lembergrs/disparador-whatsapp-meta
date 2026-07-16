<?php
use Core\Csrf;

$cobrancasElegiveisJson = json_encode(
    $cobrancasElegiveisPorCliente ?? [],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
);

function nfse_e($valor){ return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8'); }
function nfse_data($valor){ return !empty($valor) ? date('d/m/Y', strtotime((string) $valor)) : '-'; }
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
        'erro_temporario' => 'Erro',
        'erro_definitivo' => 'Erro',
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
function nfse_aptidao_item($campo, $pendencias){
    $faltante = in_array($campo, $pendencias, true) || in_array($campo . '_valido', $pendencias, true);
    return '<li class="list-inline-item mr-3">' . ($faltante ? '<span class="text-danger">✗</span>' : '<span class="text-success">✓</span>') . ' ' . nfse_e(ucwords(str_replace('_', ' ', $campo))) . '</li>';
}
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0">Painel operacional de NFS-e</h3>
        <span class="badge badge-info">Emissão manual homologada</span>
    </div>
    <div class="card-body">
        <div class="alert alert-warning">
            Esta tela é exclusiva para administradores. A emissão é manual, não aciona webhook, Worker, retry automático, e-mail ou download pelo cliente.
        </div>

        <form method="post" action="<?= BASE_URL; ?>/index.php?url=nfse/emitir" class="mb-4">
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
                    <button type="submit" id="nfse_emitir_btn" class="btn btn-primary btn-block" disabled onclick="return confirm('Esta ação emitirá uma NFS-e real no ambiente configurado. Confirma a emissão manual desta cobrança paga?');">
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
                        <tr>
                            <td class="text-nowrap"><?= nfse_data($emissao['NFE_DataEmissao'] ?? $emissao['NFE_DataCriacao'] ?? null); ?></td>
                            <td class="text-nowrap">
                                <strong><?= nfse_e($emissao['CLI_Nome'] ?? 'Cliente não identificado'); ?></strong><br>
                                <small class="text-muted">CLI_ID <?= (int) $emissao['CLI_ID']; ?></small>
                            </td>
                            <td>
                                <strong>Cobrança #<?= (int) $emissao['COB_ID']; ?></strong><br>
                                <small class="text-muted">Pago em <?= nfse_data($emissao['COB_DataPagamento'] ?? null); ?></small>
                            </td>
                            <td><?= nfse_money($emissao['NFE_ValorFiscal'] ?? $emissao['COB_Valor'] ?? 0); ?></td>
                            <td><?= nfse_badge($status); ?></td>
                            <td>
                                <div><small class="text-muted">Chave</small><br><?= nfse_doc($emissao['NFE_ChaveAcesso'] ?? ''); ?></div>
                                <div class="mt-1"><small class="text-muted">RequestId</small><br><?= nfse_doc($emissao['NFE_RequestIdEmissao'] ?? ''); ?></div>
                            </td>
                            <td class="text-nowrap">
                                <?php if($temPdf){ ?><a class="btn btn-xs btn-outline-primary" href="<?= BASE_URL; ?>/index.php?url=nfse/pdf/<?= (int) $emissao['NFE_ID']; ?>">PDF</a><?php } ?>
                                <?php if($temXml){ ?><a class="btn btn-xs btn-outline-success" href="<?= BASE_URL; ?>/index.php?url=nfse/xml/<?= (int) $emissao['NFE_ID']; ?>">XML</a><?php } ?>
                                <button type="button" class="btn btn-xs btn-outline-secondary" data-toggle="modal" data-target="#<?= $modalId; ?>">Detalhes</button>
                                <?php if($temChave){ ?>
                                    <form method="post" action="<?= BASE_URL; ?>/index.php?url=nfse/reconsultar" class="d-inline">
                                        <?= Csrf::input(); ?><input type="hidden" name="nfse_id" value="<?= (int) $emissao['NFE_ID']; ?>">
                                        <button type="submit" class="btn btn-xs btn-outline-info">Reconsultar</button>
                                    </form>
                                <?php } ?>
                                <?php if($status === 'emitida' && $temChave){ ?>
                                    <button type="button" class="btn btn-xs btn-outline-danger" data-toggle="modal" data-target="#cancelar-<?= (int) $emissao['NFE_ID']; ?>">Cancelar</button>
                                <?php } ?>
                            </td>
                        </tr>
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
            <dl class="row mb-0">
                <dt class="col-sm-3">Cliente</dt><dd class="col-sm-9"><?= nfse_e($emissao['CLI_Nome'] ?? '-'); ?> <small class="text-muted">CLI_ID <?= (int) $emissao['CLI_ID']; ?></small></dd>
                <dt class="col-sm-3">Cobrança</dt><dd class="col-sm-9">#<?= (int) $emissao['COB_ID']; ?> — <?= nfse_money($emissao['COB_Valor'] ?? $emissao['NFE_ValorFiscal'] ?? 0); ?></dd>
                <dt class="col-sm-3">Status</dt><dd class="col-sm-9"><?= nfse_badge($emissao['NFE_Status'] ?? ''); ?></dd>
                <dt class="col-sm-3">Número DPS</dt><dd class="col-sm-9"><?= nfse_e($emissao['NFE_NumDps'] ?? '-'); ?></dd>
                <dt class="col-sm-3">Número NFS-e</dt><dd class="col-sm-9"><?= nfse_e($emissao['NFE_NumeroNfse'] ?? '-'); ?></dd>
                <dt class="col-sm-3">Chave</dt><dd class="col-sm-9"><?= nfse_doc($emissao['NFE_ChaveAcesso'] ?? ''); ?></dd>
                <dt class="col-sm-3">RequestId emissão</dt><dd class="col-sm-9"><?= nfse_doc($emissao['NFE_RequestIdEmissao'] ?? ''); ?></dd>
                <dt class="col-sm-3">RequestId consulta</dt><dd class="col-sm-9"><?= nfse_doc($emissao['NFE_RequestIdConsulta'] ?? ''); ?></dd>
                <dt class="col-sm-3">Data emissão</dt><dd class="col-sm-9"><?= nfse_data($emissao['NFE_DataEmissao'] ?? null); ?></dd>
                <dt class="col-sm-3">XML</dt><dd class="col-sm-9"><?= !empty($emissao['NFE_XmlStoragePath']) ? '<span class="badge badge-success">armazenado</span>' : '<span class="badge badge-secondary">indisponível</span>'; ?></dd>
                <dt class="col-sm-3">PDF</dt><dd class="col-sm-9"><?= !empty($emissao['NFE_PdfStoragePath']) ? '<span class="badge badge-success">armazenado</span>' : '<span class="badge badge-secondary">indisponível</span>'; ?></dd>
                <dt class="col-sm-3">Último retorno</dt><dd class="col-sm-9"><span class="text-muted"><?= nfse_e($emissao['NFE_UltimoErroMensagem'] ?? 'Sem erro operacional recente.'); ?></span></dd>
            </dl>
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
        emitirBtn.disabled = !(String(clienteSelect.value || '') !== '' && String(cobrancaSelect.value || '') !== '' && !cobrancaSelect.disabled);
    }

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
