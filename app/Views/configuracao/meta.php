<?php

function formatarTelefoneConfiguracao($telefone)
{
    $telefone =
        preg_replace('/\D/', '', $telefone);

    if(substr($telefone, 0, 2) == '55'){
        $telefone =
            substr($telefone, 2);
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

$limiteNumeros =
    $limiteNumeros
    ??
    [
        'permitido' => false,
        'sem_plano' => true,
        'utilizados' => count($contas ?? []),
        'limite' => 0,
        'mensagem' => 'Escolha um plano para conectar seu número WhatsApp.'
    ];

$podeConectarNumero =
    !empty($limiteNumeros['permitido']);

?>

<div class="row">

    <div class="col-md-8">

        <div class="card">

            <div class="card-header">

                <h3 class="card-title">
                    Números WhatsApp conectados
                </h3>

                <div class="card-tools">

                    <?php if($podeConectarNumero){ ?>

                        <button
                        type="button"
                        class="btn btn-success btn-sm"
                        id="btnConectarWhatsApp"
                        >
                            <i class="fab fa-whatsapp"></i>
                            Conectar novo número
                        </button>

                    <?php }else{ ?>

                        <a
                        href="<?= BASE_URL; ?>/index.php?url=financeiro"
                        class="btn btn-primary btn-sm"
                        >
                            <i class="fas fa-arrow-up"></i>
                            Ver planos
                        </a>

                    <?php } ?>

                </div>

            </div>

            <div class="card-body">

                <div class="alert alert-info">
                    <strong>
                        <?= (int) $limiteNumeros['utilizados']; ?>
                        de
                        <?= (int) $limiteNumeros['limite']; ?>
                        números conectados
                    </strong>
                </div>

                <?php if(!$podeConectarNumero){ ?>

                    <div class="alert alert-warning">
                        <?= htmlspecialchars($limiteNumeros['mensagem']); ?>

                        <div class="mt-2">
                            <a
                            href="<?= BASE_URL; ?>/index.php?url=financeiro"
                            class="btn btn-primary btn-sm"
                            >
                                Ver Financeiro/Planos
                            </a>
                        </div>
                    </div>

                <?php } ?>

                <?php if(empty($contas)){ ?>

                    <div class="text-center text-muted py-5">

                        <i class="fab fa-whatsapp fa-4x mb-3 text-success"></i>

                        <h4>
                            Nenhum número conectado
                        </h4>

                        <p>
                            Conecte um número de WhatsApp Business para começar a enviar campanhas,
                            receber mensagens e atender seus contatos.
                        </p>

                        <?php if($podeConectarNumero){ ?>

                            <button
                            type="button"
                            class="btn btn-success"
                            id="btnConectarWhatsAppVazio"
                            >
                                <i class="fab fa-whatsapp"></i>
                                Conectar WhatsApp
                            </button>

                        <?php } ?>

                    </div>

                <?php }else{ ?>

                    <div class="table-responsive">

                        <table class="table table-bordered table-hover">

                            <thead>

                                <tr>
                                    <th>Nome</th>
                                    <th>Número</th>
                                    <th>Auto resposta</th>
                                    <th>Ações</th>
                                </tr>

                            </thead>

                            <tbody>

                                <?php foreach($contas as $conta){ ?>

                                    <tr>

                                        <td>
                                            <?= htmlspecialchars($conta['MTA_Nome']); ?>
                                        </td>

                                        <td>
                                            <?= formatarTelefoneConfiguracao($conta['MTA_NumeroTelefone']); ?>
                                        </td>

                                        <td>
                                            <?php if(($conta['MTA_AutoRespostaAtiva'] ?? 'N') == 'S'){ ?>
                                                <span class="badge badge-success">Ativa</span>
                                            <?php }else{ ?>
                                                <span class="badge badge-secondary">Inativa</span>
                                            <?php } ?>
                                        </td>

                                        <td>
                                            <button
                                            type="button"
                                            class="btn btn-primary btn-sm btnConfigurarAutoRespostaCliente"
                                            data-id="<?= $conta['MTA_ID']; ?>"
                                            data-numero="<?= htmlspecialchars(formatarTelefoneConfiguracao($conta['MTA_NumeroTelefone']), ENT_QUOTES, 'UTF-8'); ?>"
                                            data-auto-resposta-ativa="<?= $conta['MTA_AutoRespostaAtiva'] ?? 'N'; ?>"
                                            data-auto-resposta-texto="<?= htmlspecialchars($conta['MTA_AutoRespostaTexto'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                            data-auto-resposta-intervalo="<?= $conta['MTA_AutoRespostaIntervaloMinutos'] ?? 1440; ?>"
                                            >
                                                <i class="fas fa-reply"></i>
                                                Configurar auto resposta
                                            </button>
                                        </td>

                                    </tr>

                                <?php } ?>

                            </tbody>

                        </table>

                    </div>

                <?php } ?>

            </div>

        </div>

    </div>

    <div class="col-md-4">

        <div class="card card-outline card-info">

            <div class="card-header">

                <h3 class="card-title">
                    Sobre a conexão
                </h3>

            </div>

            <div class="card-body">

                <p>
                    Cada número conectado poderá enviar campanhas,
                    receber mensagens e aparecer na Central de Conversas.
                </p>

                <p>
                    No próximo passo, este botão será ligado ao
                    <strong>Embedded Signup da Meta</strong>.
                </p>

                <ul class="mb-0">
                    <li>1 cliente pode ter vários números.</li>
                    <li>Cada número pode enviar campanhas e receber mensagens.</li>
                    <li>A auto resposta não substitui o atendimento humano.</li>
                    <li>Os limites poderão variar conforme o plano contratado.</li>
                </ul>

            </div>

        </div>

    </div>

</div>


<div class="modal fade" id="modalAutoRespostaCliente">

    <div class="modal-dialog modal-lg">

        <div class="modal-content">

            <form method="POST" id="formAutoRespostaCliente" action="<?= BASE_URL; ?>/index.php?url=configuracao/salvarAutoResposta">

                <div class="modal-header">
                    <h4 class="modal-title">Configurar auto resposta</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body">

                    <input type="hidden" name="conta_id">
                    <input type="hidden" name="auto_resposta_intervalo_minutos" value="1440">

                    <div class="alert alert-info">
                        Essa mensagem será enviada automaticamente quando esse número receber uma mensagem. Ela não usa template e só funciona dentro da janela de atendimento de 24 horas da Meta.
                    </div>

                    <p class="text-muted">
                        Número: <strong id="autoRespostaClienteNumero"></strong>
                    </p>

                    <div class="form-group">
                        <label>Ativar auto resposta</label>

                        <div class="d-flex align-items-center">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="auto_resposta_ativa" id="cliente_auto_resposta_ativa_n" value="N" checked>
                                <label class="form-check-label" for="cliente_auto_resposta_ativa_n">Não</label>
                            </div>

                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="auto_resposta_ativa" id="cliente_auto_resposta_ativa_s" value="S">
                                <label class="form-check-label" for="cliente_auto_resposta_ativa_s">Sim</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Texto da auto resposta</label>
                        <textarea name="auto_resposta_texto" class="form-control" rows="5"></textarea>
                    </div>

                    <div class="form-group">
                        <label>Intervalo para repetir auto resposta</label>

                        <div class="row">
                            <div class="col-md-6">
                                <label class="small text-muted">Horas</label>
                                <select name="auto_resposta_intervalo_horas" class="form-control auto-resposta-horas">
                                    <?php for($hora = 0; $hora <= 24; $hora++){ ?>
                                        <option value="<?= $hora; ?>"><?= str_pad($hora, 2, '0', STR_PAD_LEFT); ?></option>
                                    <?php } ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="small text-muted">Minutos</label>
                                <select name="auto_resposta_intervalo_minutos_select" class="form-control auto-resposta-minutos">
                                    <?php for($minuto = 0; $minuto <= 60; $minuto++){ ?>
                                        <option value="<?= $minuto; ?>"><?= str_pad($minuto, 2, '0', STR_PAD_LEFT); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>

                        <small class="form-text text-muted">
                            O total será salvo em minutos. Selecione pelo menos 1 minuto.
                        </small>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">Salvar auto resposta</button>
                </div>

            </form>

        </div>

    </div>

</div>


<div
id="embeddedSignupFeedback"
class="alert d-none"
role="alert"
></div>

<script>

(function(){

    let ultimoSessionInfoMeta = null;

    function exibirFeedbackEmbeddedSignup(tipo, mensagem)
    {
        const feedback = document.getElementById('embeddedSignupFeedback');

        if(!feedback){
            return;
        }

        feedback.className = 'alert alert-' + tipo;
        feedback.textContent = mensagem;
    }

    function limparFeedbackEmbeddedSignup()
    {
        const feedback = document.getElementById('embeddedSignupFeedback');

        if(!feedback){
            return;
        }

        feedback.className = 'alert d-none';
        feedback.textContent = '';
    }

    function sessionInfoListener(event)
    {
        if(event.origin !== 'https://www.facebook.com' && event.origin !== 'https://web.facebook.com'){
            return;
        }

        let data = null;

        try{
            data = JSON.parse(event.data);
        }catch(e){
            return;
        }

        if(!data || data.type !== 'WA_EMBEDDED_SIGNUP'){
            return;
        }

        ultimoSessionInfoMeta = data;

        if(data.event === 'FINISH'){
            exibirFeedbackEmbeddedSignup(
                'success',
                'Cadastro incorporado concluído na Meta. Finalize a conexão após o retorno do código de autorização.'
            );
            return;
        }

        if(data.event === 'CANCEL'){
            exibirFeedbackEmbeddedSignup(
                'warning',
                'Cadastro incorporado cancelado antes da conclusão.'
            );
            return;
        }

        if(data.event === 'ERROR'){
            exibirFeedbackEmbeddedSignup(
                'danger',
                'A Meta retornou erro no cadastro incorporado. Verifique a configuração do Facebook Login for Business.'
            );
        }
    }

    window.addEventListener('message', sessionInfoListener);

    document.addEventListener('click', function(e){

        if(
            !e.target.closest('#btnConectarWhatsApp')
            &&
            !e.target.closest('#btnConectarWhatsAppVazio')
        ){
            return;
        }

        e.preventDefault();
        limparFeedbackEmbeddedSignup();

        const redirectUri = window.META_EMBEDDED_SIGNUP_REDIRECT_URI || '';
        const extras = {
            version: 'v4',
            sessionInfoVersion: '3',
            featureType: 'whatsapp_business_app_onboarding'
        };
        const url = new URL('https://business.facebook.com/messaging/whatsapp/onboard/');

        if(!window.META_APP_ID || !window.META_CONFIGURATION_ID || !redirectUri){
            exibirFeedbackEmbeddedSignup(
                'danger',
                'Configuração da Meta incompleta. Verifique as variáveis META_APP_ID, META_CONFIGURATION_ID e META_EMBEDDED_SIGNUP_REDIRECT_URI no .env.'
            );
            return;
        }

        url.searchParams.set('app_id', window.META_APP_ID || '');
        url.searchParams.set('config_id', window.META_CONFIGURATION_ID || '');
        url.searchParams.set('extras', JSON.stringify(extras));
        url.searchParams.set('redirect_uri', redirectUri);

        exibirFeedbackEmbeddedSignup(
            'info',
            'Abrindo o Cadastro Incorporado hospedado da Meta. Conclua todas as etapas na próxima tela.'
        );

        window.location.href = url.toString();

    });

})();

</script>
