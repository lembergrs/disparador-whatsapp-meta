<!DOCTYPE html>
<html lang="pt-br">
<head>
    <?php $googleTagManagerSection = 'head'; require __DIR__ . '/../partials/google_tag_manager.php'; ?>
    <meta charset="UTF-8">
    <title>Termos de Uso | Disparador.net RL2 Net</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://disparador.net/site/termosUso">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="<?= ASSET_URL; ?>/css/style.css?v=12">
</head>

<body>
<?php $googleTagManagerSection = 'body'; require __DIR__ . '/../partials/google_tag_manager.php'; ?>

<div class="container py-5">

    <div class="card">

        <div class="card-body">

            <h1>Termos de Uso</h1>

            <p class="text-muted">Última atualização: Julho de 2026</p>

            <p>
                Os presentes Termos de Uso regulam a utilização da plataforma Disparador.net,
                desenvolvida e operada pela RL2 Net.
            </p>

            <h4>1. Objeto</h4>

            <p>
                A plataforma Disparador.net permite a integração com a Plataforma WhatsApp Business
                da Meta para envio, recebimento e gerenciamento de mensagens, campanhas e atendimentos.
            </p>

            <h4>2. Responsabilidade do Usuário</h4>

            <p>O usuário é integralmente responsável:</p>

            <ul>
                <li>Pelo conteúdo das mensagens enviadas;</li>
                <li>Pela obtenção de consentimento dos destinatários quando exigido pela legislação;</li>
                <li>Pelo cumprimento das Políticas da Meta e da legislação aplicável.</li>
            </ul>

            <h4>3. Uso Adequado</h4>

            <p>É proibida a utilização da plataforma para:</p>

            <ul>
                <li>Envio de mensagens ilegais;</li>
                <li>Spam ou comunicações não autorizadas;</li>
                <li>Fraudes ou atividades ilícitas;</li>
                <li>Violação dos Termos da Meta.</li>
            </ul>

            <h4>4. Disponibilidade</h4>

            <p>
                A RL2 Net empregará seus melhores esforços para manter a plataforma disponível,
                porém não garante funcionamento ininterrupto.
            </p>

            <h4>5. Suspensão de Conta</h4>

            <p>
                A RL2 Net poderá suspender ou encerrar o acesso de usuários que violem estes Termos
                ou utilizem a plataforma de forma inadequada.
            </p>

            <h4>6. Limitação de Responsabilidade</h4>

            <p>
                A RL2 Net não se responsabiliza por bloqueios, limitações ou decisões tomadas pela Meta
                relacionadas às contas WhatsApp dos usuários.
            </p>


            <h4>7. Cancelamento, Reembolso e Período de Avaliação</h4>

            <p>
                O usuário poderá solicitar cancelamento a qualquer momento pelos canais oficiais de
                atendimento da RL2 Net. Sem prejuízo dos direitos assegurados pela legislação aplicável,
                inclusive o direito de arrependimento quando cabível, o cliente poderá solicitar o
                cancelamento no prazo legal aplicável. A RL2 Net também assegura, como política comercial,
                o reembolso integral do primeiro pagamento quando a solicitação ocorrer em até 7 dias
                corridos da confirmação do pagamento e não houver conexão bem-sucedida de número WhatsApp,
                envio de mensagens ou utilização efetiva da plataforma.
            </p>

            <p>
                Quando houver utilização efetiva, o cancelamento interromperá cobranças futuras e o acesso
                poderá permanecer até o final do período já pago. Os pedidos serão analisados conforme a
                legislação aplicável e as circunstâncias da contratação, sem prejuízo dos direitos legalmente
                assegurados ao consumidor.
            </p>

            <p>
                O período de avaliação inicia somente após a conexão operacional do primeiro número WhatsApp.
                O cancelamento e eventual reembolso observarão esta Política de Cancelamento e Reembolso
                e a legislação aplicável. As regras detalhadas estão disponíveis na
                <a href="<?= BASE_URL; ?>/index.php?url=site/politicaCancelamento">
                    Política de Cancelamento e Reembolso
                </a>.
            </p>

            <h4>8. Contato</h4>

            <p>
                <strong>RL2 Net</strong><br>
                E-mail: contato@disparador.net
            </p>

            <h4>9. Aceitação</h4>

            <p>
                Ao utilizar a plataforma, o usuário declara estar de acordo com estes Termos de Uso,
                com a Política de Privacidade e com a Política de Cancelamento e Reembolso.
            </p>

            <hr>

            <a
            href="<?= BASE_URL; ?>/index.php?url=site"
            class="btn btn-secondary"
            >
                Voltar ao site
            </a>

        </div>

    </div>

</div>

<?php require __DIR__ . '/partials/whatsapp_button.php'; ?>

</body>
</html>
