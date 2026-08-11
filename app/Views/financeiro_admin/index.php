<?php
if(!function_exists('valorPlanoCicloAdmin')){
    function valorPlanoCicloAdmin($plano, $ciclo)
    {
        return \Models\Plano::valorPorCiclo($plano, $ciclo);
    }
}

if(!function_exists('corPlanoAdmin')){
    function corPlanoAdmin($cor)
    {
        $cor = trim((string) $cor);
        return $cor !== '' ? $cor : 'secondary';
    }
}

if(!function_exists('nomeCorPlanoAdmin')){
    function nomeCorPlanoAdmin($cor)
    {
        $nomes = [
            'secondary' => 'Cinza',
            'primary' => 'Azul',
            'success' => 'Verde',
            'warning' => 'Amarelo',
            'danger' => 'Vermelho',
            'info' => 'Ciano',
            'dark' => 'Preto'
        ];

        return $nomes[$cor] ?? ucfirst($cor);
    }
}

?>
<div class="card">

    <div class="card-header">

        <ul class="nav nav-tabs card-header-tabs">

            <li class="nav-item">

                <a
                class="nav-link active"
                data-toggle="tab"
                href="#tabPlanos"
                >
                    Planos
                </a>

            </li>

            <li class="nav-item">

                <a
                class="nav-link"
                data-toggle="tab"
                href="#tabCobrancas"
                >
                    Cobranças
                </a>

            </li>

            <li class="nav-item">

                <a
                class="nav-link"
                data-toggle="tab"
                href="#tabClientes"
                >
                    Clientes
                </a>

            </li>

        </ul>

    </div>

    <div class="card-body">

        <div class="tab-content">

            <!-- PLANOS -->

            <div
            class="tab-pane fade show active"
            id="tabPlanos"
            >

                <div class="mb-3 text-right">

                    <button
                    class="btn btn-success"
                    data-toggle="modal"
                    data-target="#modalPlano"
                    >

                        <i class="fas fa-plus"></i>
                        Novo Plano

                    </button>

                </div>

                <table
                class="table table-bordered table-striped datatable"
                >

                    <thead>

                        <tr>

                            <th>Plano</th>
                            <th>Cor</th>
                            <th>Mensal</th>
                            <th>Trimestral</th>
                            <th>Semestral</th>
                            <th>Anual</th>
                            <th>Números</th>
                            <th>Usuários</th>
                            <th>Mensagens/Mês</th>
                            <th>Excedente</th>
                            <th>Status</th>
                            <th width="120">Ações</th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach($planos as $plano){ ?>

                            <tr>

                                <td>
                                    <?= htmlspecialchars($plano['PLA_Nome']); ?>
                                </td>

                                <?php $corPlano = corPlanoAdmin($plano['PLA_Cor'] ?? ''); ?>
                                <td>
                                    <span class="badge badge-<?= htmlspecialchars($corPlano); ?>">
                                        &nbsp;
                                    </span>
                                    <?= htmlspecialchars(nomeCorPlanoAdmin($corPlano)); ?>
                                </td>

                                <td>
                                    R$ <?= number_format(valorPlanoCicloAdmin($plano, 'mensal'), 2, ',', '.'); ?>
                                </td>

                                <td>
                                    R$ <?= number_format(valorPlanoCicloAdmin($plano, 'trimestral'), 2, ',', '.'); ?>
                                </td>

                                <td>
                                    R$ <?= number_format(valorPlanoCicloAdmin($plano, 'semestral'), 2, ',', '.'); ?>
                                </td>

                                <td>
                                    R$ <?= number_format(valorPlanoCicloAdmin($plano, 'anual'), 2, ',', '.'); ?>
                                </td>

                                <td>
                                    <?= $plano['PLA_LimiteNumeros']; ?>
                                </td>

                                <td>
                                    <?= $plano['PLA_LimiteUsuarios']; ?>
                                </td>

                                <td>
                                    <?= number_format($plano['PLA_LimiteMensagens'], 0, ',', '.'); ?>
                                </td>

                                <td>
                                    R$ <?= number_format($plano['PLA_ValorMensagemExcedente'], 4, ',', '.'); ?>
                                </td>

                                <td>

                                    <?php if($plano['PLA_Ativo'] == 'S'){ ?>

                                        <span class="badge badge-success">
                                            Ativo
                                        </span>

                                    <?php }else{ ?>

                                        <span class="badge badge-danger">
                                            Inativo
                                        </span>

                                    <?php } ?>

                                </td>

                                <td>

                                    <button
                                    type="button"
                                    class="btn btn-primary btn-sm btnEditarPlano"

                                    data-id="<?= $plano['PLA_ID']; ?>"

                                    data-nome="<?= htmlspecialchars($plano['PLA_Nome']); ?>"

                                    data-periodicidade="<?= $plano['PLA_Periodicidade']; ?>"

                                    data-cor="<?= htmlspecialchars(corPlanoAdmin($plano['PLA_Cor'] ?? '')); ?>"

                                    data-valor-mensal="<?= valorPlanoCicloAdmin($plano, 'mensal'); ?>"

                                    data-valor-trimestral="<?= valorPlanoCicloAdmin($plano, 'trimestral'); ?>"

                                    data-valor-semestral="<?= valorPlanoCicloAdmin($plano, 'semestral'); ?>"

                                    data-valor-anual="<?= valorPlanoCicloAdmin($plano, 'anual'); ?>"

                                    data-numeros="<?= $plano['PLA_LimiteNumeros']; ?>"

                                    data-usuarios="<?= $plano['PLA_LimiteUsuarios']; ?>"

                                    data-mensagens="<?= $plano['PLA_LimiteMensagens']; ?>"

                                    data-excedente="<?= $plano['PLA_ValorMensagemExcedente']; ?>"
                                    >

                                        <i class="fas fa-edit"></i>

                                    </button>

                                    <a
                                    href="#" data-post-url="<?= BASE_URL; ?>/index.php?url=financeiroAdmin/inativarPlano&id=<?= (int) $plano['PLA_ID']; ?>"
                                    class="btn btn-danger btn-sm"
                                    onclick="return confirm('Inativar plano?')"
                                    >

                                        <i class="fas fa-times"></i>

                                    </a>

                                </td>

                            </tr>

                        <?php } ?>

                    </tbody>

                </table>

            </div>

            <!-- COBRANÇAS -->

            <div
            class="tab-pane fade"
            id="tabCobrancas"
            >

                <div class="mb-3 text-right">
                    <a
                    href="#" data-post-url="<?= BASE_URL; ?>/index.php?url=financeiroAdmin/gerarCobrancasRecorrentes#tabCobrancas"
                    class="btn btn-success mr-2"
                    onclick="return confirm('Gerar cobranças recorrentes agora?')"
                    >
                        <i class="fas fa-sync-alt"></i>
                        Gerar cobranças recorrentes
                    </a>

                    <a
                    href="#" data-post-url="<?= BASE_URL; ?>/index.php?url=financeiroAdmin/processarVencimentos#tabCobrancas"
                    class="btn btn-warning"
                    onclick="return confirm('Processar vencimentos financeiros agora?')"
                    >
                        <i class="fas fa-calendar-times"></i>
                        Processar vencimentos
                    </a>
                </div>

                <table
                class="table table-bordered table-striped datatable"
                >

                    <thead>

                        <tr>

                            <th>Cliente</th>
                            <th>Plano</th>
                            <th>Valor</th>
                            <th>Status</th>
                            <th>Vencimento</th>
                            <th>Pagamento</th>
                            <th>Ações</th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach($cobrancas as $cobranca){ ?>

                            <?php

                            $statusCobranca = strtolower(
                                trim(
                                    (string) ($cobranca['COB_Status'] ?? '')
                                )
                            );

                            ?>

                            <tr>

                                <td>
                                    <?= htmlspecialchars($cobranca['CLI_Nome']); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($cobranca['PLA_Nome']); ?>
                                </td>

                                <td>

                                    R$
                                    <?= number_format(
                                        $cobranca['COB_Valor'],
                                        2,
                                        ',',
                                        '.'
                                    ); ?>

                                </td>

                                <td>

                                    <?php

                                    $badge = [
                                        'pendente' => 'warning',
                                        'pago' => 'success',
                                        'cancelado' => 'danger',
                                        'vencido' => 'secondary'
                                    ];

                                    ?>

                                    <span
                                    class="badge badge-<?= $badge[$statusCobranca] ?? 'secondary'; ?>"
                                    >

                                        <?= ucfirst($statusCobranca); ?>

                                    </span>

                                </td>

                                <td>

                                    <?=
                                    $cobranca['COB_DataVencimento']
                                    ? date(
                                        'd/m/Y',
                                        strtotime(
                                            $cobranca['COB_DataVencimento']
                                        )
                                    )
                                    : '-';
                                    ?>

                                </td>

                                <td>

                                    <?php if(!empty($cobranca['COB_DataPagamento'])){ ?>

                                        <span class="badge badge-success">
                                            Pago em
                                            <?= date(
                                                'd/m/Y',
                                                strtotime(
                                                    $cobranca['COB_DataPagamento']
                                                )
                                            ); ?>
                                        </span>

                                    <?php } ?>

                                </td>

                                <td width="180">

                                    <?php if(
                                        in_array(
                                            $statusCobranca,
                                            ['pendente', 'vencido'],
                                            true
                                        )
                                    ){ ?>

                                        <?php
                                        $nominalCentavos = isset($cobranca['COB_ValorBaseCentavos']) && $cobranca['COB_ValorBaseCentavos'] !== null
                                            ? (int) $cobranca['COB_ValorBaseCentavos'] + (int) ($cobranca['COB_AdicionaisCentavos'] ?? 0)
                                            : (int) round(((float) $cobranca['COB_Valor']) * 100);
                                        $descontoInicialCentavos = (int) ($cobranca['COB_DescontoInicialCentavos'] ?? 0);
                                        $descontoIndicacaoCentavos = (int) ($cobranca['COB_DescontoIndicacaoCentavos'] ?? 0);
                                        $comIndicacaoCentavos = max(0, $nominalCentavos - $descontoInicialCentavos - $descontoIndicacaoCentavos);
                                        $semIndicacaoCentavos = max(0, $nominalCentavos - $descontoInicialCentavos);
                                        ?>
                                        <button
                                        type="button"
                                        class="btn btn-success btn-sm btnConfirmarPagamentoManual"
                                        data-id="<?= (int) $cobranca['COB_ID']; ?>"
                                        data-nominal="<?= $nominalCentavos; ?>"
                                        data-inicial="<?= $descontoInicialCentavos; ?>"
                                        data-indicacao="<?= $descontoIndicacaoCentavos; ?>"
                                        data-com-indicacao="<?= $comIndicacaoCentavos; ?>"
                                        data-sem-indicacao="<?= $semIndicacaoCentavos; ?>"
                                        title="Confirmar pagamento manual"
                                        >

                                            <i class="fas fa-check"></i>

                                        </button>

                                        <a
                                        href="#" data-post-url="<?= BASE_URL; ?>/index.php?url=financeiroAdmin/cancelarCobranca&id=<?= (int) $cobranca['COB_ID']; ?>#tabCobrancas"
                                        class="btn btn-danger btn-sm"
                                        onclick="return confirm('Cancelar cobrança?')"
                                        >

                                            <i class="fas fa-times"></i>

                                        </a>

                                    <?php } ?>

                                </td>

                            </tr>

                        <?php } ?>

                    </tbody>

                </table>

            </div>

            <div
            class="tab-pane fade"
            id="tabClientes"
            >

                <table class="table table-bordered table-striped datatable">

                    <thead>

                        <tr>
                            <th>Cliente</th>
                            <th>Plano</th>
                            <th>Cadastro</th>
                            <th>Pagamento</th>
                            <th>Mensagens</th>
                            <th>Excedente</th>
                            <th>Liberação</th>
                            <th width="160">Ações</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach($clientesFinanceiro as $cliente){ ?>

                            <tr>

                                <td>
                                    <?= htmlspecialchars($cliente['CLI_Nome']); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($cliente['PLA_Nome'] ?? 'Sem plano'); ?>
                                </td>

                                <td>
                                    <span class="badge badge-secondary">
                                        <?= ucfirst($cliente['CLI_StatusCadastro']); ?>
                                    </span>
                                </td>

                                <td>
                                    <?php if($cliente['CLI_StatusPagamento'] == 'pago'){ ?>

                                        <span class="badge badge-success">
                                            Pago
                                        </span>

                                    <?php }else{ ?>

                                        <span class="badge badge-warning">
                                            Pendente
                                        </span>

                                    <?php } ?>
                                </td>

                                <td>
                                    <?= number_format(
                                        $cliente['CMS_Mensagens'] ?? 0,
                                        0,
                                        ',',
                                        '.'
                                    ); ?>

                                    /

                                    <?= number_format(
                                        $cliente['PLA_LimiteMensagens'] ?? 0,
                                        0,
                                        ',',
                                        '.'
                                    ); ?>
                                </td>

                                <td>
                                    R$
                                    <?= number_format(
                                        $cliente['EXC_ValorTotal'] ?? 0,
                                        2,
                                        ',',
                                        '.'
                                    ); ?>
                                </td>

                                <td>
                                    <?=
                                    $cliente['CLI_DataLiberacao']
                                    ? date(
                                        'd/m/Y H:i',
                                        strtotime(
                                            $cliente['CLI_DataLiberacao']
                                        )
                                    )
                                    : '-';
                                    ?>
                                </td>

                                <td>

                                    <button
                                    type="button"
                                    class="btn btn-primary btn-sm btnTrocarPlanoCliente"
                                    data-cliente="<?= $cliente['CLI_ID']; ?>"
                                    data-nome="<?= htmlspecialchars($cliente['CLI_Nome']); ?>"
                                    data-plano="<?= $cliente['CLI_Plano_DR']; ?>"
                                    >

                                        <i class="fas fa-exchange-alt"></i>

                                    </button>

                                    <?php if($cliente['CLI_StatusCadastro'] == 'suspenso'){ ?>

                                        <a
                                        href="#" data-post-url="<?= BASE_URL; ?>/index.php?url=financeiroAdmin/reativarCliente&id=<?= (int) $cliente['CLI_ID']; ?>#tabClientes"
                                        class="btn btn-success btn-sm"
                                        onclick="return confirm('Reativar cliente?')"
                                        >

                                            <i class="fas fa-play"></i>

                                        </a>

                                    <?php }else{ ?>

                                        <a
                                        href="#" data-post-url="<?= BASE_URL; ?>/index.php?url=financeiroAdmin/suspenderCliente&id=<?= (int) $cliente['CLI_ID']; ?>#tabClientes"
                                        class="btn btn-danger btn-sm"
                                        onclick="return confirm('Suspender cliente?')"
                                        >

                                            <i class="fas fa-pause"></i>

                                        </a>

                                    <?php } ?>

                                </td>

                            </tr>

                        <?php } ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

