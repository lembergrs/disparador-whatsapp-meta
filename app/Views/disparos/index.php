<?php
$limitePlanoDisparador = isset($clientePlano['PLA_LimiteMensagens']) ? (int) $clientePlano['PLA_LimiteMensagens'] : null;
$mensagensUsadasDisparador = isset($consumoMes['CMS_Mensagens']) ? (int) $consumoMes['CMS_Mensagens'] : 0;
$mensagensDisponiveisDisparador = $limitePlanoDisparador !== null ? max(0, $limitePlanoDisparador - $mensagensUsadasDisparador) : null;
$limiteMetaLabel = \Services\MetaService::formatarLimiteConversasMeta($metaContaLimite['MTA_MessagingLimit'] ?? null);
$avisoLimiteMeta = \Services\MetaService::avisoDesatualizacaoMeta($metaContaLimite['MTA_UltimaVerificacao'] ?? null);
?>
<div class="mb-3 d-flex flex-wrap align-items-center justify-content-between">
    <div class="btn-group mb-2 mb-md-0" role="group" aria-label="Navegação de disparos">
        <a href="<?= BASE_URL; ?>/index.php?url=disparo" class="btn btn-primary">
            <i class="fas fa-paper-plane"></i> Novo Disparo
        </a>
        <a href="<?= BASE_URL; ?>/index.php?url=disparo/historico" class="btn btn-outline-primary">
            <i class="fas fa-history"></i> Histórico de Disparos
        </a>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-6">
        <div class="callout callout-info h-100 mb-0">
            <h5>Plano Disparador</h5>
            <?php if($mensagensDisponiveisDisparador !== null){ ?>
                <p class="mb-1">Você possui <?= number_format($mensagensDisponiveisDisparador, 0, ',', '.'); ?> mensagens disponíveis neste ciclo.</p>
            <?php }else{ ?>
                <p class="mb-1">Consulte seu plano no Disparador para acompanhar a franquia comercial de mensagens.</p>
            <?php } ?>
            <small class="text-muted">Este limite faz parte do plano contratado no Disparador e considera mensagens processadas pela plataforma.</small>
        </div>
    </div>
    <div class="col-md-6">
        <div class="callout callout-warning h-100 mb-0">
            <h5>Limite de conversas da Meta</h5>
            <p class="mb-1">Limite atualmente informado pela Meta: <?= htmlspecialchars($limiteMetaLabel, ENT_QUOTES, 'UTF-8'); ?></p>
            <small>Este limite é controlado exclusivamente pela Meta e é diferente da quantidade de mensagens do seu plano.</small>
            <?php if(!empty($avisoLimiteMeta)){ ?><br><small class="text-muted"><?= htmlspecialchars($avisoLimiteMeta, ENT_QUOTES, 'UTF-8'); ?></small><?php } ?>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Novo Disparo</h3>
    </div>

    <div class="card-body">
        <form
            method="POST"
            id="formDisparo"
            action="<?= BASE_URL; ?>/index.php?url=disparo/enviar"
        >
            <?= \Core\Csrf::input(); ?>

            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Conta Meta</label>

                        <select
                            name="meta"
                            id="meta"
                            class="form-control"
                            required
                        >
                            <?php $totalContas = count($contas); ?>

                            <?php if($totalContas > 1){ ?>
                                <option value="">Selecione</option>
                            <?php } ?>

                            <?php foreach($contas as $conta){ ?>
                                <option
                                    value="<?= $conta['MTA_ID']; ?>"
                                    <?= $totalContas == 1 ? 'selected' : ''; ?>
                                >
                                    <?= $conta['MTA_Nome']; ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group">
                        <label>Template</label>

                        <select
                            name="template"
                            id="template"
                            class="form-control"
                            required
                            disabled
                        >
                            <option value="">Selecione primeiro a Conta Meta</option>
                        </select>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group">
                        <label>Lista de Contatos</label>

                        <select
                            name="lista_id"
                            id="listaContatosDisparo"
                            class="form-control"
                        >
                            <option value="" data-total="0">Nenhuma lista - informar números manualmente</option>

                            <?php foreach(($listasContatos ?? []) as $lista){ ?>
                                <option
                                    value="<?= (int) $lista['LST_ID']; ?>"
                                    data-total="<?= (int) ($lista['total_contatos'] ?? 0); ?>"
                                >
                                    <?= htmlspecialchars($lista['LST_Nome']); ?> (<?= (int) ($lista['total_contatos'] ?? 0); ?> contatos)
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
            </div>

            <div
                id="areaProgressoDisparo"
                style="display:none;"
                class="mb-3"
            >
                <strong id="textoProgressoDisparo">Preparando envio...</strong>

                <div class="progress mt-2">
                    <div
                        id="barraProgressoDisparo"
                        class="progress-bar progress-bar-striped progress-bar-animated bg-success"
                        style="width:0%"
                    >
                        0%
                    </div>
                </div>
            </div>

            <div id="resumoFinalDisparo"></div>

            <div class="row mt-3 align-items-stretch">
                <div class="col-lg-6 col-md-6 col-12 mb-3">
                    <div id="previewTemplateDisparo" class="h-100">
                        <div class="card card-outline card-success h-100 mb-0">
                            <div class="card-header">
                                <h3 class="card-title">Prévia da mensagem</h3>
                            </div>

                            <div class="card-body d-flex">
                                <div
                                    id="conteudoPreviewTemplateDisparo"
                                    class="disparo-preview-box flex-fill"
                                >
                                    <div class="text-center text-muted py-5">
                                        <i class="fas fa-file-alt fa-3x mb-3"></i>
                                        <h5>Preview da mensagem</h5>
                                        <p>Selecione um template para exibir a prévia.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 col-md-6 col-12 mb-3">
                    <div id="painelEdicaoDisparo" class="h-100">
                        <div class="form-group h-100 d-flex flex-column">
                            <label>Número(s) Destino</label>

                            <textarea
                                name="numeros"
                                id="numerosDestino"
                                class="form-control disparo-numeros-box flex-fill"
                                rows="7"
                                placeholder="(41) 99999-9999&#10;(41) 98888-8888"
                            ></textarea>

                            <small
                                id="dicaFormatosNumerosDestino"
                                class="text-muted d-block mt-1"
                            >
                                Você pode selecionar uma lista, informar números manualmente ou combinar as duas opções. Ex.: 41999990000, (41) 99999-0000 ou +55 41 99999-0000.
                            </small>

                            <small
                                id="contadorNumerosDestino"
                                class="text-muted d-block mt-1"
                            >
                                Números identificados: 0
                            </small>

                            <small
                                id="ajudaNumerosDestino"
                                class="text-muted d-block mt-2"
                            >
                                <strong>Formato esperado:</strong><br>
                                Número
                                <br><br>
                                <strong>Exemplo:</strong><br>
                                (41) 99999-9999<br>
                                (41) 98888-8888
                            </small>

                            <div class="mt-3">
                                <button
                                    type="submit"
                                    id="btnEnviarDisparo"
                                    class="btn btn-success"
                                >
                                    <i class="fas fa-paper-plane"></i>
                                    Enviar Template
                                </button>
                            </div>
                        </div>
                    </div>

                    <div
                        id="painelExecucaoDisparo"
                        style="display:none;"
                    >
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="mb-0">Status dos Envios</h5>

                            <button
                                type="button"
                                id="btnPararDisparo"
                                class="btn btn-danger btn-sm"
                            >
                                <i class="fas fa-stop"></i>
                                Parar envio
                            </button>
                        </div>

                        <div
                            id="boxStatusNumeros"
                            class="status-envios-box"
                        >
                            <table class="table table-sm table-bordered mb-0 tabela-status-disparo">
                                <thead>
                                    <tr>
                                        <th class="col-numero-disparo">Número</th>
                                        <th class="col-status-disparo">Status</th>
                                        <th>Motivo</th>
                                        <th class="col-detalhes-disparo">Detalhes</th>
                                    </tr>
                                </thead>

                                <tbody id="listaStatusNumeros"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<div
    class="modal fade"
    id="modalDetalhesDisparo"
    tabindex="-1"
    role="dialog"
    aria-hidden="true"
>
    <div
        class="modal-dialog modal-lg"
        role="document"
    >
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalhes técnicos do envio</h5>

                <button
                    type="button"
                    class="close"
                    data-dismiss="modal"
                    aria-label="Fechar"
                >
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <small class="text-muted d-block">Número</small>
                        <strong id="detalheDisparoNumero">-</strong>
                    </div>

                    <div class="col-md-4">
                        <small class="text-muted d-block">Status</small>
                        <strong id="detalheDisparoStatus">-</strong>
                    </div>

                    <div class="col-md-4">
                        <small class="text-muted d-block">Mensagem</small>
                        <strong id="detalheDisparoMensagem">-</strong>
                    </div>
                </div>

                <div class="form-group mb-0">
                    <label for="detalheDisparoJson">JSON técnico completo</label>

                    <textarea
                        id="detalheDisparoJson"
                        class="form-control"
                        rows="16"
                        readonly
                    ></textarea>

                    <small class="form-text text-muted">
                        Use este conteúdo para analisar payload, retorno da Meta/API e erros técnicos.
                    </small>
                </div>
            </div>

            <div class="modal-footer">
                <button
                    type="button"
                    id="btnCopiarDetalhesDisparo"
                    class="btn btn-outline-primary"
                >
                    <i class="fas fa-copy"></i>
                    Copiar detalhes
                </button>

                <button
                    type="button"
                    class="btn btn-secondary"
                    data-dismiss="modal"
                >
                    Fechar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
window.TEMPLATES_DISPARO = <?= json_encode($templates, JSON_UNESCAPED_UNICODE); ?>;
window.TOTAL_CONTAS_META = <?= count($contas); ?>;
</script>
