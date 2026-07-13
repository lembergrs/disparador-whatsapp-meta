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
                    A conexão usa o Cadastro Incorporado da Meta, valida as permissões no backend
                    e só libera o número quando a WABA, o telefone e o webhook estiverem confirmados.
                </p>

                <ul class="mb-0">
                    <li>1 cliente pode ter vários números.</li>
                    <li>Cada número pode enviar campanhas e receber mensagens.</li>
                    <li>A auto resposta não substitui o atendimento humano.</li>
                    <li>Os limites poderão variar conforme o plano contratado.</li>
                    <li>Após conectar, use a tela de templates para sincronizar modelos imediatamente.</li>
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


<div
class="modal fade"
id="modalEmbeddedSignupMeta"
tabindex="-1"
role="dialog"
aria-labelledby="modalEmbeddedSignupMetaTitulo"
aria-hidden="true"
>
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalEmbeddedSignupMetaTitulo">
                    Conexão com a Meta
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <p>
                    A configuração do WhatsApp será aberta em uma nova aba da Meta.
                </p>

                <p>
                    Conclua todas as etapas nessa nova aba. Ao finalizar, você será redirecionado de volta para o Disparador.net.
                </p>

                <p class="mb-0">
                    <strong>Não feche esta página até concluir o processo.</strong>
                </p>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    Entendi
                </button>
                <button type="button" class="btn btn-primary" id="btnReabrirEmbeddedSignupMeta">
                    Abrir novamente, caso a nova aba não tenha aberto
                </button>
            </div>
        </div>
    </div>
</div>

<script>

