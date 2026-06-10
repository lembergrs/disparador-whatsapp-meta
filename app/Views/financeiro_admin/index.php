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
                            <th>Periodicidade</th>
                            <th>Valor</th>
                            <th>Números</th>
                            <th>Usuários</th>
                            <th>Mensagens/Mês</th>
                            <th>Excedente</th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach($planos as $plano){ ?>

                            <tr>

                                <td>
                                    <?= htmlspecialchars($plano['PLA_Nome']); ?>
                                </td>

                                <td>
                                    <?= ucfirst($plano['PLA_Periodicidade']); ?>
                                </td>

                                <td>
                                    R$ <?= number_format($plano['PLA_Valor'], 2, ',', '.'); ?>
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
                            <th>Ações</th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach($cobrancas as $cobranca){ ?>

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
                                    class="badge badge-<?= $badge[$cobranca['COB_Status']] ?? 'secondary'; ?>"
                                    >

                                        <?= ucfirst($cobranca['COB_Status']); ?>

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

                                <td width="180">

                                    <?php if(
                                        $cobranca['COB_Status']
                                        == 'pendente'
                                    ){ ?>

                                        <a
                                        href="<?= BASE_URL; ?>/index.php?url=financeiroAdmin/marcarPago&id=<?= $cobranca['COB_ID']; ?>"
                                        class="btn btn-success btn-sm"
                                        onclick="return confirm('Confirmar pagamento?')"
                                        >

                                            <i class="fas fa-check"></i>

                                        </a>

                                        <a
                                        href="<?= BASE_URL; ?>/index.php?url=financeiroAdmin/cancelarCobranca&id=<?= $cobranca['COB_ID']; ?>"
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
                        class="form-control"
                        required
                        >

                    </div>

                    <div class="form-group">

                        <label>Periodicidade</label>

                        <select
                        name="periodicidade"
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

                    <div class="form-group">

                        <label>Valor</label>

                        <input
                        type="text"
                        name="valor"
                        class="form-control"
                        required
                        >

                    </div>

                    <div class="form-group">

                        <label>Limite de Números</label>

                        <input
                        type="number"
                        name="numeros"
                        class="form-control"
                        required
                        >

                    </div>

                    <div class="form-group">

                        <label>Limite de Usuários</label>

                        <input
                        type="number"
                        name="usuarios"
                        class="form-control"
                        required
                        >

                    </div>

                    <div class="form-group">

                        <label>Limite de Mensagens/Mês</label>

                        <input
                        type="number"
                        name="mensagens"
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
                        class="form-control"
                        value="0,05"
                        required
                        >

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