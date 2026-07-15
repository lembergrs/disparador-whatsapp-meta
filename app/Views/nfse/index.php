<?php
use Core\Csrf;
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Emissão manual controlada de NFS-e</h3>
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
                                #<?= (int) $cliente['CLI_ID']; ?> - <?= htmlspecialchars($cliente['CLI_Nome'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <label for="nfse_cobranca_id">Cobrança</label>
                    <select name="cobranca_id" id="nfse_cobranca_id" class="form-control" required>
                        <option value="">Selecione</option>
                        <?php foreach($cobrancas as $cobranca){ ?>
                            <option value="<?= (int) $cobranca['COB_ID']; ?>" data-cliente-id="<?= (int) $cobranca['CLI_ID']; ?>">
                                #<?= (int) $cobranca['COB_ID']; ?> - Cliente #<?= (int) $cobranca['CLI_ID']; ?> - R$ <?= number_format((float) ($cobranca['COB_Valor'] ?? 0), 2, ',', '.'); ?> - <?= htmlspecialchars($cobranca['COB_Status'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary btn-block" onclick="return confirm('Confirmar emissão manual de NFS-e para esta cobrança?');">
                        Emitir manualmente
                    </button>
                </div>
            </div>
            <small class="form-text text-muted">A aptidão fiscal será validada novamente no servidor antes de qualquer chamada à API.</small>
        </form>

        <form method="get" action="<?= BASE_URL; ?>/index.php" class="form-inline mb-3">
            <input type="hidden" name="url" value="nfse">
            <label class="mr-2" for="status">Status</label>
            <select name="status" id="status" class="form-control mr-2">
                <option value="">Todos</option>
                <?php foreach($statusPermitidos as $status){ ?>
                    <option value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>" <?= $statusFiltro === $status ? 'selected' : ''; ?>><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?></option>
                <?php } ?>
            </select>
            <button class="btn btn-secondary" type="submit">Filtrar</button>
        </form>

        <div class="table-responsive">
            <table class="table table-striped table-sm">
                <thead>
                    <tr>
                        <th>NFE_ID</th>
                        <th>Cliente</th>
                        <th>Cobrança</th>
                        <th>Status</th>
                        <th>numDPS</th>
                        <th>idDps</th>
                        <th>Chave acesso</th>
                        <th>RequestId</th>
                        <th>Erro seguro</th>
                        <th>PDF</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($emissoes as $emissao){ ?>
                        <tr>
                            <td><?= (int) $emissao['NFE_ID']; ?></td>
                            <td>#<?= (int) $emissao['CLI_ID']; ?> <?= htmlspecialchars($emissao['CLI_Nome'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>#<?= (int) $emissao['COB_ID']; ?></td>
                            <td><?= htmlspecialchars($emissao['NFE_Status'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars($emissao['NFE_NumDps'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars($emissao['NFE_IdDps'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars($emissao['NFE_ChaveAcesso'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars($emissao['NFE_RequestIdEmissao'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars($emissao['NFE_UltimoErroMensagem'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <?php if(!empty($emissao['NFE_ChaveAcesso'])){ ?>
                                    <form method="post" action="<?= BASE_URL; ?>/index.php?url=nfse/consultarPdf" class="m-0">
                                        <?= Csrf::input(); ?>
                                        <input type="hidden" name="nfse_id" value="<?= (int) $emissao['NFE_ID']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-primary">Consultar PDF</button>
                                    </form>
                                <?php }else{ ?>
                                    -
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                    <?php if(empty($emissoes)){ ?>
                        <tr><td colspan="10" class="text-center text-muted">Nenhuma emissão local encontrada.</td></tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