<!-- MODAL PLANO -->

<div
class="modal fade"
id="modalPlano"
tabindex="-1"
>

    <div class="modal-dialog">

        <div class="modal-content">

            <form
            method="post"
            action="<?= BASE_URL; ?>/index.php?url=financeiroAdmin/salvarPlano"
            id="formPlano"
            >

                <input
                type="hidden"
                name="plano_id"
                id="plano_id"
                >

                <div class="modal-header">

                    <h5 class="modal-title">

                        Novo Plano

                    </h5>

                    <button
                    type="button"
                    class="close"
                    data-dismiss="modal"
                    >

                        &times;

                    </button>

                </div>

                <div class="modal-body">

                    <div class="form-group">

                        <label>Nome</label>

                        <input
                        type="text"
                        name="nome"
                        id="nome"
                        class="form-control"
                        required
                        >

                    </div>

                    <div class="form-group">

                        <label>Periodicidade</label>

                        <select
                        name="periodicidade"
                        id="periodicidade"
                        class="form-control"
                        required
                        >

                            <option value="mensal">
                                Mensal
                            </option>

                            <option value="trimestral">
                                Trimestral
                            </option>

                            <option value="semestral">
                                Semestral
                            </option>

                            <option value="anual">
                                Anual
                            </option>

                        </select>

                    </div>

                    <div class="form-row">

                        <div class="form-group col-md-6">

                            <label>Valor mensal</label>

                            <input
                            type="text"
                            name="valor_mensal"
                            id="valor_mensal"
                            class="form-control"
                            required
                            >

                        </div>

                        <div class="form-group col-md-6">

                            <label>Valor trimestral</label>

                            <input
                            type="text"
                            name="valor_trimestral"
                            id="valor_trimestral"
                            class="form-control"
                            placeholder="Automático: mensal x 3"
                            >

                        </div>

                    </div>

                    <div class="form-row">

                        <div class="form-group col-md-6">

                            <label>Valor semestral</label>

                            <input
                            type="text"
                            name="valor_semestral"
                            id="valor_semestral"
                            class="form-control"
                            placeholder="Automático: mensal x 6"
                            >

                        </div>

                        <div class="form-group col-md-6">

                            <label>Valor anual</label>

                            <input
                            type="text"
                            name="valor_anual"
                            id="valor_anual"
                            class="form-control"
                            placeholder="Automático: mensal x 12"
                            >

                        </div>

                    </div>

                    <div class="form-group">

                        <label>Limite de Números</label>

                        <input
                        type="number"
                        name="numeros"
                        id="numeros"
                        class="form-control"
                        required
                        >

                    </div>

                    <div class="form-group">

                        <label>Limite de Usuários</label>

                        <input
                        type="number"
                        name="usuarios"
                        id="usuarios"
                        class="form-control"
                        required
                        >

                    </div>

                    <div class="form-group">

                        <label>Limite de Mensagens/Mês</label>

                        <input
                        type="number"
                        name="mensagens"
                        id="mensagens"
                        class="form-control"
                        value="1000"
                        required
                        >

                    </div>

                    <div class="form-group">

                        <label>Valor Mensagem Excedente</label>

                        <input
                        type="text"
                        name="excedente"
                        id="excedente"
                        class="form-control"
                        value="0,05"
                        required
                        >

                    </div>

                    <div class="form-group">

                        <label>Cor de Destaque</label>

                        <select
                        name="cor"
                        id="cor"
                        class="form-control"
                        >

                            <option value="secondary">Cinza</option>
                            <option value="primary">Azul</option>
                            <option value="success">Verde</option>
                            <option value="warning">Amarelo</option>
                            <option value="danger">Vermelho</option>
                            <option value="info">Ciano</option>
                            <option value="dark">Preto</option>

                        </select>

                        <div
                        id="previewCor"
                        class="badge badge-secondary mt-2"
                        >
                            Exemplo do Plano
                        </div>

                    </div>

                </div>

                <div class="modal-footer">

                    <button
                    type="submit"
                    class="btn btn-success"
                    >

                        Salvar

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

