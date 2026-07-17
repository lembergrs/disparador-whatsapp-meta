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
                                            <?php if(($conta['MTA_Status'] ?? '') === 'conectado'){ ?>
                                                <div class="alert alert-success py-1 mb-2">WhatsApp conectado e pronto para uso.</div>
                                            <?php }elseif(in_array(($conta['MTA_Status'] ?? ''), ['pendente_registro', 'erro_registro'], true)){ ?>
                                                <div class="alert <?= ($conta['MTA_Status'] ?? '') === 'erro_registro' ? 'alert-danger' : 'alert-warning'; ?> py-1 mb-2">Seu número foi vinculado à Meta, mas ainda falta concluir o registro para liberar os envios.</div>
                                            <?php } ?>
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


                                            <?php if(in_array(($conta['MTA_Status'] ?? ''), ['pendente_registro', 'erro_registro', 'requer_acao'], true) && !empty($conta['MTA_PhoneNumberId']) && !empty($conta['MTA_Token'])){ ?>
                                                <button
                                                type="button"
                                                class="btn btn-warning btn-sm btnConcluirRegistroWhatsApp"
                                                data-id="<?= (int) $conta['MTA_ID']; ?>"
                                                data-numero="<?= htmlspecialchars(formatarTelefoneConfiguracao($conta['MTA_NumeroTelefone']), ENT_QUOTES, 'UTF-8'); ?>"
                                                >
                                                    <i class="fas fa-key"></i>
                                                    Concluir registro
                                                </button>
                                            <?php } ?>

                                            <?php if(in_array(($conta['MTA_Status'] ?? ''), ['pendente_registro', 'erro_registro', 'requer_acao'], true)){ ?>
                                                <form method="POST" action="<?= BASE_URL; ?>/index.php?url=configuracao/atualizarStatusNumeroWhatsApp" class="d-inline">
                                                    <input type="hidden" name="conta_id" value="<?= (int) $conta['MTA_ID']; ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                                                    <button type="submit" class="btn btn-info btn-sm">
                                                        <i class="fas fa-sync"></i>
                                                        Atualizar status
                                                    </button>
                                                </form>
                                            <?php } ?>
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
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
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



