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

function avisoMetaDashboard($metaConta)
{
    return \Services\MetaService::avisoDesatualizacaoMeta($metaConta['MTA_UltimaVerificacao'] ?? null);
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

<div class="row">

    <div class="col-lg-3 col-6">
        <div class="small-box bg-primary">
            <div class="inner">
                <h3><?= number_format($campanhas, 0, ',', '.'); ?></h3>
                <p>Campanhas Totais</p>
            </div>
            <div class="icon">
                <i class="fas fa-bullhorn"></i>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-6">
        <div class="small-box bg-info">
            <div class="inner">
                <h3><?= number_format($conversas, 0, ',', '.'); ?></h3>
                <p>Conversas Ativas</p>
            </div>
            <div class="icon">
                <i class="fas fa-comments"></i>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-6">
        <div class="small-box bg-danger">
            <div class="inner">
                <h3><?= number_format($naoLidas, 0, ',', '.'); ?></h3>
                <p>Não Lidas</p>
            </div>
            <div class="icon">
                <i class="fas fa-envelope"></i>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-6">
        <div class="small-box bg-secondary">
            <div class="inner">
                <h3><?= number_format($mensagensRecebidas, 0, ',', '.'); ?></h3>
                <p>Mensagens Recebidas</p>
            </div>
            <div class="icon">
                <i class="fas fa-inbox"></i>
            </div>
        </div>
    </div>


</div>

<div class="row">
    <div class="col-lg-3 col-6">
        <div class="small-box bg-success">
            <div class="inner"><h3><?= number_format($assinaturasAtivas, 0, ',', '.'); ?></h3><p>Assinaturas Ativas</p></div>
            <div class="icon"><i class="fas fa-check-circle"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-warning">
            <div class="inner"><h3><?= number_format($assinaturasPendentes, 0, ',', '.'); ?></h3><p>Assinaturas Pendentes</p></div>
            <div class="icon"><i class="fas fa-clock"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-danger">
            <div class="inner"><h3><?= number_format($assinaturasVencidas, 0, ',', '.'); ?></h3><p>Assinaturas Vencidas</p></div>
            <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-secondary">
            <div class="inner"><h3><?= number_format($assinaturasCanceladas, 0, ',', '.'); ?></h3><p>Assinaturas Canceladas</p></div>
            <div class="icon"><i class="fas fa-ban"></i></div>
        </div>
    </div>
</div>

<?php }else{ ?>

<?php
$clienteEmPreTrialDashboard = \Core\Auth::clienteEmPreTrial();
$avaliacaoDashboard = \Core\Auth::dadosAvaliacaoCliente(false);
?>

<?php if($clienteEmPreTrialDashboard){ ?>
<div class="alert alert-info d-flex align-items-center justify-content-between flex-wrap">
    <div class="mr-3">
        <strong>Comece seu período de avaliação.</strong><br>
        Conecte seu número do WhatsApp para iniciar seu período de avaliação de 7 dias ou até 200 mensagens.
    </div>
    <a href="<?= BASE_URL; ?>/index.php?url=configuracao/meta" class="btn btn-primary btn-sm mt-2 mt-md-0">
        <i class="fab fa-whatsapp mr-1"></i> Conectar WhatsApp
    </a>
</div>
<?php }elseif(!empty($avaliacaoDashboard['ativo'])){ ?>
<div class="alert alert-success d-flex align-items-center justify-content-between flex-wrap">
    <div class="mr-3">
        <strong>Período de avaliação ativo.</strong><br>
        Início: <?= htmlspecialchars(formatarDataDashboard($avaliacaoDashboard['inicio'] ?? null)); ?> ·
        Restam <?= (int)($avaliacaoDashboard['dias_restantes'] ?? 0); ?> dia(s) e
        <?= number_format((int)($avaliacaoDashboard['mensagens_restantes'] ?? 0), 0, ',', '.'); ?> mensagem(ns) do trial.
    </div>
    <a href="<?= BASE_URL; ?>/index.php?url=financeiro" class="btn btn-outline-light btn-sm mt-2 mt-md-0">
        Contratar ou regularizar plano
    </a>
</div>
<?php } ?>

<?php if($usuario['nivel'] != 'admin' && !empty($onboardingChecklist) && empty($onboardingChecklist['concluido'])){ ?>
<div class="card card-outline card-success mb-3">
    <div class="card-header"><h3 class="card-title">Primeiros passos</h3></div>
    <div class="card-body">
        <div class="progress mb-3" style="height: 10px;">
            <div class="progress-bar bg-success" style="width: <?= (int) $onboardingChecklist['percentual']; ?>%;"></div>
        </div>
        <p class="text-muted mb-3"><?= (int) $onboardingChecklist['concluidos']; ?> de <?= (int) $onboardingChecklist['total']; ?> etapas concluídas.</p>
        <div class="row">
            <?php foreach($onboardingChecklist['itens'] as $item){ ?>
                <div class="col-md-6 mb-2">
                    <a class="d-flex align-items-center text-decoration-none <?= !empty($item['done']) ? 'text-success' : 'text-dark'; ?>" href="<?= BASE_URL; ?>/index.php?url=<?= htmlspecialchars($item['url']); ?>">
                        <i class="<?= !empty($item['done']) ? 'fas fa-check-circle' : 'far fa-circle'; ?> mr-2"></i>
                        <span><?= htmlspecialchars($item['label']); ?></span>
                    </a>
                </div>
            <?php } ?>
        </div>
    </div>
</div>
<?php } ?>

<?php if($usuario['nivel'] != 'admin'){ ?>
<div class="row mb-3">
    <div class="col-md-6 mb-3">
        <div class="card card-outline card-primary h-100">
            <div class="card-header"><h3 class="card-title">Seu plano no Disparador</h3></div>
            <div class="card-body">
                <p class="mb-1"><strong>Plano:</strong> <?= htmlspecialchars($cliente['PLA_Nome'] ?? 'Não informado'); ?></p>
                <?php if(isset($cliente['PLA_LimiteMensagens'])){ ?>
                    <p class="mb-1"><strong>Mensagens incluídas:</strong> <?= number_format((int) $cliente['PLA_LimiteMensagens'], 0, ',', '.'); ?></p>
                <?php } ?>
                <?php if($consumo){ ?>
                    <p class="mb-1"><strong>Mensagens utilizadas:</strong> <?= number_format((int) ($consumo['CMS_Mensagens'] ?? 0), 0, ',', '.'); ?></p>
                <?php } ?>
                <p class="text-muted mb-0">Este limite faz parte do plano contratado no Disparador e considera as mensagens processadas pela plataforma.</p>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-3">
        <div class="card card-outline card-info h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title">Limite de conversas da Meta</h3>
                <button type="button" class="btn btn-tool text-info" data-toggle="modal" data-target="#modalEntendaLimites">Entenda os limites</button>
            </div>
            <div class="card-body">
                <?php if($metaConta && !empty($metaConta['MTA_MessagingLimit'])){ ?>
                    <p class="mb-1"><strong>Limite atual informado pela Meta:</strong><br><?= htmlspecialchars(\Services\MetaService::formatarLimiteConversasMeta($metaConta['MTA_MessagingLimit'])); ?></p>
                <?php }else{ ?>
                    <p class="mb-1"><strong>Limite atual informado pela Meta:</strong><br>Limite da Meta ainda não disponível.</p>
                    <p class="text-muted">Conclua a conexão do número ou aguarde a sincronização dos dados da Meta.</p>
                <?php } ?>
                <p class="mb-1"><strong>Qualidade:</strong> <?= htmlspecialchars($metaConta['MTA_QualityRating'] ?? 'Não informado'); ?></p>
                <p class="mb-1"><strong>Status Meta:</strong> <?= htmlspecialchars($metaConta['MTA_OperationalStatus'] ?? 'Não informado'); ?></p>
                <p class="mb-1"><strong>Última consulta à Meta:</strong> <?= !empty($metaConta['MTA_UltimaVerificacao']) ? date('d/m/Y \à\s H:i', strtotime($metaConta['MTA_UltimaVerificacao'])) : 'Nunca'; ?></p>
                <?php $avisoMeta = $metaConta ? \Services\MetaService::avisoDesatualizacaoMeta($metaConta['MTA_UltimaVerificacao'] ?? null) : 'Limite da Meta ainda não disponível. Conclua a conexão do número ou aguarde a sincronização dos dados da Meta.'; ?>
                <?php if($avisoMeta){ ?><div class="alert alert-warning py-2"><?= htmlspecialchars($avisoMeta); ?></div><?php } ?>
                <p class="text-muted mb-1">Este limite é definido e controlado exclusivamente pela Meta. O Disparador não consegue aumentá-lo, alterá-lo ou garantir quando ele será ampliado.</p>
                <p class="text-muted mb-0">A Meta pode alterar esse limite conforme seus próprios critérios, como situação da empresa, qualidade das conversas e histórico de uso.</p>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEntendaLimites" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Entenda os limites do Disparador e da Meta</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
        <div class="modal-body">
            <p>O limite do plano Disparador corresponde à quantidade de mensagens incluídas no plano contratado e processadas pela plataforma.</p>
            <p>O limite de conversas da Meta corresponde à quantidade de clientes únicos com os quais a empresa pode iniciar novas conversas dentro de uma janela contínua de 24 horas, conforme as regras e informações fornecidas pela própria Meta.</p>
            <p>O Disparador apenas consulta e exibe esse dado. A definição, atualização e ampliação do limite são de responsabilidade exclusiva da Meta.</p>
            <p>Ter mensagens disponíveis no plano Disparador não garante que a Meta permitirá iniciar novas conversas acima do limite definido por ela.</p>
            <div class="row"><div class="col-md-6"><h6>Limite do plano Disparador</h6><ul><li>definido pelo plano contratado;</li><li>baseado em mensagens;</li><li>controlado pelo Disparador;</li><li>ciclo comercial do plano.</li></ul></div><div class="col-md-6"><h6>Limite de conversas da Meta</h6><ul><li>definido pela Meta;</li><li>relacionado a clientes únicos;</li><li>janela contínua de 24 horas;</li><li>não pode ser alterado pelo Disparador.</li></ul></div></div>
        </div>
    </div></div>
</div>

<?php } ?>

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
            <a href="<?= BASE_URL; ?>/index.php?url=listaContato" class="small-box-footer">
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
<?php } ?>

<?php if($metaPagamentoConta){ ?><div class="alert alert-warning d-flex align-items-center justify-content-between flex-wrap"><div><strong>Ação necessária: confirme a configuração de pagamento da Meta.</strong><br>As tarifas das mensagens são cobradas diretamente pela Meta. Configure a forma de pagamento da sua conta do WhatsApp para evitar problemas no envio.</div><a href="<?= BASE_URL; ?>/index.php?url=configuracao/meta" class="btn btn-warning btn-sm">Configurar pagamento</a></div><?php } ?>