<!-- MODAL TROCA DE PLANO -->

<div
class="modal fade"
id="modalTrocarPlanoCliente"
tabindex="-1"
>

    <div class="modal-dialog">

        <div class="modal-content">

            <form
            method="post"
            action="<?= BASE_URL; ?>/index.php?url=financeiroAdmin/alterarPlanoCliente"
            >

                <input
                type="hidden"
                name="cliente_id"
                id="cliente_id_plano"
                >

                <div class="modal-header">

                    <h5 class="modal-title">
                        Trocar Plano
                    </h5>

                    <button
                    type="button"
                    class="close"
                    data-dismiss="modal"
                    >
                        &times;
                    </button>

                </div>

                <div class="modal-body">

                    <p>
                        Cliente:
                        <strong id="nome_cliente_plano"></strong>
                    </p>

                    <div class="form-group">

                        <label>Novo Plano</label>

                        <select
                        name="plano_id"
                        id="plano_id_cliente"
                        class="form-control"
                        required
                        >

                            <option value="">
                                Selecione
                            </option>

                            <?php foreach($planos as $plano){ ?>

                                <option value="<?= $plano['PLA_ID']; ?>">
                                    <?= htmlspecialchars($plano['PLA_Nome']); ?>
                                </option>

                            <?php } ?>

                        </select>

                    </div>

                    <div class="form-group">

                        <label>Ciclo</label>

                        <select
                        name="ciclo"
                        id="ciclo_cliente"
                        class="form-control"
                        required
                        >
                            <option value="mensal">Mensal</option>
                            <option value="trimestral">Trimestral</option>
                            <option value="semestral">Semestral</option>
                            <option value="anual">Anual</option>
                        </select>

                    </div>

                </div>

                <div class="modal-footer">

                    <button
                    type="submit"
                    class="btn btn-success"
                    >
                        Salvar
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

