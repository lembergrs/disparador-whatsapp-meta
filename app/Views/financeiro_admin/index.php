<?php
if(!function_exists('valorPlanoCicloAdmin')){
    function valorPlanoCicloAdmin($plano, $ciclo)
    {
        return \Models\Plano::valorPorCiclo($plano, $ciclo);
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
                                    href="<?= BASE_URL; ?>/index.php?url=financeiroAdmin/inativarPlano&id=<?= $plano['PLA_ID']; ?>"
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
                    href="<?= BASE_URL; ?>/index.php?url=financeiroAdmin/gerarCobrancasRecorrentes#tabCobrancas"
                    class="btn btn-success mr-2"
                    onclick="return confirm('Gerar cobranças recorrentes agora?')"
                    >
                        <i class="fas fa-sync-alt"></i>
                        Gerar cobranças recorrentes
                    </a>

                    <a
                    href="<?= BASE_URL; ?>/index.php?url=financeiroAdmin/processarVencimentos#tabCobrancas"
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
                                        $statusCobranca
                                        == 'pendente'
                                    ){ ?>

                                        <a
                                        href="<?= BASE_URL; ?>/index.php?url=financeiroAdmin/marcarPago&id=<?= $cobranca['COB_ID']; ?>#tabCobrancas"
                                        class="btn btn-success btn-sm"
                                        onclick="return confirm('Confirmar pagamento?')"
                                        >

                                            <i class="fas fa-check"></i>

                                        </a>

                                        <a
                                        href="<?= BASE_URL; ?>/index.php?url=financeiroAdmin/cancelarCobranca&id=<?= $cobranca['COB_ID']; ?>#tabCobrancas"
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
                                        href="<?= BASE_URL; ?>/index.php?url=financeiroAdmin/reativarCliente&id=<?= $cliente['CLI_ID']; ?>#tabClientes"
                                        class="btn btn-success btn-sm"
                                        onclick="return confirm('Reativar cliente?')"
                                        >

                                            <i class="fas fa-play"></i>

                                        </a>

                                    <?php }else{ ?>

                                        <a
                                        href="<?= BASE_URL; ?>/index.php?url=financeiroAdmin/suspenderCliente&id=<?= $cliente['CLI_ID']; ?>#tabClientes"
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
                        class="badge badge-primary mt-2"
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

        document.getElementById('formPlano').action =
            '<?= BASE_URL; ?>/index.php?url=financeiroAdmin/salvarPlano';

        document.querySelector('#modalPlano .modal-title').innerHTML =
            'Novo Plano';

    });

    $('#cor').on('change', function(){

        let cor = $(this).val();

        $('#previewCor')
            .removeClass(
                'badge-primary badge-success badge-warning badge-danger badge-info badge-secondary badge-dark'
            )
            .addClass(
                'badge-' + cor
            );

    }).trigger('change');

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