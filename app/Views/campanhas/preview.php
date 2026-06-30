<div class="card">

    <div class="card-header">

        <h3>

            Pré-visualização da campanha

        </h3>

    </div>

    <div class="card-body">

        <p>

            <strong>Campanha:</strong>

            <?= $campanha['CAM_Nome']; ?>

        </p>

        <p>

            <strong>Contato exemplo:</strong>

            <?= $contato['CON_Nome']; ?>

        </p>

        <hr>

        <h5>
            Variáveis
        </h5>

        <table class="table table-bordered">

            <thead>

                <tr>

                    <th>Variável</th>

                    <th>Campo</th>

                    <th>Valor</th>

                </tr>

            </thead>

            <tbody>

            <?php

            $dados =
                json_decode(
                    $contato['CON_DadosJson'],
                    true
                );

            foreach($variaveis as $var){

            ?>

            <tr>

                <td>

                    {{<?= $var['CPV_Variavel']; ?>}}

                </td>

                <td>

                    <?= $var['CPV_Campo']; ?>

                </td>

                <td>

                    <?= $dados[
                        $var['CPV_Campo']
                    ] ?? ''; ?>

                </td>

            </tr>

            <?php } ?>

            </tbody>

        </table>
        
        <hr>

        <div class="alert alert-info">

            <strong>Template:</strong>
            <?= $campanha['TMP_Nome']; ?>

            <br>

            <strong>Idioma:</strong>
            <?= $campanha['TMP_Idioma']; ?>

        </div>

        <hr>


        <?php
        $headerTipo = strtoupper((string) ($campanha['TMP_HeaderTipo'] ?? ''));
        $headerUrl = trim((string) ($campanha['TMP_HeaderMidiaUrlExemplo'] ?? ''));
        $headerDocumentoNome = trim((string) ($campanha['TMP_HeaderDocumentoNome'] ?? ''));

        if($headerUrl !== ''){

            if(!preg_match('/^https?:\/\//i', $headerUrl)){
                $headerUrl = rtrim(BASE_URL, '/') . '/' . ltrim($headerUrl, '/');
            }
        }
        ?>

        <?php if(in_array($headerTipo, ['IMAGE', 'VIDEO', 'DOCUMENT'], true)){ ?>

        <h5>
            Mídia do template
        </h5>

        <div class="mb-3">

            <?php if($headerTipo === 'IMAGE' && $headerUrl !== ''){ ?>

                <img
                src="<?= htmlspecialchars($headerUrl, ENT_QUOTES); ?>"
                alt="Imagem do template"
                class="img-fluid rounded"
                style="max-width:100%;"
                >

            <?php }elseif($headerTipo === 'VIDEO' && $headerUrl !== ''){ ?>

                <video
                src="<?= htmlspecialchars($headerUrl, ENT_QUOTES); ?>"
                class="img-fluid rounded"
                style="max-width:100%;"
                controls
                ></video>

            <?php }else{ ?>

                <div class="alert alert-info py-2">
                    <?php if($headerTipo === 'IMAGE'){ ?>
                        <i class="fas fa-image"></i>
                        Imagem do template
                    <?php }elseif($headerTipo === 'VIDEO'){ ?>
                        <i class="fas fa-video"></i>
                        Vídeo do template
                    <?php }else{ ?>
                        <i class="fas fa-file-pdf"></i>
                        <?= htmlspecialchars($headerDocumentoNome !== '' ? $headerDocumentoNome : 'Documento do template', ENT_QUOTES); ?>
                    <?php } ?>
                </div>

            <?php } ?>

        </div>

        <hr>

        <?php } ?>

        <h5>

            Mensagem resultante

        </h5>

        <div
        class="alert alert-success"
        style="
            white-space: pre-line;
        "
        >

            <?= $mensagem; ?>

        </div>

    </div>

</div>

<div class="card">

    <div class="card-header">

        <h3 class="card-title">
            Enviar teste
        </h3>

    </div>

    <div class="card-body">

        <form
        method="POST"
        action="<?= BASE_URL; ?>/index.php?url=campanha/enviarTeste"
        >

            <input
            type="hidden"
            name="campanha_id"
            value="<?= $campanha['CAM_ID']; ?>"
            >

            <div class="form-group">

                <label>Número para teste</label>

                <input
                type="text"
                name="telefone"
                id="telefoneTeste"
                class="form-control"
                placeholder="(41) 99999-9999"
                required
                >
                <small class="text-muted">
                Informe apenas DDD + número. O código do Brasil (55) será adicionado automaticamente.
                </small>

            </div>

            <button
            type="submit"
            class="btn btn-success"
            onclick="return confirm('Enviar teste para este número?')"
            >

                <i class="fas fa-paper-plane"></i>
                Enviar Teste

            </button>

        </form>

    </div>

</div>

<div class="mt-3">

    <a
    href="<?= BASE_URL; ?>/index.php?url=campanha"
    class="btn btn-secondary"
    >

        <i class="fas fa-arrow-left"></i>
        Voltar

    </a>

    <a
    href="<?= BASE_URL; ?>/index.php?url=campanha/detalhes&id=<?= $campanha['CAM_ID']; ?>"
    class="btn btn-info"
    >

        <i class="fas fa-search"></i>
        Detalhes da Campanha

    </a>

</div>