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

<div class="card">

    <div class="card-header">

        <h3 class="card-title">
            Escolha seu plano
        </h3>

    </div>

    <div class="card-body">

        <div class="row">

            <?php foreach($planos as $plano){ ?>

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