<div class="modal fade" id="modalRegistroNumeroWhatsApp">

    <div class="modal-dialog">

        <div class="modal-content">

            <form method="POST" id="formRegistroNumeroWhatsApp" action="<?= BASE_URL; ?>/index.php?url=configuracao/registrarNumeroWhatsApp" autocomplete="off">

                <div class="modal-header">
                    <h4 class="modal-title">Concluir registro do WhatsApp</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body">
                    <input type="hidden" name="conta_id">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">

                    <div class="alert alert-warning">
                        <strong>Concluir conexão do WhatsApp</strong><br>
                        Seu número foi vinculado à Meta. Para concluir a conexão e liberar os envios, crie um PIN de 6 dígitos. Guarde esse PIN em local seguro, pois ele poderá ser solicitado futuramente pela Meta.
                    </div>

                    <p class="text-muted">
                        Número: <strong id="registroNumeroWhatsAppNumero"></strong>
                    </p>

                    <div class="form-group">
                        <label>PIN de 6 dígitos</label>
                        <input
                        type="password"
                        name="pin"
                        class="form-control pin-registro-whatsapp"
                        inputmode="numeric"
                        pattern="[0-9]{6}"
                        maxlength="6"
                        minlength="6"
                        autocomplete="off"
                        required
                        >
                    </div>
                    <div class="form-group">
                        <label>Confirmar PIN</label>
                        <input
                        type="password"
                        name="pin_confirmacao"
                        class="form-control pin-registro-whatsapp"
                        inputmode="numeric"
                        pattern="[0-9]{6}"
                        maxlength="6"
                        minlength="6"
                        autocomplete="off"
                        required
                        >
                        <small class="form-text text-muted">
                            O PIN será enviado somente para a Meta nesta tentativa e não será salvo pelo Disparador.
                        </small>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning" id="btnRegistrarNumeroWhatsApp">Concluir conexão</button>
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

    let tentativaAtiva = false;
    let signupState = null;
    let signupRequestId = null;
    let finishPayload = null;
    let oauthCode = null;
    let envioFinalizacaoEmAndamento = false;
    let coordenacaoTimer = null;
    const COORDENACAO_TIMEOUT_MS = 5000;

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

    function resetarTentativa()
    {
        tentativaAtiva = false;
        signupState = null;
        signupRequestId = null;
        finishPayload = null;
        oauthCode = null;
        envioFinalizacaoEmAndamento = false;
        if(coordenacaoTimer){ clearTimeout(coordenacaoTimer); }
        coordenacaoTimer = null;
        setBotoesConexao(false);
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

    function facebookSdkPronto()
    {
        return typeof FB !== 'undefined' && FB && typeof FB.login === 'function';
    }

    function finalizarQuandoPossivel(forcarPorTimeout)
    {
        if(envioFinalizacaoEmAndamento || !signupState || !oauthCode){
            return;
        }

        if(!finishPayload && !forcarPorTimeout){
            return;
        }

        envioFinalizacaoEmAndamento = true;
        if(coordenacaoTimer){ clearTimeout(coordenacaoTimer); coordenacaoTimer = null; }
        exibirFeedbackEmbeddedSignup('info', 'Finalizando a conexão com segurança...');

        postForm(BASE_URL + '/index.php?url=configuracao/finalizarEmbeddedSignup', {
            state: signupState,
            code: oauthCode,
            session_info: finishPayload ? JSON.stringify(finishPayload) : ''
        }).then(function(resp){
            exibirFeedbackEmbeddedSignup(
                resp.connected ? 'success' : 'warning',
                resp.message || 'Número vinculado com sucesso. Falta concluir o registro.'
            );
            setTimeout(function(){ window.location.reload(); }, resp.connected ? 1200 : 2500);
        }).catch(function(error){
            exibirFeedbackEmbeddedSignup('danger', (error && (error.message || error.detail)) || 'Não foi possível concluir a conexão com a Meta.');
            resetarTentativa();
        });
    }

    function registrarFinishMeta(data)
    {
        finishPayload = data;
        exibirFeedbackEmbeddedSignup('info', 'Cadastro concluído na Meta. Aguardando o código de autorização para finalizar.');
        finalizarQuandoPossivel(false);
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
            resetarTentativa();
            return;
        }
        if(data.event === 'ERROR'){
            exibirFeedbackEmbeddedSignup('danger', 'A Meta informou erro no cadastro. Revise os dados informados e tente novamente.');
            resetarTentativa();
        }
    }

    window.addEventListener('message', sessionInfoListener);

    function iniciarFacebookLogin(resp)
    {
        signupState = resp.state;
        signupRequestId = resp.requestId;
        finishPayload = null;
        oauthCode = null;
        envioFinalizacaoEmAndamento = false;

        exibirFeedbackEmbeddedSignup('info', 'Cadastro em andamento na Meta. Siga as etapas na janela exibida.');

        FB.login(function(loginResponse){
            if(!loginResponse || !loginResponse.authResponse || !loginResponse.authResponse.code){
                exibirFeedbackEmbeddedSignup('danger', 'A autorização da Meta não retornou o código necessário. Tente novamente.');
                resetarTentativa();
                return;
            }

            oauthCode = loginResponse.authResponse.code;
            exibirFeedbackEmbeddedSignup('info', 'Autorização recebida. Aguardando os dados finais do cadastro.');

            coordenacaoTimer = setTimeout(function(){
                finalizarQuandoPossivel(true);
            }, COORDENACAO_TIMEOUT_MS);

            finalizarQuandoPossivel(false);
        }, {
            config_id: resp.configurationId,
            response_type: 'code',
            override_default_response_type: true,
            extras: {
                sessionInfoVersion: '3',
                version: 'v4',
                state: resp.state
            }
        });
    }


    document.addEventListener('click', function(e){
        const botao = e.target.closest('.btnConcluirRegistroWhatsApp');
        if(!botao){ return; }
        e.preventDefault();
        const modal = document.getElementById('modalRegistroNumeroWhatsApp');
        const form = document.getElementById('formRegistroNumeroWhatsApp');
        if(!modal || !form){ return; }
        form.querySelector('[name="conta_id"]').value = botao.dataset.id || '';
        form.querySelector('[name="pin"]').value = '';
        form.querySelector('[name="pin_confirmacao"]').value = '';
        const numero = document.getElementById('registroNumeroWhatsAppNumero');
        if(numero){ numero.textContent = botao.dataset.numero || ''; }
        if(typeof $ !== 'undefined' && $('#modalRegistroNumeroWhatsApp').modal){
            $('#modalRegistroNumeroWhatsApp').modal('show');
        }
    });


    document.addEventListener('input', function(e){
        if(!e.target.classList.contains('pin-registro-whatsapp')){ return; }
        e.target.value = e.target.value.replace(/\D/g, '').substring(0, 6);
    });

    document.addEventListener('submit', function(e){
        const form = e.target.closest('#formRegistroNumeroWhatsApp');
        if(!form){ return; }
        const pin = form.querySelector('[name="pin"]').value;
        const confirmacao = form.querySelector('[name="pin_confirmacao"]').value;
        if(!/^[0-9]{6}$/.test(pin) || pin !== confirmacao){
            e.preventDefault();
            alert('Informe e confirme o PIN de 6 dígitos.');
            return;
        }
        const botao = document.getElementById('btnRegistrarNumeroWhatsApp');
        if(botao){
            botao.disabled = true;
            botao.textContent = 'Concluindo...';
        }
    });

    document.addEventListener('click', function(e){
        if(!e.target.closest('#btnConectarWhatsApp') && !e.target.closest('#btnConectarWhatsAppVazio')){ return; }
        e.preventDefault();
        if(tentativaAtiva){ return; }

        if(!facebookSdkPronto()){
            exibirFeedbackEmbeddedSignup('danger', 'O SDK do Facebook ainda não carregou. Atualize a página e tente novamente.');
            return;
        }

        tentativaAtiva = true;
        setBotoesConexao(true);
        exibirFeedbackEmbeddedSignup('info', 'Abrindo a Meta para iniciar o cadastro do WhatsApp...');

        postForm(BASE_URL + '/index.php?url=configuracao/iniciarEmbeddedSignup', {})
            .then(iniciarFacebookLogin)
            .catch(function(error){
                resetarTentativa();
                exibirFeedbackEmbeddedSignup('danger', (error && error.message) || 'Não foi possível iniciar o Cadastro Incorporado.');
            });
    });

})();

</script>