<div class="modal fade" id="modalConfirmarPagamentoManual" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document"><div class="modal-content">
        <form method="post" id="formConfirmarPagamentoManual" action="<?= BASE_URL; ?>/index.php?url=financeiroAdmin/marcarPago#tabCobrancas">
            <div class="modal-header"><h5 class="modal-title">Confirmar pagamento manual</h5><button type="button" class="close" data-dismiss="modal" aria-label="Fechar"><span>&times;</span></button></div>
            <div class="modal-body">
                <div id="composicaoPagamentoManual" class="small text-muted mb-3"></div>
                <div class="form-group"><label for="valor_pago_manual">Valor efetivamente pago</label><input class="form-control" type="text" inputmode="decimal" name="valor_pago" id="valor_pago_manual" required></div>
                <div id="decisaoIndicacaoManual" class="form-group d-none">
                    <label>O desconto de indicação foi aplicado?</label>
                    <div><div class="custom-control custom-radio custom-control-inline"><input class="custom-control-input" type="radio" id="indicacaoAplicada" name="decisao_indicacao" value="aplicado"><label class="custom-control-label" for="indicacaoAplicada">Aplicado</label></div><div class="custom-control custom-radio custom-control-inline"><input class="custom-control-input" type="radio" id="indicacaoNaoAplicada" name="decisao_indicacao" value="nao_aplicado"><label class="custom-control-label" for="indicacaoNaoAplicada">Não aplicado</label></div></div>
                </div>
                <div id="avisoValorDivergente" class="alert alert-warning d-none"><div id="textoValorDivergente"></div><div class="custom-control custom-checkbox mt-2"><input class="custom-control-input" type="checkbox" id="confirmar_valor_divergente" name="confirmar_valor_divergente" value="1"><label class="custom-control-label" for="confirmar_valor_divergente">Confirmo o lançamento com valor divergente.</label></div></div>
                <div class="form-group mb-0"><label for="motivo_pagamento_manual">Observação administrativa <small class="text-muted">(opcional)</small></label><textarea class="form-control" maxlength="500" rows="3" id="motivo_pagamento_manual" name="motivo"></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-success">Confirmar pagamento</button></div>
        </form>
    </div></div>
