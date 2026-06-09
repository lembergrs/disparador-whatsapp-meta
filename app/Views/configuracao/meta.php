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

?>

<div class="row">

    <div class="col-md-8">

        <div class="card">

            <div class="card-header">

                <h3 class="card-title">
                    Números WhatsApp conectados
                </h3>

                <div class="card-tools">

                    <button
                    type="button"
                    class="btn btn-success btn-sm"
                    id="btnConectarWhatsApp"
                    >
                        <i class="fab fa-whatsapp"></i>
                        Conectar novo número
                    </button>

                </div>

            </div>

            <div class="card-body">

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

                        <button
                        type="button"
                        class="btn btn-success"
                        id="btnConectarWhatsAppVazio"
                        >
                            <i class="fab fa-whatsapp"></i>
                            Conectar WhatsApp
                        </button>

                    </div>

                <?php }else{ ?>

                    <div class="table-responsive">

                        <table class="table table-bordered table-hover">

                            <thead>

                                <tr>
                                    <th>Nome</th>
                                    <th>Número</th>
                                    <th>Status</th>
                                    <th>Phone Number ID</th>
                                    <th>WABA ID</th>
                                    <th width="180">Ações</th>
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

                                            <?php if($conta['MTA_Status'] == 'conectado'){ ?>

                                                <span class="badge badge-success">
                                                    Conectado
                                                </span>

                                            <?php }else{ ?>

                                                <span class="badge badge-secondary">
                                                    <?= htmlspecialchars($conta['MTA_Status']); ?>
                                                </span>

                                            <?php } ?>

                                        </td>

                                        <td>
                                            <small>
                                                <?= htmlspecialchars($conta['MTA_PhoneNumberId']); ?>
                                            </small>
                                        </td>

                                        <td>
                                            <small>
                                                <?= htmlspecialchars($conta['MTA_WabaId']); ?>
                                            </small>
                                        </td>

                                        <td>

                                            <button
                                            type="button"
                                            class="btn btn-info btn-sm"
                                            disabled
                                            >
                                                <i class="fas fa-sync"></i>
                                                Templates
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
                    <li>Cada número terá seu próprio Phone Number ID.</li>
                    <li>O webhook identificará o número recebido.</li>
                    <li>Os limites poderão variar conforme o plano contratado.</li>
                </ul>

            </div>

        </div>

    </div>

</div>

<script>

document.addEventListener('click', function(e){

    if(
        !e.target.closest('#btnConectarWhatsApp')
        &&
        !e.target.closest('#btnConectarWhatsAppVazio')
    ){
        return;
    }

    if(typeof FB === 'undefined'){
        alert('SDK da Meta não carregado.');
        return;
    }

    FB.login(function(response){

        console.log('Embedded Signup response:', response);

        if(response.authResponse && response.authResponse.code){

            alert('Código recebido com sucesso.');

            console.log('CODE:', response.authResponse.code);

            return;
        }

        alert(
            'A conexão não foi concluída. Verifique se o popup da Meta foi autorizado até o final.'
        );

    }, {
        config_id: META_CONFIGURATION_ID,
        response_type: 'code',
        override_default_response_type: true,
        extras: {
            feature: 'whatsapp_embedded_signup'
        }
    });

});

</script>