<?php

$avaliacao = \Core\Auth::dadosAvaliacaoCliente();

?>

<?php if($avaliacao['ativo']){ ?>

    <div class="alert alert-info">

        <h5>
            <i class="fas fa-clock"></i>
            Período de avaliação ativo
        </h5>

        <p class="mb-1">
            Você pode usar o sistema durante a avaliação enquanto estiver dentro de
            <strong><?= $avaliacao['limite_dias']; ?> dias</strong>
            e abaixo de
            <strong><?= number_format($avaliacao['limite_mensagens'], 0, ',', '.'); ?> mensagens</strong>.
        </p>

        <p class="mb-0">
            Restam
            <strong><?= $avaliacao['dias_restantes']; ?> dia(s)</strong>
            e
            <strong><?= number_format($avaliacao['mensagens_restantes'], 0, ',', '.'); ?> mensagem(ns)</strong>
            até o limite gratuito.
        </p>

    </div>

<?php } ?>

<?php if($cobranca){ ?>

    <div class="alert alert-warning">

        <h5>
            <i class="fas fa-exclamation-triangle"></i>
            Pagamento pendente
        </h5>

        <p class="mb-1">
            Plano:
            <strong><?= htmlspecialchars($cobranca['PLA_Nome']); ?></strong>
        </p>

        <p class="mb-1">
            Valor:
            <strong>
                R$ <?= number_format($cobranca['COB_Valor'], 2, ',', '.'); ?>
            </strong>
        </p>

        <p class="mb-0">
            Vencimento:
            <strong>
                <?= date('d/m/Y', strtotime($cobranca['COB_DataVencimento'])); ?>
            </strong>
        </p>

    </div>

<?php } ?>

<?php if(
    !empty($excedente)
    &&
    $excedente['EXC_Mensagens'] > 0
){ ?>

    <div class="alert alert-info">

        <h5>

            Consumo Excedente

        </h5>

        <p class="mb-1">

            Mensagens excedentes:

            <strong>

                <?= number_format(
                    $excedente['EXC_Mensagens'],
                    0,
                    ',',
                    '.'
                ); ?>

            </strong>

        </p>

        <p class="mb-0">

            Valor acumulado:

            <strong>

                R$
                <?= number_format(
                    $excedente['EXC_ValorTotal'],
                    2,
                    ',',
                    '.'
                ); ?>

            </strong>

        </p>

    </div>

<?php } ?>


<div class="card mb-3">
    <div class="card-header">
        <h3 class="card-title">Minha Assinatura</h3>
    </div>
    <div class="card-body">
        <?php if(!empty($assinaturaAtual)){ ?>
            <div class="row">
                <div class="col-md-2"><small class="text-muted d-block">Plano contratado</small><strong><?= htmlspecialchars($assinaturaAtual['PLA_Nome']); ?></strong></div>
                <div class="col-md-2"><small class="text-muted d-block">Ciclo</small><strong><?= htmlspecialchars($assinaturaAtual['ASS_Ciclo']); ?></strong></div>
                <div class="col-md-2"><small class="text-muted d-block">Valor</small><strong>R$ <?= number_format($assinaturaAtual['ASS_Valor'], 2, ',', '.'); ?></strong></div>
                <div class="col-md-2"><small class="text-muted d-block">Status</small><strong><?= ucfirst($assinaturaAtual['ASS_Status']); ?></strong></div>
                <div class="col-md-2"><small class="text-muted d-block">Próxima cobrança</small><strong><?= $assinaturaAtual['ASS_DataProximaCobranca'] ? date('d/m/Y', strtotime($assinaturaAtual['ASS_DataProximaCobranca'])) : '-'; ?></strong></div>
                <div class="col-md-2"><small class="text-muted d-block">Data de início</small><strong><?= $assinaturaAtual['ASS_DataInicio'] ? date('d/m/Y', strtotime($assinaturaAtual['ASS_DataInicio'])) : '-'; ?></strong></div>
            </div>
        <?php }else{ ?>
            <div class="alert alert-info mb-0">Você ainda não possui uma assinatura ativa.</div>
        <?php } ?>
    </div>
</div>

<div class="card">

    <div class="card-header">

        <h3 class="card-title">
            Escolha seu plano
        </h3>

    </div>

    <div class="card-body">

        <div class="row">

            <?php foreach($planos as $plano){ ?>

                <?php
                $numerosAtivosPlano =
                    (int) ($numerosAtivos ?? 0);

                $limiteNumerosPlano =
                    (int) $plano['PLA_LimiteNumeros'];

                $planoIncompativelNumeros =
                    $numerosAtivosPlano
                    >
                    $limiteNumerosPlano;
                ?>

                <div class="col-md-4 mb-4">

                    <div class="card h-100">

                        <div class="card-body text-center">

                            <h4>
                                <?= htmlspecialchars($plano['PLA_Nome']); ?>
                            </h4>

                            <h2 class="text-success">
                                R$ <?= number_format($plano['PLA_Valor'], 2, ',', '.'); ?>
                            </h2>

                            <p>
                                <i class="fab fa-whatsapp text-success"></i>
                                <?= $plano['PLA_LimiteNumeros']; ?>
                                número(s) WhatsApp
                            </p>

                            <p>
                                <i class="fas fa-user text-info"></i>
                                <?= $plano['PLA_LimiteUsuarios']; ?>
                                usuário(s)
                            </p>

                            <p>
                                <i class="fas fa-paper-plane text-primary"></i>
                                <?= number_format(
                                    $plano['PLA_LimiteMensagens'],
                                    0,
                                    ',',
                                    '.'
                                ); ?>
                                mensagens/mês
                            </p>

                            <p class="text-muted small">
                                Excedente:
                                R$ <?= number_format(
                                    $plano['PLA_ValorMensagemExcedente'],
                                    4,
                                    ',',
                                    '.'
                                ); ?>
                                por mensagem adicional
                            </p>

                            <?php if($planoIncompativelNumeros){ ?>

                                <div class="alert alert-warning small">
                                    Plano incompatível com sua utilização atual.
                                    Reduza para no máximo
                                    <?= $limiteNumerosPlano; ?>
                                    número(s) conectado(s).
                                    Atualmente sua conta possui
                                    <?= $numerosAtivosPlano; ?>
                                    número(s) conectado(s).
                                </div>

                            <?php } ?>

                            <form
                            method="post"
                            action="<?= BASE_URL; ?>/index.php?url=financeiro/escolherPlano"
                            >

                                <input
                                type="hidden"
                                name="plano"
                                value="<?= $plano['PLA_ID']; ?>"
                                >

                                <button
                                type="submit"
                                class="btn btn-success btn-block"
                                <?= $planoIncompativelNumeros ? 'disabled' : ''; ?>
                                >
                                    Escolher plano
                                </button>

                            </form>

                        </div>

                    </div>

                </div>

            <?php } ?>

        </div>

    </div>

</div>
