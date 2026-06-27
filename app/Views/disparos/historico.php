<?php
if(!function_exists('hDisparo')){
    function hDisparo($valor)
    {
        return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
    }
}

if(!function_exists('dataDisparoHistorico')){
    function dataDisparoHistorico($data)
    {
        return $data ? date('d/m/Y H:i', strtotime($data)) : '-';
    }
}

if(!function_exists('statusLoteDisparoLabel')){
    function statusLoteDisparoLabel($status)
    {
        $mapa = [
            'pendente' => 'Na fila',
            'processando' => 'Processando',
            'concluido' => 'Concluído',
            'concluido_com_erros' => 'Concluído com erros',
            'erro' => 'Erro'
        ];

        return $mapa[$status] ?? ucfirst((string) ($status ?: '-'));
    }
}

if(!function_exists('statusLoteDisparoBadge')){
    function statusLoteDisparoBadge($status)
    {
        $mapa = [
            'pendente' => 'secondary',
            'processando' => 'info',
            'concluido' => 'success',
            'concluido_com_erros' => 'warning',
            'erro' => 'danger'
        ];

        return $mapa[$status] ?? 'secondary';
    }
}


$paramsBase = [
    'url' => 'disparo/historico',
    'data_inicial' => $filtros['data_inicial'] ?? '',
    'data_final' => $filtros['data_final'] ?? '',
    'status' => $filtros['status'] ?? '',
    'template' => $filtros['template'] ?? '',
    'numero' => $filtros['numero'] ?? '',
    'per_page' => $perPage ?? 10
];

$montarUrl = function(array $extras = []) use ($paramsBase){
    $params = array_merge($paramsBase, $extras);
    return BASE_URL . '/index.php?' . http_build_query($params);
};
?>

<div class="mb-3 d-flex flex-wrap align-items-center justify-content-between">
    <div class="btn-group mb-2 mb-md-0" role="group" aria-label="Navegação de disparos">
        <a href="<?= BASE_URL; ?>/index.php?url=disparo" class="btn btn-outline-primary">
            <i class="fas fa-paper-plane"></i> Novo Disparo
        </a>
        <a href="<?= BASE_URL; ?>/index.php?url=disparo/historico" class="btn btn-primary">
            <i class="fas fa-history"></i> Histórico de Disparos
        </a>
    </div>
</div>