</div>

<script>

document.addEventListener('DOMContentLoaded', function(){

    document.addEventListener('click', function(e){

        const botao =
            e.target.closest('.btnEditarPlano');

        if(!botao){
            return;
        }

        document.getElementById('plano_id').value =
            botao.dataset.id;

        document.getElementById('nome').value =
            botao.dataset.nome;

        document.getElementById('periodicidade').value =
            botao.dataset.periodicidade;

        document.getElementById('valor_mensal').value =
            botao.dataset.valorMensal;

        document.getElementById('valor_trimestral').value =
            botao.dataset.valorTrimestral;

        document.getElementById('valor_semestral').value =
            botao.dataset.valorSemestral;

        document.getElementById('valor_anual').value =
            botao.dataset.valorAnual;

        document.getElementById('numeros').value =
            botao.dataset.numeros;

        document.getElementById('usuarios').value =
            botao.dataset.usuarios;

        document.getElementById('mensagens').value =
            botao.dataset.mensagens;

        document.getElementById('excedente').value =
            botao.dataset.excedente;

        document.getElementById('cor').value =
            botao.dataset.cor || 'secondary';

        atualizarPreviewCor();

        document.getElementById('formPlano').action =
            '<?= BASE_URL; ?>/index.php?url=financeiroAdmin/editarPlano';

        document.querySelector('#modalPlano .modal-title').innerHTML =
            'Editar Plano';

        $('#modalPlano').modal('show');

    });

    document.addEventListener('click', function(e){

        const botao =
            e.target.closest('.btnTrocarPlanoCliente');

        if(!botao){
            return;
        }

        document.getElementById('cliente_id_plano').value =
            botao.dataset.cliente;

        document.getElementById('nome_cliente_plano').innerHTML =
            botao.dataset.nome;

        document.getElementById('plano_id_cliente').value =
            botao.dataset.plano;

        $('#modalTrocarPlanoCliente').modal('show');

    });

    $('#modalPlano').on('hidden.bs.modal', function(){

        document.getElementById('formPlano').reset();

        document.getElementById('plano_id').value = '';

        document.getElementById('cor').value = 'secondary';
        atualizarPreviewCor();

        document.getElementById('formPlano').action =
            '<?= BASE_URL; ?>/index.php?url=financeiroAdmin/salvarPlano';

        document.querySelector('#modalPlano .modal-title').innerHTML =
            'Novo Plano';

    });

    function atualizarPreviewCor(){

        let cor = $('#cor').val() || 'secondary';

        $('#previewCor')
            .removeClass(
                'badge-primary badge-success badge-warning badge-danger badge-info badge-secondary badge-dark'
            )
            .addClass(
                'badge-' + cor
            );

    }

    $('#cor').on('change', atualizarPreviewCor);
    atualizarPreviewCor();

    const formPagamentoManual = document.getElementById('formConfirmarPagamentoManual');
    const valorPagoManual = document.getElementById('valor_pago_manual');
    const decisaoIndicacaoManual = document.getElementById('decisaoIndicacaoManual');
    const avisoValorDivergente = document.getElementById('avisoValorDivergente');
    let contextoPagamentoManual = null;
    const moedaManual = function(centavos){ return 'R$ ' + (centavos / 100).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2}); };
    const centavosInformados = function(valor){ let texto = String(valor || '').trim(); texto = texto.includes(',') ? texto.replace(/\./g, '').replace(',', '.') : texto; return /^\d+(\.\d{1,2})?$/.test(texto) ? Math.round(Number(texto) * 100) : null; };
    const atualizarAvisoPagamentoManual = function(){
        if(!contextoPagamentoManual){ return; }
        const decisao = formPagamentoManual.querySelector('input[name="decisao_indicacao"]:checked');
        const esperado = contextoPagamentoManual.indicacao > 0 && decisao && decisao.value === 'nao_aplicado' ? contextoPagamentoManual.semIndicacao : contextoPagamentoManual.comIndicacao;
        const informado = centavosInformados(valorPagoManual.value);
        const deveAvisar = informado !== null && (contextoPagamentoManual.indicacao === 0 || decisao) && informado !== esperado;
        avisoValorDivergente.classList.toggle('d-none', !deveAvisar);
        if(deveAvisar){ document.getElementById('textoValorDivergente').textContent = 'Valor esperado para a opção selecionada: ' + moedaManual(esperado) + '. O valor informado é diferente.'; }
    };
    document.querySelectorAll('.btnConfirmarPagamentoManual').forEach(function(botao){ botao.addEventListener('click', function(){
        contextoPagamentoManual = {id: botao.dataset.id, nominal: Number(botao.dataset.nominal), inicial: Number(botao.dataset.inicial), indicacao: Number(botao.dataset.indicacao), comIndicacao: Number(botao.dataset.comIndicacao), semIndicacao: Number(botao.dataset.semIndicacao)};
        formPagamentoManual.action = '<?= BASE_URL; ?>/index.php?url=financeiroAdmin/marcarPago&id=' + contextoPagamentoManual.id + '#tabCobrancas';
        valorPagoManual.value = moedaManual(contextoPagamentoManual.comIndicacao).replace('R$ ', '');
        formPagamentoManual.querySelectorAll('input[name="decisao_indicacao"]').forEach(function(input){ input.checked = false; input.required = contextoPagamentoManual.indicacao > 0; });
        decisaoIndicacaoManual.classList.toggle('d-none', contextoPagamentoManual.indicacao === 0);
        document.getElementById('confirmar_valor_divergente').checked = false;
        document.getElementById('composicaoPagamentoManual').innerHTML = 'Nominal: <strong>' + moedaManual(contextoPagamentoManual.nominal) + '</strong><br>Benefício inicial: <strong>-' + moedaManual(contextoPagamentoManual.inicial) + '</strong><br>Desconto de indicação: <strong>-' + moedaManual(contextoPagamentoManual.indicacao) + '</strong><br>Esperado com indicação: <strong>' + moedaManual(contextoPagamentoManual.comIndicacao) + '</strong><br>Esperado sem indicação: <strong>' + moedaManual(contextoPagamentoManual.semIndicacao) + '</strong>';
        atualizarAvisoPagamentoManual(); $('#modalConfirmarPagamentoManual').modal('show');
    }); });
    valorPagoManual.addEventListener('input', atualizarAvisoPagamentoManual);
    formPagamentoManual.querySelectorAll('input[name="decisao_indicacao"]').forEach(function(input){ input.addEventListener('change', atualizarAvisoPagamentoManual); });

    if(window.location.hash){

        const aba =
            document.querySelector(
                'a[href="' + window.location.hash + '"]'
            );

        if(aba){
            $(aba).tab('show');
        }

    }

});

</script>
