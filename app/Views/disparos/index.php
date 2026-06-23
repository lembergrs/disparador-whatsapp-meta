<div class="card">

<div class="card-header">

<h3 class="card-title">

Novo Disparo

</h3>

</div>

<div class="card-body">

<form
method="POST"
id="formDisparo"
action="<?= BASE_URL; ?>/index.php?url=disparo/enviar"
>

<div class="row">

<div class="col-md-6">

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

    <option value="">
        Selecione
    </option>

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

<div class="col-md-6">

<div class="form-group">

<label>Template</label>

<select
name="template"
id="template"
class="form-control"
required
disabled
>

<option value="">
Selecione primeiro a Conta Meta
</option>

</select>

</div>

</div>

</div>

<div
id="areaProgressoDisparo"
style="display:none;"
class="mb-3"
>

    <strong id="textoProgressoDisparo">
        Preparando envio...
    </strong>

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

<div class="row mt-3">

    <div class="col-md-6">

        <div id="previewTemplateDisparo">

            <div class="card card-outline card-success">

                <div class="card-header">

                    <h3 class="card-title">
                        Prévia da mensagem
                    </h3>

                </div>

                <div class="card-body">

                    <div
                    id="conteudoPreviewTemplateDisparo"
                    class="disparo-preview-box"
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

    <div class="col-md-6">

        <div id="painelEdicaoDisparo">

            <div class="form-group">

                <label>Número(s) Destino</label>

                <textarea
                name="numeros"
                id="numerosDestino"
                class="form-control disparo-numeros-box"
                rows="7"
                placeholder="(41) 99999-9999&#10;(41) 98888-8888"
                required
                ></textarea>

                <small
                id="dicaFormatosNumerosDestino"
                class="text-muted d-block mt-1"
                >
                    Você pode colar números em vários formatos. Ex.: 41999990000, (41) 99999-0000 ou +55 41 99999-0000.
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

            </div>

            <button
            type="submit"
            id="btnEnviarDisparo"
            class="btn btn-success"
            >
                <i class="fas fa-paper-plane"></i>
                Enviar Template
            </button>

        </div>

        <div
        id="painelExecucaoDisparo"
        style="display:none;"
        >

            <div class="d-flex justify-content-between align-items-center mb-2">

                <h5 class="mb-0">
                    Status dos Envios
                </h5>

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

                <h5 class="modal-title">
                    Detalhes técnicos do envio
                </h5>

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

                    <label for="detalheDisparoJson">
                        JSON técnico completo
                    </label>

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

        <div class="alert alert-info">
            <strong>Template com ${variaveis.length} variável(is).</strong><br>
            Informe os valores no campo Números Destino, usando uma linha por destino.<br>
            A orientação do campo será ajustada conforme a quantidade de variáveis.
            <div class="mt-2 small">
                Variáveis esperadas: {{${variaveis.join('}}, {{')}}}
            </div>
        </div>
        </div>

        </div>

    </div>

</div>

<script>
window.TEMPLATES_DISPARO = <?= json_encode($templates, JSON_UNESCAPED_UNICODE); ?>;
window.TOTAL_CONTAS_META = <?= count($contas); ?>;
</script>