(function(){

    let tentativaAtiva = false;
    let ultimaUrlEmbeddedSignupMeta = null;
    let ultimoStateEmbeddedSignupMeta = null;

    function exibirFeedbackEmbeddedSignup(tipo, mensagem)
    {
        const feedback = document.getElementById('embeddedSignupFeedback');
        if(!feedback){ return; }
        feedback.className = 'alert alert-' + tipo;
        feedback.textContent = mensagem;
    }

    function setBotoesConexao(disabled)
    {
        document.querySelectorAll('#btnConectarWhatsApp,#btnConectarWhatsAppVazio').forEach(function(botao){
            botao.disabled = disabled;
        });
    }

    function abrirEmbeddedSignupMeta()
    {
        if(!ultimaUrlEmbeddedSignupMeta){ return null; }
        const popup = window.open(ultimaUrlEmbeddedSignupMeta, '_blank');
        if(!popup){
            exibirFeedbackEmbeddedSignup('warning', 'O navegador bloqueou a nova aba. Clique em "Abrir novamente" e permita popups para continuar.');
        }
        return popup;
    }

    function exibirModalEmbeddedSignupMeta()
    {
        if(typeof $ !== 'undefined' && $('#modalEmbeddedSignupMeta').modal){
            $('#modalEmbeddedSignupMeta').modal('show');
        }
    }

    function postForm(url, data)
    {
        const form = new FormData();
        Object.keys(data).forEach(function(key){ form.append(key, data[key]); });
        form.append('csrf_token', CSRF_TOKEN || '');
        return fetch(url, { method: 'POST', credentials: 'same-origin', body: form }).then(function(response){
            return response.json().then(function(json){
                if(!response.ok || !json.ok){ throw json; }
                return json;
            });
        });
    }

    function registrarFinishMeta(data)
    {
        if(!ultimoStateEmbeddedSignupMeta){ return; }
        exibirFeedbackEmbeddedSignup('info', 'Autorização recebida. Estamos finalizando a conexão com segurança.');
        postForm(BASE_URL + '/index.php?url=configuracao/registrarEmbeddedSignupFinish', {
            state: ultimoStateEmbeddedSignupMeta,
            session_info: JSON.stringify(data)
        }).then(function(){
            exibirFeedbackEmbeddedSignup('success', 'Cadastro concluído na Meta. Aguarde a aba de retorno finalizar a conexão.');
        }).catch(function(error){
            exibirFeedbackEmbeddedSignup('danger', (error && error.message) || 'Não foi possível registrar a seleção feita na Meta. Refaça a conexão.');
            tentativaAtiva = false;
            setBotoesConexao(false);
        });
    }

    function sessionInfoListener(event)
    {
        if(event.origin !== 'https://www.facebook.com' && event.origin !== 'https://web.facebook.com'){
            return;
        }

        let data = null;
        try{ data = typeof event.data === 'string' ? JSON.parse(event.data) : event.data; }catch(e){ return; }
        if(!data || data.type !== 'WA_EMBEDDED_SIGNUP'){ return; }

        if(data.event === 'FINISH'){
            registrarFinishMeta(data);
            return;
        }
        if(data.event === 'CANCEL'){
            exibirFeedbackEmbeddedSignup('warning', 'Cadastro cancelado antes da conclusão. Você pode iniciar novamente quando quiser.');
            tentativaAtiva = false;
            setBotoesConexao(false);
            return;
        }
        if(data.event === 'ERROR'){
            exibirFeedbackEmbeddedSignup('danger', 'A Meta informou erro no cadastro. Revise os dados informados e tente novamente.');
            tentativaAtiva = false;
            setBotoesConexao(false);
        }
    }

    window.addEventListener('message', function(event){
        if(event.origin !== window.location.origin){ return; }
        const data = event.data || {};
        if(data.type !== 'DISPARADOR_META_EMBEDDED_SIGNUP_CALLBACK'){ return; }
        exibirFeedbackEmbeddedSignup(data.ok ? 'success' : 'danger', data.ok ? 'Conexão concluída. Atualizando a página...' : 'A conexão não foi concluída. Informe o código de diagnóstico ao suporte.');
        tentativaAtiva = false;
        setBotoesConexao(false);
        if(data.ok){ setTimeout(function(){ window.location.reload(); }, 1200); }
    });

    window.addEventListener('message', sessionInfoListener);

    document.addEventListener('click', function(e){
        if(!e.target.closest('#btnConectarWhatsApp') && !e.target.closest('#btnConectarWhatsAppVazio')){ return; }
        e.preventDefault();
        if(tentativaAtiva){ return; }

        tentativaAtiva = true;
        setBotoesConexao(true);
        exibirFeedbackEmbeddedSignup('info', 'Abrindo a Meta para iniciar o cadastro do WhatsApp...');

        postForm(BASE_URL + '/index.php?url=configuracao/iniciarEmbeddedSignup', {})
            .then(function(resp){
                ultimoStateEmbeddedSignupMeta = resp.state;
                const url = new URL('https://business.facebook.com/messaging/whatsapp/onboard/');
                url.searchParams.set('app_id', resp.appId);
                url.searchParams.set('config_id', resp.configurationId);
                url.searchParams.set('redirect_uri', resp.redirectUri);
                url.searchParams.set('response_type', 'code');
                url.searchParams.set('state', resp.state);
                url.searchParams.set('scope', 'whatsapp_business_management,whatsapp_business_messaging');
                url.searchParams.set('extras', JSON.stringify({ sessionInfoVersion: '3', version: 'v4' }));
                ultimaUrlEmbeddedSignupMeta = url.toString();
                abrirEmbeddedSignupMeta();
                exibirModalEmbeddedSignupMeta();
                exibirFeedbackEmbeddedSignup('info', 'Cadastro em andamento na Meta. Não feche esta página até aparecer a confirmação.');
            })
            .catch(function(error){
                tentativaAtiva = false;
                setBotoesConexao(false);
                exibirFeedbackEmbeddedSignup('danger', (error && error.message) || 'Não foi possível iniciar o Cadastro Incorporado.');
            });
    });

    document.addEventListener('click', function(e){
        if(!e.target.closest('#btnReabrirEmbeddedSignupMeta')){ return; }
        e.preventDefault();
        abrirEmbeddedSignupMeta();
    });

})();

</script>
