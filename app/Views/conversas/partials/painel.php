<?php

if(!function_exists('formatarNumeroBR')){

    function formatarNumeroBR($numero)
    {
        $numero = preg_replace('/\D/', '', $numero);

        if(substr($numero, 0, 2) == '55'){
            $numero = substr($numero, 2);
        }

        if(strlen($numero) == 11){

            return '(' . substr($numero, 0, 2) . ') '
                . substr($numero, 2, 5)
                . '-'
                . substr($numero, 7);

        }

        if(strlen($numero) == 10){

            return '(' . substr($numero, 0, 2) . ') '
                . substr($numero, 2, 4)
                . '-'
                . substr($numero, 6);

        }

        return $numero;
    }

}

 if($conversaSelecionada){ ?>

    <?php

    $nomeSelecionado =
        $conversaSelecionada['CVS_Nome']
        ?: formatarNumeroBR($conversaSelecionada['CVS_Numero']);

    ?>

    <div class="card-header bg-light">

        <strong>
            <?= htmlspecialchars($nomeSelecionado); ?>
        </strong>

        <br>

        <small class="text-muted">
            <?= formatarNumeroBR($conversaSelecionada['CVS_Numero']); ?>
        </small>

    </div>

    <div
        id="areaMensagens"
        class="card-body conversa-bg"
        style="
            overflow-y:auto;
            background-color:#efeae2;
            background-image:
                radial-gradient(circle at 25px 25px, rgba(0,0,0,0.04) 2px, transparent 0),
                radial-gradient(circle at 75px 75px, rgba(0,0,0,0.03) 2px, transparent 0);
            background-size:100px 100px;
        "
    >
        <?php require __DIR__ . '/partials/mensagens.php'; ?>
    </div>

    <div class="card-footer bg-light">

        <?php if($janelaAberta){ ?>

            <form
                method="POST"
                id="formEnviarMensagem"
                action="<?= rtrim(BASE_URL, '/'); ?>/index.php?url=conversa/enviarAjax"
            >

                <input
                    type="hidden"
                    name="conversa_id"
                    value="<?= $conversaSelecionada['CVS_ID']; ?>"
                >

                <div class="input-group">

                    <input
                        type="text"
                        name="mensagem"
                        id="campoMensagem"
                        class="form-control"
                        placeholder="Digite uma mensagem..."
                        autocomplete="off"
                        required
                    >

                    <div class="input-group-append">

                        <button
                            id="btnEnviarMensagem"
                            class="btn btn-success"
                            type="submit"
                        >
                            <i class="fas fa-paper-plane"></i>
                        </button>

                    </div>

                </div>

            </form>

        <?php }else{ ?>

            <div class="alert alert-warning mb-0">
                A janela de atendimento de 24 horas está fechada.
                Para falar com este contato novamente, envie um template aprovado.
            </div>

        <?php } ?>

    </div>

<?php }else{ ?>

    <div class="card-body text-center text-muted d-flex align-items-center justify-content-center">
        Selecione uma conversa para visualizar as mensagens.
    </div>

<?php } ?>