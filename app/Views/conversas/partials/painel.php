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

    <div class="card-header bg-light conversa-painel-header">

        <div class="d-flex justify-content-between align-items-start conversa-painel-header-linha">

            <div class="conversa-painel-titulo">

                <strong>
                    <?= htmlspecialchars($nomeSelecionado, ENT_QUOTES, 'UTF-8'); ?>
                </strong>

                <small class="text-muted d-block">
                    <?= formatarNumeroBR($conversaSelecionada['CVS_Numero']); ?>
                </small>

            </div>

            <div class="text-right conversa-painel-responsavel">

                <small class="text-muted d-block">
                    <i class="fas fa-user-headset"></i>
                    <?= !empty($conversaSelecionada['ResponsavelNome'])
                        ? htmlspecialchars($conversaSelecionada['ResponsavelNome'], ENT_QUOTES, 'UTF-8')
                        : 'Sem responsável'; ?>
                </small>

                <?php if(!empty($podeAtribuirConversa)){ ?>
                    <button
                        type="button"
                        class="btn btn-xs btn-outline-info btn-atribuir mt-1"
                        data-id="<?= $conversaSelecionada['CVS_ID']; ?>"
                        data-responsavel="<?= (int) ($conversaSelecionada['ResponsavelId'] ?? 0); ?>"
                    >
                        <i class="fas fa-user-plus"></i>
                        Atribuir
                    </button>
                <?php } ?>

            </div>

        </div>

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
        <?php require __DIR__ . '/mensagens.php'; ?>
    </div>

    <div class="card-footer bg-light">

        <?php if($janelaAberta){ ?>

            <form
                method="POST"
                id="formEnviarMensagem"
                action="<?= rtrim(BASE_URL, '/'); ?>/index.php?url=conversa/enviarAjax"
                data-action-midia="<?= rtrim(BASE_URL, '/'); ?>/index.php?url=conversa/enviarMidiaAjax"
                enctype="multipart/form-data"
            >

                <input
                    type="hidden"
                    name="conversa_id"
                    value="<?= (int) $conversaSelecionada['CVS_ID']; ?>"
                >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>"
                >

                <div class="input-group position-relative">

                    <div class="input-group-prepend">
                        <button
                            type="button"
                            class="btn btn-outline-secondary"
                            id="btnAnexarMensagem"
                            title="Anexar arquivo"
                            aria-label="Anexar arquivo"
                        >
                            <i class="fas fa-paperclip"></i>
                        </button>
                        <input
                            type="file"
                            name="arquivo"
                            id="arquivoMensagem"
                            class="d-none"
                            accept=".jpg,.jpeg,.png,.webp,.pdf,.mp4,.3gpp,image/jpeg,image/png,image/webp,application/pdf,video/mp4,video/3gpp"
                        >
                        <button
                            type="button"
                            class="btn btn-outline-secondary"
                            id="btnEmojiMensagem"
                            title="Inserir emoji"
                            aria-label="Inserir emoji"
                        >
                            <i class="far fa-smile"></i>
                        </button>
                    </div>

                    <div
                        id="seletorEmojiMensagem"
                        class="bg-white border rounded shadow-sm p-2 d-none"
                        style="position:absolute;bottom:44px;left:0;z-index:1050;width:250px;"
                    >
                        <?php foreach(['😀','😃','😄','😁','😂','🤣','😊','😍','🥰','😘','😉','😎','🤔','😢','😭','😡','👍','👎','👏','🙏','❤️','🔥','🎉','✅','👀'] as $emoji){ ?>
                            <button type="button" class="btn btn-light btn-sm js-inserir-emoji" style="font-size:20px;width:38px;height:38px;padding:0;"><?= htmlspecialchars($emoji, ENT_QUOTES, 'UTF-8'); ?></button>
                        <?php } ?>
                    </div>

                    <input
                        type="text"
                        name="mensagem"
                        id="campoMensagem"
                        class="form-control"
                        placeholder="Digite uma mensagem..."
                        autocomplete="off"
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