<div class="card card-outline card-primary">
    <div class="card-header">
        <h3 class="card-title">Filtros</h3>
    </div>

    <div class="card-body">
        <form method="get" action="<?= BASE_URL; ?>/index.php" class="mb-0">
            <input type="hidden" name="url" value="disparo/historico">

            <div class="row">
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Data inicial</label>
                        <input type="date" name="data_inicial" class="form-control" value="<?= hDisparo($filtros['data_inicial'] ?? ''); ?>">
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="form-group">
                        <label>Data final</label>
                        <input type="date" name="data_final" class="form-control" value="<?= hDisparo($filtros['data_final'] ?? ''); ?>">
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <option value="">Todos</option>
                            <?php foreach($statusLoteOpcoes as $valor => $rotulo){ ?>
                                <option value="<?= hDisparo($valor); ?>" <?= ($filtros['status'] ?? '') === $valor ? 'selected' : ''; ?>>
                                    <?= hDisparo($rotulo); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="form-group">
                        <label>Template</label>
                        <select name="template" class="form-control">
                            <option value="0">Todos</option>
                            <?php foreach($templates as $template){ ?>
                                <option value="<?= (int) $template['TMP_ID']; ?>" <?= (int) ($filtros['template'] ?? 0) === (int) $template['TMP_ID'] ? 'selected' : ''; ?>>
                                    <?= hDisparo($template['TMP_Nome'] ?? 'Template'); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="form-group">
                        <label>Número</label>
                        <input type="text" name="numero" class="form-control" value="<?= hDisparo($filtros['numero'] ?? ''); ?>" placeholder="5599999999999">
                    </div>
                </div>

                <div class="col-md-1">
                    <div class="form-group">
                        <label>Exibir</label>
                        <select name="per_page" class="form-control">
                            <?php foreach([10, 20, 50] as $opcao){ ?>
                                <option value="<?= $opcao; ?>" <?= (int) $perPage === $opcao ? 'selected' : ''; ?>><?= $opcao; ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Filtrar
            </button>
            <a href="<?= BASE_URL; ?>/index.php?url=disparo/historico" class="btn btn-outline-secondary">Limpar</a>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h3 class="card-title mb-0">Histórico de Disparos Manuais</h3>
        <span class="text-muted small ml-auto">Total: <?= (int) $total; ?></span>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Data</th>
                        <th>Conta WhatsApp</th>
                        <th>Template</th>
                        <th>Total</th>
                        <th>Na fila</th>
                        <th>Processando</th>
                        <th>Aguardando confirmação</th>
                        <th>Enviados</th>
                        <th>Entregues</th>
                        <th>Lidos</th>
                        <th>Erros</th>
                        <th>Status geral</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($lotes)){ ?>
                        <tr>
                            <td colspan="14" class="text-center text-muted py-4">Nenhum lote de disparo manual encontrado.</td>
                        </tr>
                    <?php } ?>

                    <?php foreach($lotes as $lote){ ?>
                        <tr>
                            <td>#<?= (int) $lote['DML_ID']; ?></td>
                            <td><?= hDisparo(dataDisparoHistorico($lote['DML_DataCadastro'] ?? null)); ?></td>
                            <td><?= hDisparo($lote['MTA_Nome'] ?? '-'); ?></td>
                            <td><?= hDisparo($lote['TMP_Nome'] ?? '-'); ?></td>
                            <td><?= (int) ($lote['total_itens'] ?? $lote['DML_Total'] ?? 0); ?></td>
                            <td><?= (int) ($lote['total_pendente'] ?? 0); ?></td>
                            <td><?= (int) ($lote['total_processando'] ?? 0); ?></td>
                            <td><?= (int) ($lote['total_aguardando_confirmacao'] ?? 0); ?></td>
                            <td><?= (int) ($lote['total_enviado'] ?? 0); ?></td>
                            <td><?= (int) ($lote['total_delivered'] ?? 0); ?></td>
                            <td><?= (int) ($lote['total_read'] ?? 0); ?></td>
                            <td><?= (int) ($lote['total_erro'] ?? 0); ?></td>
                            <td>
                                <span class="badge badge-<?= hDisparo(statusLoteDisparoBadge($lote['DML_Status'] ?? '')); ?>">
                                    <?= hDisparo(statusLoteDisparoLabel($lote['DML_Status'] ?? '')); ?>
                                </span>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-info btn-detalhes-lote" data-lote-id="<?= (int) $lote['DML_ID']; ?>">
                                    Ver detalhes
                                </button>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card-footer d-flex flex-column flex-md-row align-items-md-center justify-content-between">
        <span class="text-muted small mb-2 mb-md-0">
            Página <?= (int) $pagina; ?> de <?= (int) $totalPaginas; ?>
        </span>

        <ul class="pagination pagination-sm mb-0">
            <li class="page-item <?= $pagina <= 1 ? 'disabled' : ''; ?>">
                <a class="page-link" href="<?= hDisparo($montarUrl(['page' => max(1, $pagina - 1)])); ?>">Anterior</a>
            </li>

            <?php for($i = max(1, $pagina - 2); $i <= min($totalPaginas, $pagina + 2); $i++){ ?>
                <li class="page-item <?= $i === (int) $pagina ? 'active' : ''; ?>">
                    <a class="page-link" href="<?= hDisparo($montarUrl(['page' => $i])); ?>"><?= $i; ?></a>
                </li>
            <?php } ?>

            <li class="page-item <?= $pagina >= $totalPaginas ? 'disabled' : ''; ?>">
                <a class="page-link" href="<?= hDisparo($montarUrl(['page' => min($totalPaginas, $pagina + 1)])); ?>">Próxima</a>
            </li>
        </ul>
    </div>
</div>

<div class="modal fade" id="modalDetalhesLoteDisparo" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalhes do lote</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="conteudoDetalhesLoteDisparo">
                <div class="text-center text-muted py-4">Carregando...</div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    const modal = document.getElementById('modalDetalhesLoteDisparo');
    const conteudo = document.getElementById('conteudoDetalhesLoteDisparo');

    function escapeHtml(valor){
        return String(valor || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function renderDetalhes(resposta){
        const lote = resposta.lote || {};
        const itens = resposta.itens || [];
        let html = '<div class="mb-3">'
            + '<strong>Lote #' + escapeHtml(lote.id) + '</strong><br>'
            + '<span class="text-muted">Conta: ' + escapeHtml(lote.conta) + ' | Template: ' + escapeHtml(lote.template) + ' | Status: ' + escapeHtml(lote.status_label) + '</span>'
            + '</div>';

        html += '<div class="table-responsive"><table class="table table-sm table-striped table-bordered">'
            + '<thead><tr>'
            + '<th>Número</th><th>Status</th><th>Mensagem</th><th>Message ID</th><th>Criado em</th><th>Envio/processamento</th><th>Atualização</th><th>Erro técnico</th><th>Retorno Meta</th>'
            + '</tr></thead><tbody>';

        if(itens.length === 0){
            html += '<tr><td colspan="9" class="text-center text-muted py-4">Nenhum item encontrado.</td></tr>';
        }

        itens.forEach(function(item){
            html += '<tr>'
                + '<td>' + escapeHtml(item.numero) + '</td>'
                + '<td>' + escapeHtml(item.status_label) + '</td>'
                + '<td>' + escapeHtml(item.mensagem) + '</td>'
                + '<td><small>' + escapeHtml(item.message_id || '-') + '</small></td>'
                + '<td>' + escapeHtml(item.data_cadastro || '-') + '</td>'
                + '<td>' + escapeHtml(item.data_envio || '-') + '</td>'
                + '<td>' + escapeHtml(item.data_atualizacao || '-') + '</td>'
                + '<td><small>' + escapeHtml(item.erro || '-') + '</small></td>'
                + '<td><small>' + escapeHtml(item.retorno || '-') + '</small></td>'
                + '</tr>';
        });

        html += '</tbody></table></div>';
        conteudo.innerHTML = html;
    }

    document.querySelectorAll('.btn-detalhes-lote').forEach(function(botao){
        botao.addEventListener('click', function(){
            const loteId = botao.dataset.loteId || '';

            conteudo.innerHTML = '<div class="text-center text-muted py-4">Carregando...</div>';

            if(window.jQuery){
                window.jQuery(modal).modal('show');
            }

            fetch('<?= BASE_URL; ?>/index.php?url=disparo/detalhesLoteAjax&lote_id=' + encodeURIComponent(loteId), {
                credentials: 'same-origin'
            })
            .then(function(response){ return response.json(); })
            .then(function(resposta){
                if(!resposta || !resposta.sucesso){
                    conteudo.innerHTML = '<div class="alert alert-danger">' + escapeHtml((resposta && resposta.erro) || 'Não foi possível carregar os detalhes.') + '</div>';
                    return;
                }

                renderDetalhes(resposta);
            })
            .catch(function(){
                conteudo.innerHTML = '<div class="alert alert-danger">Não foi possível carregar os detalhes.</div>';
            });
        });
    });
});
</script>
