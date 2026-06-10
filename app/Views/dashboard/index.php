<?php

function formatarTelefoneDashboard($telefone)
{
    $telefone = preg_replace('/\D/', '', $telefone);

    if(substr($telefone, 0, 2) == '55'){
        $telefone = substr($telefone, 2);
    }

    if(strlen($telefone) == 11){
        return '(' . substr($telefone, 0, 2) . ') '
            . substr($telefone, 2, 5)
            . '-'
            . substr($telefone, 7);
    }

    if(strlen($telefone) == 10){
        return '(' . substr($telefone, 0, 2) . ') '
            . substr($telefone, 2, 4)
            . '-'
            . substr($telefone, 6);
    }

    return $telefone;
}

function formatarDataDashboard($data)
{
    if(!$data){
        return '-';
    }

    return date('d/m/Y H:i', strtotime($data));
}

?>

<?php if($usuario['nivel'] == 'admin'){ ?>

<div class="row">

    <div class="col-lg-3 col-6">
        <div class="small-box bg-info">
            <div class="inner">
                <h3><?= number_format($clientes, 0, ',', '.'); ?></h3>
                <p>Clientes</p>
            </div>
            <div class="icon">
                <i class="fas fa-users"></i>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-6">
        <div class="small-box bg-success">
            <div class="inner">
                <h3><?= number_format($contasMeta, 0, ',', '.'); ?></h3>
                <p>Contas Meta</p>
            </div>
            <div class="icon">
                <i class="fab fa-whatsapp"></i>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-6">
        <div class="small-box bg-warning">
            <div class="inner">
                <h3><?= number_format($templates, 0, ',', '.'); ?></h3>
                <p>Templates</p>
            </div>
            <div class="icon">
                <i class="fas fa-file-alt"></i>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-6">
        <div class="small-box bg-danger">
            <div class="inner">
                <h3><?= number_format($contatos, 0, ',', '.'); ?></h3>
                <p>Contatos</p>
            </div>
            <div class="icon">
                <i class="fas fa-address-book"></i>
            </div>
        </div>
    </div>

</div>

<?php }else{ ?>

<div class="row">

    <div class="col-lg-3 col-6">
        <div class="small-box bg-info">
            <div class="inner">
                <h3><?= number_format($conversas, 0, ',', '.'); ?></h3>
                <p>Conversas</p>
            </div>
            <div class="icon">
                <i class="fas fa-comments"></i>
            </div>
            <a href="<?= BASE_URL; ?>/index.php?url=conversa" class="small-box-footer">
                Abrir conversas <i class="fas fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>

    <div class="col-lg-3 col-6">
        <div class="small-box bg-danger">
            <div class="inner">
                <h3><?= number_format($naoLidas, 0, ',', '.'); ?></h3>
                <p>Não lidas</p>
            </div>
            <div class="icon">
                <i class="fas fa-envelope"></i>
            </div>
            <a href="<?= BASE_URL; ?>/index.php?url=conversa" class="small-box-footer">
                Ver pendências <i class="fas fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>

    <div class="col-lg-3 col-6">
        <div class="small-box bg-success">
            <div class="inner">
                <h3><?= number_format($contatos, 0, ',', '.'); ?></h3>
                <p>Contatos</p>
            </div>
            <div class="icon">
                <i class="fas fa-address-book"></i>
            </div>
            <a href="<?= BASE_URL; ?>/index.php?url=listacontato" class="small-box-footer">
                Ver listas <i class="fas fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>

    <div class="col-lg-3 col-6">
        <div class="small-box bg-warning">
            <div class="inner">
                <h3><?= number_format($campanhas, 0, ',', '.'); ?></h3>
                <p>Campanhas</p>
            </div>
            <div class="icon">
                <i class="fas fa-bullhorn"></i>
            </div>
            <a href="<?= BASE_URL; ?>/index.php?url=campanha" class="small-box-footer">
                Ver campanhas <i class="fas fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>

</div>

<div class="row">

    <div class="col-md-3">

        <div class="card">

            <div class="card-header">

                <h3 class="card-title">
                    Meu Plano
                </h3>

            </div>

            <div class="card-body">

                <?php if(!empty($cliente['PLA_Nome'])){ ?>

                    <h5>
                        <?= htmlspecialchars($cliente['PLA_Nome']); ?>
                    </h5>

                    <p class="mb-1">
                        <strong>Valor:</strong>
                        <h4 class="text-success">
                        R$ <?= number_format(
                            $cliente['PLA_Valor'],
                            2,
                            ',',
                            '.'
                        ); ?>
                        </h4>
                    </p>

                    <p class="mb-1">
                        <strong>Números:</strong>
                        <?= $cliente['PLA_LimiteNumeros']; ?>
                    </p>

                    <p class="mb-1">
                        <strong>Usuários:</strong>
                        <?= $cliente['PLA_LimiteUsuarios']; ?>
                    </p>

                    <p class="mb-1">
                        <strong>Limite Mensal:</strong>
                        <?= number_format(
                            $cliente['PLA_LimiteMensagens'],
                            0,
                            ',',
                            '.'
                        ); ?> mensagens
                    </p>

                    <?php

                    $mensagensUtilizadas =
                        (int)($consumo['CMS_Mensagens'] ?? 0);

                    $limiteMensagens =
                        (int)$cliente['PLA_LimiteMensagens'];

                    $percentualUso = 0;

                    if($limiteMensagens > 0){

                        $percentualUso =
                            min(
                                100,
                                round(
                                    (
                                        $mensagensUtilizadas
                                        / $limiteMensagens
                                    ) * 100
                                )
                            );
                    }

                    $corBarra = 'success';

                    if($percentualUso >= 80){
                        $corBarra = 'warning';
                    }

                    if($percentualUso >= 100){
                        $corBarra = 'danger';
                    }

                    ?>

                    <hr>

                    <p class="mb-1">

                        <strong>Uso Mensal</strong>

                    </p>

                    <p class="mb-2">

                        <?= number_format(
                            $mensagensUtilizadas,
                            0,
                            ',',
                            '.'
                        ); ?>

                        /

                        <?= number_format(
                            $limiteMensagens,
                            0,
                            ',',
                            '.'
                        ); ?>

                        mensagens

                    </p>

                    <div class="progress mb-2">

                        <div
                        class="progress-bar bg-<?= $corBarra; ?>"
                        style="width: <?= $percentualUso; ?>%;"
                        >

                            <?= $percentualUso; ?>%

                        </div>

                    </div>

                    <?php if($percentualUso >= 100){ ?>

                        <div class="alert alert-danger py-2">

                            Limite mensal atingido.

                        </div>

                    <?php }elseif($percentualUso >= 90){ ?>

                        <div class="alert alert-danger py-2">

                            Atenção: mais de 90% do plano utilizado.

                        </div>

                    <?php }elseif($percentualUso >= 80){ ?>

                        <div class="alert alert-warning py-2">

                            Atenção: mais de 80% do plano utilizado.

                        </div>

                    <?php } ?>

                    <?php if(
                        !empty($excedente)
                        &&
                        $excedente['EXC_Mensagens'] > 0
                    ){ ?>

                        <hr>

                        <p class="mb-1">

                            <strong>
                                Excedente Atual
                            </strong>

                        </p>

                        <p class="mb-1">

                            <?= number_format(
                                $excedente['EXC_Mensagens'],
                                0,
                                ',',
                                '.'
                            ); ?>

                            mensagens

                        </p>

                        <p class="mb-0">

                            R$
                            <?= number_format(
                                $excedente['EXC_ValorTotal'],
                                2,
                                ',',
                                '.'
                            ); ?>

                        </p>

                    <?php } ?>

                    <p class="mb-0">

                        <strong>Status:</strong>

                        <?php if(
                            $cliente['CLI_StatusPagamento']
                            == 'pago'
                        ){ ?>

                            <span class="badge badge-success">
                                Ativo
                            </span>

                        <?php }else{ ?>

                            <span class="badge badge-warning">
                                Pendente
                            </span>

                        <?php } ?>

                    </p>

                <?php }else{ ?>

                    <div class="alert alert-warning mb-0">

                        Nenhum plano contratado.

                    </div>

                <?php } ?>

            </div>

        </div>

    </div>

    <div class="col-md-3">

        <div class="card">

            <div class="card-header">
                <h3 class="card-title">
                    Conta Meta
                </h3>
            </div>

            <div class="card-body">

                <?php if($usuario['nivel'] == 'admin'){ ?>

                    <p class="text-muted mb-0">
                        Visão administrativa geral.
                    </p>

                <?php }elseif($metaConta){ ?>

                    <h5>
                        <?= htmlspecialchars($metaConta['MTA_Nome']); ?>
                    </h5>

                    <p class="mb-1">
                        <strong>Número:</strong>
                        <?= formatarTelefoneDashboard($metaConta['MTA_NumeroTelefone']); ?>
                    </p>

                    <p class="mb-0">
                        <strong>Status:</strong>

                        <?php if($metaConta['MTA_Status'] == 'conectado'){ ?>

                            <span class="badge badge-success">
                                Conectada
                            </span>

                        <?php }else{ ?>

                            <span class="badge badge-danger">
                                <?= htmlspecialchars($metaConta['MTA_Status']); ?>
                            </span>

                        <?php } ?>

                    </p>

                <?php }else{ ?>

                    <div class="alert alert-warning mb-0">
                        Nenhuma conta Meta cadastrada.
                    </div>

                <?php } ?>

            </div>

        </div>

    </div>

    <div class="col-md-6">

        <div class="card">

            <div class="card-header">
                <h3 class="card-title">
                    Últimas Campanhas
                </h3>
            </div>

            <div class="card-body table-responsive p-0">

                <table class="table table-hover table-striped mb-0">

                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Status</th>
                            <th>Agendamento</th>
                            <th>Contatos</th>
                            <th>Enviados</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if(empty($ultimasCampanhas)){ ?>

                        <tr>
                            <td colspan="5" class="text-center text-muted">
                                Nenhuma campanha encontrada.
                            </td>
                        </tr>

                    <?php } ?>

                    <?php foreach($ultimasCampanhas as $campanha){ ?>

                        <tr>
                            <td>
                                <?= htmlspecialchars($campanha['CAM_Nome']); ?>
                            </td>

                            <td>
                                <span class="badge badge-secondary">
                                    <?= htmlspecialchars($campanha['CAM_Status']); ?>
                                </span>
                            </td>

                            <td>
                                <?= formatarDataDashboard($campanha['CAM_DataAgendamento']); ?>
                            </td>

                            <td>
                                <?= number_format($campanha['CAM_TotalContatos'], 0, ',', '.'); ?>
                            </td>

                            <td>
                                <?= number_format($campanha['CAM_TotalEnviados'], 0, ',', '.'); ?>
                            </td>
                        </tr>

                    <?php } ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

<div class="card">

    <div class="card-header">
        <h3 class="card-title">
            Últimas Conversas
        </h3>
    </div>

    <div class="card-body table-responsive p-0">

        <table class="table table-hover table-striped mb-0">

            <thead>
                <tr>
                    <th>Contato</th>
                    <th>Telefone</th>
                    <th>Última mensagem</th>
                    <th>Data</th>
                </tr>
            </thead>

            <tbody>

            <?php if(empty($ultimasConversas)){ ?>

                <tr>
                    <td colspan="4" class="text-center text-muted">
                        Nenhuma conversa encontrada.
                    </td>
                </tr>

            <?php } ?>

            <?php foreach($ultimasConversas as $conversa){ ?>

                <tr>
                    <td>
                        <?= htmlspecialchars($conversa['CVS_Nome'] ?: 'Sem nome'); ?>
                    </td>

                    <td>
                        <?= formatarTelefoneDashboard($conversa['CVS_Numero']); ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($conversa['CVS_UltimaMensagem']); ?>
                    </td>

                    <td>
                        <?= formatarDataDashboard($conversa['CVS_DataUltimaMensagem']); ?>
                    </td>
                </tr>

            <?php } ?>

            </tbody>

        </table>

    </div>

</div>
<?php } ?>