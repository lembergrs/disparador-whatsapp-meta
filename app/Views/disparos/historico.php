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
        return $data ? date('d/m/Y H:i:s', strtotime($data)) : '-';
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

<style>
.badge-purple {
    background-color: #6f42c1;
    color: #fff;
}
</style>

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
                        <input type="text" name="numero" class="form-control telefone" value="<?= hDisparo($filtros['numero'] ?? ''); ?>" placeholder="5599999999999">
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
                        <th>Data/Hora do disparo</th>
                        <th>Conta WhatsApp utilizada</th>
                        <th>Template utilizado</th>
                        <th>Quantidade total</th>
                        <th>Status geral</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($lotes)){ ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">Nenhum lote de disparo manual encontrado.</td>
                        </tr>
                    <?php } ?>

                    <?php foreach($lotes as $lote){ ?>
                        <tr>
                            <td><?= hDisparo(dataDisparoHistorico($lote['DML_DataCadastro'] ?? null)); ?></td>
                            <td><?= hDisparo($lote['MTA_Nome'] ?? '-'); ?></td>
                            <td><?= hDisparo($lote['TMP_Nome'] ?? '-'); ?></td>
                            <td><?= (int) ($lote['total_itens'] ?? $lote['DML_Total'] ?? 0); ?></td>
                            <td>
                                <span class="badge badge-<?= hDisparo(statusLoteDisparoBadge($lote['DML_Status'] ?? '')); ?>">
                                    <?= hDisparo(statusLoteDisparoLabel($lote['DML_Status'] ?? '')); ?>
                                </span>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-info btn-detalhes-lote" data-lote-id="<?= (int) $lote['DML_ID']; ?>">
                                    Acompanhar
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
                <h5 class="modal-title">Acompanhar disparo</h5>
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

    function badgeStatus(item){
        return '<span class="badge badge-' + escapeHtml(item.status_badge || 'secondary') + '">' + escapeHtml(item.status_label || '-') + '</span>';
    }

    function renderResumo(lote, resumo){
        const total = resumo.total || 0;
        const progresso = resumo.progresso || 0;

        return '<div class="card card-outline card-info" data-disparo-resumo>'
            + '<div class="card-header"><h3 class="card-title">Resumo do disparo</h3></div>'
            + '<div class="card-body">'
            + '<div class="row mb-3">'
            + '<div class="col-md-3 col-6 mb-2"><small class="text-muted d-block">Data do disparo</small><strong>' + escapeHtml(lote.data_cadastro || '-') + '</strong></div>'
            + '<div class="col-md-3 col-6 mb-2"><small class="text-muted d-block">Conta WhatsApp</small><strong>' + escapeHtml(lote.conta || '-') + '</strong></div>'
            + '<div class="col-md-3 col-6 mb-2"><small class="text-muted d-block">Template utilizado</small><strong>' + escapeHtml(lote.template || '-') + '</strong></div>'
            + '<div class="col-md-3 col-6 mb-2"><small class="text-muted d-block">Quantidade total</small><strong>' + escapeHtml(total) + ' mensagens</strong></div>'
            + '</div>'
            + '<div class="progress mb-3" style="height: 22px;">'
            + '<div class="progress-bar bg-success" role="progressbar" style="width:' + escapeHtml(progresso) + '%" aria-valuenow="' + escapeHtml(progresso) + '" aria-valuemin="0" aria-valuemax="100">' + escapeHtml(progresso) + '%</div>'
            + '</div>'
            + '<div class="row text-center" data-disparo-contadores>'
            + '<div class="col-md-3 col-6 mb-2"><span class="text-success">●</span> <strong>' + escapeHtml(resumo.enviadas || 0) + '</strong><br><small>enviadas</small></div>'
            + '<div class="col-md-3 col-6 mb-2"><span class="text-success">●</span> <strong>' + escapeHtml(resumo.entregues || 0) + '</strong><br><small>entregues</small></div>'
            + '<div class="col-md-3 col-6 mb-2"><span style="color:#6f42c1;">●</span> <strong>' + escapeHtml(resumo.lidas || 0) + '</strong><br><small>lidas</small></div>'
            + '<div class="col-md-3 col-6 mb-2"><span class="text-danger">●</span> <strong>' + escapeHtml(resumo.erros || 0) + '</strong><br><small>erros</small></div>'
            + '</div>'
            + '</div>'
            + '</div>';
    }

    function renderDetalhes(resposta){
        const lote = resposta.lote || {};
        const resumo = resposta.resumo || {};
        const itens = resposta.itens || [];
        let html = renderResumo(lote, resumo);

        html += '<div class="card" data-disparo-destinatarios>'
            + '<div class="card-header"><h3 class="card-title">Destinatários</h3></div>'
            + '<div class="card-body p-0"><div class="table-responsive">'
            + '<table class="table table-sm table-striped table-bordered mb-0">'
            + '<thead><tr>'
            + '<th>Número</th><th>Status</th><th>Mensagem</th><th>Criado em</th><th>Envio/Processamento</th><th>Atualização</th><th>Erro</th>'
            + '</tr></thead><tbody>';

        if(itens.length === 0){
            html += '<tr><td colspan="7" class="text-center text-muted py-4">Nenhum destinatário encontrado.</td></tr>';
        }

        itens.forEach(function(item){
            html += '<tr data-disparo-item-status="' + escapeHtml(item.status || '') + '">'
                + '<td>' + escapeHtml(item.numero || '-') + '</td>'
                + '<td>' + badgeStatus(item) + '</td>'
                + '<td>' + escapeHtml(item.mensagem || '-') + '</td>'
                + '<td>' + escapeHtml(item.data_cadastro || '-') + '</td>'
                + '<td>' + escapeHtml(item.data_envio || '-') + '</td>'
                + '<td>' + escapeHtml(item.data_atualizacao || '-') + '</td>'
                + '<td>' + escapeHtml(item.erro || '-') + '</td>'
                + '</tr>';
        });

        html += '</tbody></table></div></div></div>';
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
