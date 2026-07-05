$(document).ready(function(){

    if($('.flashMessage').length){

        setTimeout(function(){

            $('.flashMessage').fadeOut(
                500
            );

        }, 2000);

    }


    aplicarMascaras();


    $('#gerarSenha').click(function(){

        const maiusculas = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        const minusculas = 'abcdefghijkmnopqrstuvwxyz';
        const numeros = '23456789';
        const especiais = '!@#$%&*?';
        const todos = maiusculas + minusculas + numeros + especiais;
        let senha = [
            maiusculas[Math.floor(Math.random() * maiusculas.length)],
            minusculas[Math.floor(Math.random() * minusculas.length)],
            numeros[Math.floor(Math.random() * numeros.length)],
            especiais[Math.floor(Math.random() * especiais.length)]
        ];

        while(senha.length < 10){
            senha.push(todos[Math.floor(Math.random() * todos.length)]);
        }

        senha = senha.sort(function(){ return Math.random() - 0.5; }).join('');

        $('#senha').val(senha).trigger('input');

    });



    $('#tipo_pessoa').change(function(){

        let tipo = $(this).val();





        if(tipo == 'PF'){

            $('#label_nome').html(
                'Nome Completo'
            );

            $('.area_razao_social').hide();





            $('.cpf_cnpj').unmask();

            $('.cpf_cnpj').mask(
                '000.000.000-00'
            );





        }else{

            $('#label_nome').html(
                'Nome Fantasia'
            );

            $('.area_razao_social').show();





            $('.cpf_cnpj').unmask();

            $('.cpf_cnpj').mask(
                '00.000.000/0000-00'
            );

        }

    });

    if($('#conteudoPreviewTemplateDisparo').length){
        resetarPreviewDisparo();
    }

    $('#tipo_pessoa').trigger('change');

        
    $('.cpf_cnpj').blur(function(){

        let campo = $(this);

        let tipo = $('#tipo_pessoa').val();

        let valor = campo.val();

        let valido = false;





        if(tipo == 'PF'){

            valido = validarCpf(valor);

        }else{

            valido = validarCnpj(valor);

        }





        if(!valido){

            alert('CPF/CNPJ inválido.');

            setTimeout(function(){

                campo.focus();

            }, 100);

        }

    });

    $('.btnEditarCliente').click(function(){

        $('#cliente_id').val(
            $(this).data('id')
        );

        $('[name=tipo_pessoa]').val(
            $(this).data('tipo')
        ).trigger('change');

        $('[name=cpf_cnpj]').val(
            $(this).data('documento')
        );

        $('[name=nome]').val(
            $(this).data('nome')
        );

        $('[name=razao_social]').val(
            $(this).data('razao')
        );

        $('[name=email]').val(
            $(this).data('email')
        );

        $('[name=telefone]').val(
            $(this).data('telefone')
        );

        $('[name=mensalidade]').val(
            $(this).data('mensalidade')
        );

        $('[name=vencimento]').val(
            $(this).data('vencimento')
        );

        $('[name=status]').val(
            $(this).data('status')
        );

        $('[name=observacoes]').val(
            $(this).data('observacoes')
        );

        $('#senha').val('').prop('required', false).trigger('input');

        $('#senha').attr(
            'placeholder',
            'Deixe vazio para manter a senha atual'
        );

        $('#modalCliente .modal-title').html(
            'Editar Cliente'
        );

        $('#modalCliente').modal(
            'show'
        );

    });

    $('#btnNovoCliente').click(function(){

        $('#cliente_id').val('');





        $('#modalCliente form')[0].reset();





        $('#modalCliente .modal-title').html(
            'Novo Cliente'
        );





        $('#senha').prop('required', true).trigger('input');

        $('#senha').attr(
            'placeholder',
            ''
        );





        $('#modalCliente').modal(
            'show'
        );

    });

    function resetarPreviewDisparo()
    {
        $('#conteudoPreviewTemplateDisparo').html(`
            <div class="text-center text-muted py-5">
                <i class="fas fa-file-alt fa-3x mb-3"></i>
                <h5>Preview da mensagem</h5>
                <p>Selecione um template para exibir a prévia.</p>
            </div>
        `);

        $('#previewTemplateDisparo').show();
    }


    function gerarWebhookVerifyTokenMeta()
    {
        let bytes = new Uint8Array(32);

        if(
            window.crypto
            &&
            window.crypto.getRandomValues
        ){

            window.crypto.getRandomValues(bytes);

            return Array.from(bytes)
            .map(function(byte){

                return byte.toString(16).padStart(2, '0');
            })
            .join('');
        }

        return Array.from(bytes)
        .map(function(){

            return Math.floor(Math.random() * 256)
            .toString(16)
            .padStart(2, '0');
        })
        .join('');
    }


    function definirAutoRespostaAtiva(valor, contexto)
    {
        contexto = contexto || $(document);

        contexto.find('[name=auto_resposta_ativa][value="' + (valor || 'N') + '"]').prop('checked', true);
    }

    function definirIntervaloAutoResposta(totalMinutos, contexto)
    {
        contexto = contexto || $(document);

        totalMinutos = parseInt(totalMinutos, 10);

        if(isNaN(totalMinutos) || totalMinutos <= 0){
            totalMinutos = 1440;
        }

        let horas = Math.floor(totalMinutos / 60);
        let minutos = totalMinutos % 60;

        if(horas > 24){
            horas = 24;
            minutos = 0;
        }

        contexto.find('[name=auto_resposta_intervalo_horas]').val(horas);
        contexto.find('[name=auto_resposta_intervalo_minutos_select]').val(minutos);
        contexto.find('[name=auto_resposta_intervalo_minutos]').val((horas * 60) + minutos);
    }

    function atualizarIntervaloAutoResposta(contexto)
    {
        contexto = contexto || $(document);

        let horas = parseInt(contexto.find('[name=auto_resposta_intervalo_horas]').val(), 10);
        let minutos = parseInt(contexto.find('[name=auto_resposta_intervalo_minutos_select]').val(), 10);

        horas = isNaN(horas) ? 0 : horas;
        minutos = isNaN(minutos) ? 0 : minutos;

        contexto.find('[name=auto_resposta_intervalo_minutos]').val((horas * 60) + minutos);
    }

    function atualizarPreviewWebhookVerifyTokenMeta()
    {
        let token = $('#webhook_verify_token').val();

        $('#metaWebhookVerifyTokenPreview').text(
            token || 'Informe ou gere um token'
        );
    }

    $(document).on(
        'click',
        '.btnEditarMeta',
        function(){

            $('#meta_id').val(
                $(this).data('id')
            );

            $('[name=cliente]').val(
                $(this).data('cliente')
            );

            $('[name=nome]').val(
                $(this).data('nome')
            );

            $('[name=phone_number_id]').val(
                $(this).data('phone')
            );

            $('[name=waba_id]').val(
                $(this).data('waba')
            );

            $('[name=token]').val('');

            $('[name=webhook_verify_token]').val(
                $(this).data('webhook-token')
            );

            atualizarPreviewWebhookVerifyTokenMeta();

            $('[name=url_base]').val(
                $(this).data('url')
            );

            $('[name=numero]').val(
                $(this).data('numero')
            );

            definirAutoRespostaAtiva(
                $(this).data('auto-resposta-ativa') || 'N',
                $('#formMeta')
            );

            $('[name=auto_resposta_texto]').val(
                $(this).data('auto-resposta-texto') || ''
            );

            definirIntervaloAutoResposta(
                $(this).data('auto-resposta-intervalo') || 1440,
                $('#formMeta')
            );

            $('#formMeta').attr(
                'action',
                BASE_URL
                + '/index.php?url=metaConta/atualizar'
            );

            $('#modalMeta .modal-title').html(
                'Editar Conta Meta'
            );

            $('#modalMeta').modal(
                'show'
            );

        }
    );

    $('#btnNovaMeta').click(function(){

        $('#formMeta')[0].reset();

        $('#meta_id').val('');

        $('[name=webhook_verify_token]').val(
            gerarWebhookVerifyTokenMeta()
        );

        atualizarPreviewWebhookVerifyTokenMeta();

        definirAutoRespostaAtiva('N', $('#formMeta'));
        $('[name=auto_resposta_texto]').val('');
        definirIntervaloAutoResposta(1440, $('#formMeta'));

        $('#formMeta').attr(
            'action',
            BASE_URL
            + '/index.php?url=metaConta/salvar'
        );





        $('#modalMeta .modal-title').html(
            'Nova Conta Meta'
        );

    });

    $('#btnGerarWebhookToken').click(function(){

        $('[name=webhook_verify_token]').val(
            gerarWebhookVerifyTokenMeta()
        );

        atualizarPreviewWebhookVerifyTokenMeta();

    });

    $(document).on(
        'submit',
        '#formMeta',
        function(e){

            if(
                $('#formMeta [name=auto_resposta_ativa]:checked').val() == 'S'
                &&
                $.trim($('[name=auto_resposta_texto]').val()) == ''
            ){
                e.preventDefault();
                alert('Informe o texto da auto resposta para ativá-la.');
                return false;
            }

            atualizarIntervaloAutoResposta($('#formMeta'));

            let intervalo = parseInt(
                $('#formMeta [name=auto_resposta_intervalo_minutos]').val(),
                10
            );

            if(isNaN(intervalo) || intervalo < 1){
                e.preventDefault();
                alert('Selecione um intervalo de pelo menos 1 minuto para a auto resposta.');
                return false;
            }
        }
    );

    $(document).on(
        'change',
        '[name=auto_resposta_intervalo_horas], [name=auto_resposta_intervalo_minutos_select]',
        function(){

            atualizarIntervaloAutoResposta(
                $(this).closest('form')
            );

        }
    );

    $(document).on(
        'click',
        '.btnConfigurarAutoRespostaCliente',
        function(){

            let form = $('#formAutoRespostaCliente');

            form.find('[name=conta_id]').val(
                $(this).data('id')
            );

            $('#autoRespostaClienteNumero').text(
                $(this).data('numero') || ''
            );

            definirAutoRespostaAtiva(
                $(this).data('auto-resposta-ativa') || 'N',
                form
            );

            form.find('[name=auto_resposta_texto]').val(
                $(this).data('auto-resposta-texto') || ''
            );

            definirIntervaloAutoResposta(
                $(this).data('auto-resposta-intervalo') || 1440,
                form
            );

            $('#modalAutoRespostaCliente').modal('show');

        }
    );

    $(document).on(
        'submit',
        '#formAutoRespostaCliente',
        function(e){

            let form = $(this);

            if(
                form.find('[name=auto_resposta_ativa]:checked').val() == 'S'
                &&
                $.trim(form.find('[name=auto_resposta_texto]').val()) == ''
            ){
                e.preventDefault();
                alert('Informe o texto da auto resposta para ativá-la.');
                return false;
            }

            atualizarIntervaloAutoResposta(form);

            let intervalo = parseInt(
                form.find('[name=auto_resposta_intervalo_minutos]').val(),
                10
            );

            if(isNaN(intervalo) || intervalo < 1){
                e.preventDefault();
                alert('Selecione um intervalo de pelo menos 1 minuto para a auto resposta.');
                return false;
            }
        }
    );

    $(document).on(
        'input',
        '[name=webhook_verify_token]',
        function(){

            atualizarPreviewWebhookVerifyTokenMeta();

        }
    );

    $(document).on(
        'click',
        '.btnVisualizarTemplate',
        function(){

            if(typeof abrirPreviewTemplate === 'function'){
                return;
            }

            $('#tmpNome').html(
                $(this).data('nome')
            );

            $('#tmpStatus').html(
                $(this).data('status')
            );

            $('#tmpIdioma').html(
                $(this).data('idioma')
            );

            $('#tmpCategoria').html(
                $(this).data('categoria')
            );


            let componentes =
                atob(
                    $(this).attr('data-componentes')
                );

            try{

                componentes =
                    JSON.parse(componentes);

            }catch(e){

                componentes = [];

            }

            let html = '';

            componentes.forEach(function(comp){

                html += `
                    <div class="mb-3">
                `;

                if(comp.type == 'HEADER'){

                    if(comp.format == 'TEXT'){

                        html += `
                            <div class="alert alert-secondary mb-2">
                                <strong>${comp.text ?? ''}</strong>
                            </div>
                        `;

                    }else if(comp.format == 'IMAGE'){

                        html += `
                            <div class="border rounded p-3 text-center bg-light mb-2">
                                <i class="fas fa-image fa-2x mb-2"></i>
                                <br>
                                <strong>Imagem no cabeçalho</strong>
                            </div>
                        `;

                    }else if(comp.format == 'VIDEO'){

                        html += `
                            <div class="border rounded p-3 text-center bg-light mb-2">
                                <i class="fas fa-video fa-2x mb-2"></i>
                                <br>
                                <strong>Vídeo no cabeçalho</strong>
                            </div>
                        `;

                    }else if(comp.format == 'DOCUMENT'){

                        html += `
                            <div class="border rounded p-3 text-center bg-light mb-2">
                                <i class="fas fa-file-alt fa-2x mb-2"></i>
                                <br>
                                <strong>Documento no cabeçalho</strong>
                            </div>
                        `;

                    }

                }

                if(comp.type == 'BODY' && comp.text){

                    html += `
                        <div class="border rounded p-2 mb-2">
                            ${comp.text.replace(/\n/g, '<br>')}
                        </div>
                    `;

                }

                if(comp.type == 'FOOTER' && comp.text){

                    html += `
                        <small class="text-muted">
                            ${comp.text}
                        </small>
                    `;

                }

                if(comp.type == 'BUTTONS' && comp.buttons){

                    html += `
                        <div class="mt-3">
                    `;

                    comp.buttons.forEach(function(btn){

                        let icone = 'fa-reply';

                        if(btn.type == 'URL'){
                            icone = 'fa-link';
                        }

                        if(btn.type == 'PHONE_NUMBER'){
                            icone = 'fa-phone';
                        }

                        html += `
                            <button
                            type="button"
                            class="btn btn-outline-primary btn-block btn-sm mb-1"
                            disabled
                            >
                                <i class="fas ${icone}"></i>
                                ${btn.text}
                            </button>
                        `;

                    });

                    html += `
                        </div>
                    `;

                }

                html += `
                    </div>
                `;

            });






            $('#templatePreview').html(
                html
            );


            let variaveis = [];





            function coletarVariaveisTextoTemplate(texto){
                let matches = String(texto || '').match(/{{(.*?)}}/g);

                if(matches){
                    matches.forEach(function(v){
                        v = v.replace('{{','').replace('}}','').trim();

                        if(v !== '' && !variaveis.includes(v)){
                            variaveis.push(v);
                        }
                    });
                }
            }

            componentes.forEach(function(comp){

                if(comp.text){
                    coletarVariaveisTextoTemplate(comp.text);
                }

                if(comp.type == 'BUTTONS' && comp.buttons){
                    comp.buttons.forEach(function(btn){
                        if(btn.url){
                            coletarVariaveisTextoTemplate(btn.url);
                        }
                    });
                }

            });





            let htmlVariaveis = '';





            if(variaveis.length > 0){

                variaveis.forEach(function(v){

                    htmlVariaveis += `
                        <span class="badge badge-primary mr-1">
                            Variável ${v}
                        </span>
                    `;

                });

            }else{

                htmlVariaveis =
                '<span class="text-muted">Sem variáveis</span>';

            }






            $('#templateVariaveis').html(
                htmlVariaveis
            );



            $('#modalTemplate').modal(
                'show'
            );

        }
    );

    $(document).on('change', '#templateCampanha', function(){

        let option = $(this).find(':selected');

        let componentesBase64 = option.attr('data-componentes');
        let headerMidiaUrlExemplo = normalizarUrlMidiaTemplateDisparo(option.attr('data-header-midia-url-exemplo'));
        let headerDocumentoNome = option.attr('data-header-documento-nome') || '';

        $('#camposVariaveis').html('');
        $('#areaMapeamentoVariaveis').hide();
        $('#conteudoPreviewTemplate').html('');
        $('#previewTemplateCampanha').hide();

        if(!componentesBase64){
            resetarPreviewDisparo();
            return;
        }

        let componentes = [];

        try{

            componentes = JSON.parse(
                atob(componentesBase64)
            );

            let previewHtml = '';

            componentes.forEach(function(comp){

                if(comp.type == 'HEADER' && comp.text){

                    previewHtml += `
                        <div class="mb-2">
                            <strong>${comp.text}</strong>
                        </div>
                    `;

                }

                if(comp.type == 'HEADER' && ['IMAGE','VIDEO','DOCUMENT'].indexOf(String(comp.format || '').toUpperCase()) >= 0){
                    let formato = String(comp.format || '').toUpperCase();

                    if(formato == 'IMAGE' && headerMidiaUrlExemplo){
                        previewHtml += `<div class="mb-2"><img src="${escapeHtmlDisparo(headerMidiaUrlExemplo)}" alt="Imagem do template" class="img-fluid rounded" style="max-width:100%;"></div>`;
                    }else if(formato == 'VIDEO' && headerMidiaUrlExemplo){
                        previewHtml += `<div class="mb-2"><video src="${escapeHtmlDisparo(headerMidiaUrlExemplo)}" class="img-fluid rounded" controls style="max-width:100%;"></video></div>`;
                    }else{
                        let icone = formato == 'IMAGE' ? 'fa-image' : (formato == 'VIDEO' ? 'fa-video' : 'fa-file-pdf');
                        let nome = formato == 'IMAGE' ? 'Imagem do template' : (formato == 'VIDEO' ? 'Vídeo do template' : (headerDocumentoNome || comp.media_name || 'Documento do template'));
                        previewHtml += `<div class="alert alert-info py-2"><i class="fas ${icone}"></i> ${escapeHtmlDisparo(nome)}</div>`;
                    }
                }

                if(comp.type == 'BODY' && comp.text){

                    previewHtml += `
                        <div class="mb-2">
                            ${comp.text}
                        </div>
                    `;

                }

                if(comp.type == 'FOOTER' && comp.text){

                    previewHtml += `
                        <div class="text-muted small mt-2">
                            ${comp.text}
                        </div>
                    `;

                }

                if(comp.type == 'BUTTONS' && comp.buttons){

                    previewHtml += `<div class="mt-3">`;

                    comp.buttons.forEach(function(btn){

                        previewHtml += `
                            <button
                            type="button"
                            class="btn btn-outline-primary btn-sm mr-1 mb-1"
                            disabled
                            >
                                ${btn.text}
                            </button>
                        `;

                    });

                    previewHtml += `</div>`;

                }

            });

            if(previewHtml != ''){

                $('#conteudoPreviewTemplate').html(previewHtml);
                $('#previewTemplateCampanha').show();

            }else{

                $('#conteudoPreviewTemplate').html('');
                $('#previewTemplateCampanha').hide();

            }

        }catch(e){

            return;

        }

        let variaveis = [];

        function coletarVariaveisCampanha(texto){
            let matches = String(texto || '').match(/{{(.*?)}}/g);

            if(matches){
                matches.forEach(function(v){
                    v = v.replace('{{','').replace('}}','').trim();

                    if(v !== '' && !variaveis.includes(v)){
                        variaveis.push(v);
                    }
                });
            }
        }

        componentes.forEach(function(comp){

            if(comp.text){
                coletarVariaveisCampanha(comp.text);
            }

            if(comp.type == 'BUTTONS' && comp.buttons){
                comp.buttons.forEach(function(btn){
                    if(btn.url){
                        coletarVariaveisCampanha(btn.url);
                    }
                });
            }

        });

        if(variaveis.length == 0){
            return;
        }

        let html = '';

        variaveis.forEach(function(v){

            html += `
                <div class="form-group">

                    <label>Variável {{${v}}}</label>

                    <select
                    name="variaveis[${v}]"
                    class="form-control"
                    required
                    >

                        <option value="">
                            Selecione o campo da planilha
                        </option>
            `;

            window.CAMPOS_CONTATO.forEach(function(campo){

                html += `
                    <option value="${campo}">
                        ${campo}
                    </option>
                `;

            });

            html += `
                    </select>

                </div>
            `;

        });

        $('#camposVariaveis').html(html);
        $('#areaMapeamentoVariaveis').show();

    });

    $(document).on('change', '#template', function(){

        let option =
            $(this).find(':selected');

        let componentesBase64 =
            option.attr('data-componentes');
        let headerMidiaUrlExemplo = normalizarUrlMidiaTemplateDisparo(option.attr('data-header-midia-url-exemplo'));
        let headerDocumentoNome = option.attr('data-header-documento-nome') || '';

        $('#conteudoPreviewTemplateDisparo').html('');
        $('#previewTemplateDisparo').hide();

        if(!componentesBase64){
            atualizarAjudaNumerosDestinoDisparo(0);
            return;
        }

        let componentes = [];

        try{

            componentes =
                JSON.parse(
                    atob(componentesBase64)
                );

        }catch(e){

            return;

        }

        let previewHtml = '';

        componentes.forEach(function(comp){

            if(comp.type == 'HEADER' && comp.text){

                previewHtml += `
                    <div class="mb-2">
                        <strong>${comp.text}</strong>
                    </div>
                `;

            }

            if(comp.type == 'HEADER' && ['IMAGE','VIDEO','DOCUMENT'].indexOf(String(comp.format || '').toUpperCase()) >= 0){
                let formato = String(comp.format || '').toUpperCase();

                if(formato == 'IMAGE' && headerMidiaUrlExemplo){
                    previewHtml += `<div class="mb-2"><img src="${escapeHtmlDisparo(headerMidiaUrlExemplo)}" alt="Imagem do template" class="img-fluid rounded" style="max-width:100%;"></div>`;
                }else if(formato == 'VIDEO' && headerMidiaUrlExemplo){
                    previewHtml += `<div class="mb-2"><video src="${escapeHtmlDisparo(headerMidiaUrlExemplo)}" class="img-fluid rounded" controls style="max-width:100%;"></video></div>`;
                }else{
                    let icone = formato == 'IMAGE' ? 'fa-image' : (formato == 'VIDEO' ? 'fa-video' : 'fa-file-pdf');
                    let nome = formato == 'IMAGE' ? 'Imagem do template' : (formato == 'VIDEO' ? 'Vídeo do template' : (headerDocumentoNome || comp.media_name || 'Documento do template'));
                    previewHtml += `<div class="alert alert-info py-2"><i class="fas ${icone}"></i> ${escapeHtmlDisparo(nome)}</div>`;
                }
            }

            if(comp.type == 'BODY' && comp.text){

                previewHtml += `
                    <div class="mb-2">
                        ${comp.text}
                    </div>
                `;

            }

            if(comp.type == 'FOOTER' && comp.text){

                previewHtml += `
                    <div class="text-muted small mt-2">
                        ${comp.text}
                    </div>
                `;

            }

            if(comp.type == 'BUTTONS' && comp.buttons){

                previewHtml += `<div class="mt-3">`;

                comp.buttons.forEach(function(btn){

                    previewHtml += `
                        <button
                        type="button"
                        class="btn btn-outline-primary btn-sm mr-1 mb-1"
                        disabled
                        >
                            ${btn.text}
                        </button>
                    `;

                });

                previewHtml += `</div>`;

            }

        });

        if(previewHtml != ''){

            $('#conteudoPreviewTemplateDisparo').html(
                previewHtml
            );

            $('#previewTemplateDisparo').show();

        }

        let variaveis = obterVariaveisTemplateDisparo(componentes);

        atualizarAjudaNumerosDestinoDisparo(variaveis.length);

    });

    function formatarNumeroDisparo(numero)
    {
        numero = String(numero || '').replace(/\D/g, '');

        if(numero.substring(0,2) == '55'){
            numero = numero.substring(2);
        }

        if(numero.length == 11){
            return '(' + numero.substring(0,2) + ') ' +
                numero.substring(2,7) + '-' +
                numero.substring(7);
        }

        if(numero.length == 10){
            return '(' + numero.substring(0,2) + ') ' +
                numero.substring(2,6) + '-' +
                numero.substring(6);
        }

        return numero;
    }

    function limparNumeroDisparo(numero)
    {
        numero = String(numero || '').replace(/\D/g, '');

        if(numero.length > 0 && numero.substring(0,2) != '55'){
            numero = '55' + numero;
        }

        return numero;
    }

    function obterParteTelefoneLinhaDisparo(linha)
    {
        linha = String(linha || '');

        let indiceVirgula = linha.indexOf(',');

        if(indiceVirgula === -1){
            return {
                telefone: linha,
                complemento: ''
            };
        }

        return {
            telefone: linha.substring(0, indiceVirgula),
            complemento: linha.substring(indiceVirgula)
        };
    }

    function formatarLinhaNumeroDestinoDisparo(linha)
    {
        let partesLinha = obterParteTelefoneLinhaDisparo(linha);
        let numeroLimpo = limparNumeroDisparo(partesLinha.telefone);

        if(numeroLimpo === ''){
            return null;
        }

        return formatarNumeroDisparo(numeroLimpo) + partesLinha.complemento;
    }

    function contarNumerosDestinoDisparo()
    {
        let campo = $('#numerosDestino');

        if(!campo.length){
            return 0;
        }

        let total = 0;
        let linhas = campo.val().split(/\r?\n/);

        linhas.forEach(function(linha){
            if(String(linha || '').trim() === ''){
                return;
            }

            let partesLinha = obterParteTelefoneLinhaDisparo(linha);

            if(limparNumeroDisparo(partesLinha.telefone) !== ''){
                total++;
            }
        });

        return total;
    }

    function obterTotalListaContatosDisparo()
    {
        let option = $('#listaContatosDisparo').find(':selected');
        return parseInt(option.attr('data-total') || '0', 10) || 0;
    }

    function atualizarContadorNumerosDestinoDisparo()
    {
        let manuais = contarNumerosDestinoDisparo();
        let lista = obterTotalListaContatosDisparo();
        let estimado = manuais + lista;

        $('#contadorNumerosDestino').text(
            'Números digitados: ' + manuais +
            ' | Contatos da lista: ' + lista +
            ' | Total final estimado: ' + estimado
        );
    }

    let timeoutContadorNumerosDestinoDisparo = null;

    function agendarAtualizacaoContadorNumerosDestinoDisparo()
    {
        if(timeoutContadorNumerosDestinoDisparo){
            clearTimeout(timeoutContadorNumerosDestinoDisparo);
        }

        timeoutContadorNumerosDestinoDisparo = setTimeout(function(){
            atualizarContadorNumerosDestinoDisparo();
        }, 150);
    }

    function escapeHtmlDisparo(texto)
    {
        return String(texto ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function normalizarUrlMidiaTemplateDisparo(url)
    {
        url = String(url || '').trim();

        if(url === ''){
            return '';
        }

        if(/^https?:\/\//i.test(url)){
            return url;
        }

        let base = String(typeof BASE_URL !== 'undefined' ? BASE_URL : '').replace(/\/$/, '');

        if(url.charAt(0) === '/'){
            return base + url;
        }

        return base + '/' + url.replace(/^\/+/, '');
    }

    function encodeDetalhesDisparo(dados)
    {
        try{
            return btoa(
                unescape(
                    encodeURIComponent(
                        JSON.stringify(dados || {}, null, 2)
                    )
                )
            );
        }catch(e){
            return '';
        }
    }

    function decodeDetalhesDisparo(dados)
    {
        try{
            return decodeURIComponent(
                escape(
                    atob(dados)
                )
            );
        }catch(e){
            return 'Detalhes indisponíveis.';
        }
    }

    function obterComponentesTemplateDisparo()
    {
        let option = $('#template').find(':selected');
        let componentesBase64 = option.attr('data-componentes');

        if(!componentesBase64){
            return [];
        }

        try{
            return JSON.parse(atob(componentesBase64));
        }catch(e){
            return [];
        }
    }

    function obterVariaveisTemplateDisparo(componentes)
    {
        let variaveis = [];

        function coletarVariaveisTexto(texto)
        {
            let matches = String(texto || '').match(/{{(.*?)}}/g);

            if(!matches){
                return;
            }

            matches.forEach(function(v){
                v = v.replace('{{','').replace('}}','').trim();

                if(v !== '' && !variaveis.includes(v)){
                    variaveis.push(v);
                }
            });
        }

        componentes.forEach(function(comp){
            if(comp.text){
                coletarVariaveisTexto(comp.text);
            }

            if(comp.type == 'BUTTONS' && comp.buttons){
                comp.buttons.forEach(function(botao){
                    if(botao.url){
                        coletarVariaveisTexto(botao.url);
                    }
                });
            }
        });

        let todasNumericas = variaveis.length > 0 && variaveis.every(function(v){
            return !isNaN(v);
        });

        if(todasNumericas){
            variaveis.sort(function(a, b){
                return parseInt(a) - parseInt(b);
            });
        }

        return variaveis;
    }

    function atualizarAjudaNumerosDestinoDisparo(quantidadeVariaveis)
    {
        let formato = 'Número';
        let exemplos = [
            '(41) 99999-9999',
            '(41) 98888-8888'
        ];
        let placeholder = exemplos.join('\n');

        if(quantidadeVariaveis == 1){
            formato = 'Número,Variável 1';
            exemplos = [
                '(41) 99999-9999,Rodrigo',
                '(41) 98888-8888,João'
            ];
            placeholder = exemplos.join('\n');
        }else if(quantidadeVariaveis == 2){
            formato = 'Número,Variável 1,Variável 2';
            exemplos = [
                '(41) 99999-9999,Rodrigo,Pedido 123',
                '(41) 98888-8888,João,Pedido 456'
            ];
            placeholder = exemplos.join('\n');
        }else if(quantidadeVariaveis >= 3){
            let partesFormato = ['Número'];

            for(let i = 1; i <= quantidadeVariaveis; i++){
                partesFormato.push('Variável ' + i);
            }

            formato = partesFormato.join(',');
            exemplos = [
                '(41) 99999-9999,Rodrigo,Pedido 123,Valor extra',
                '(41) 98888-8888,João,Pedido 456,Valor extra'
            ];
            placeholder = exemplos.join('\n');
        }

        $('#numerosDestino').attr('placeholder', placeholder);

        $('#ajudaNumerosDestino').html(
            '<strong>Formato esperado:</strong><br>' +
            escapeHtmlDisparo(formato) +
            '<br><br>' +
            '<strong>Exemplo:</strong><br>' +
            exemplos.map(escapeHtmlDisparo).join('<br>')
        );
    }

    function montarMensagemAproximadaDisparo(componentes, variaveis, valores)
    {
        let partes = [];

        componentes.forEach(function(comp){
            if(['HEADER', 'BODY', 'FOOTER'].includes(comp.type) && comp.text){
                let texto = comp.text;

                variaveis.forEach(function(v, index){
                    texto = texto.replace(
                        new RegExp('{{\\s*' + v + '\\s*}}', 'g'),
                        valores[index] || ''
                    );
                });

                partes.push(texto);
            }
        });

        return partes.join('\n\n');
    }

    function parseDestinosDisparo()
    {
        let componentes = obterComponentesTemplateDisparo();
        let variaveis = obterVariaveisTemplateDisparo(componentes);
        let linhas = $('#numerosDestino').val().split(/\r?\n/);
        let destinos = [];
        let erros = [];
        let temExtrasSemVariaveis = false;
        let numerosUsados = [];

        linhas.forEach(function(linhaOriginal, index){
            let linha = linhaOriginal.trim();

            if(linha === ''){
                return;
            }

            let partes = linha.split(',');
            let numeroOriginal = partes.shift().trim();
            let numeroLimpo = limparNumeroDisparo(numeroOriginal);
            let valores = partes.map(function(valor){
                return valor.trim();
            });

            if(numeroLimpo === ''){
                erros.push('Linha ' + (index + 1) + ': número não informado.');
                return;
            }

            if(numerosUsados.includes(numeroLimpo)){
                return;
            }

            if(variaveis.length > 0 && valores.length < variaveis.length){
                erros.push(
                    'Linha ' + (index + 1) + ': o template exige ' +
                    variaveis.length + ' variável(is), mas foram informada(s) ' +
                    valores.length + '.'
                );
                return;
            }

            if(variaveis.length > 0){
                valores = valores.slice(0, variaveis.length);
            }

            if(variaveis.length == 0 && valores.length > 0){
                temExtrasSemVariaveis = true;
            }

            numerosUsados.push(numeroLimpo);

            destinos.push({
                linha: index + 1,
                numero: numeroLimpo,
                numero_formatado: formatarNumeroDisparo(numeroLimpo),
                valores: valores,
                mensagem: montarMensagemAproximadaDisparo(
                    componentes,
                    variaveis,
                    valores
                )
            });
        });

        return {
            componentes: componentes,
            variaveis: variaveis,
            destinos: destinos,
            erros: erros,
            temExtrasSemVariaveis: temExtrasSemVariaveis
        };
    }

    function atualizarPreviewDestinosDisparo(parse)
    {
        if(parse.destinos.length == 0){
            return;
        }

        let html = `
            <div class="mb-2">
                <strong>Prévia dos destinos</strong>
            </div>
        `;

        parse.destinos.slice(0, 10).forEach(function(destino){
            html += `
                <div class="border rounded p-2 mb-2">
                    <div>
                        <strong>${escapeHtmlDisparo(destino.numero_formatado)}</strong>
                    </div>
            `;

            if(parse.variaveis.length > 0){
                html += '<ul class="mb-2 pl-3">';

                parse.variaveis.forEach(function(v, index){
                    html += `
                        <li>
                            {{${escapeHtmlDisparo(v)}}}:
                            ${escapeHtmlDisparo(destino.valores[index] || '')}
                        </li>
                    `;
                });

                html += '</ul>';
            }

            if(destino.mensagem){
                html += `
                    <div class="small text-muted">
                        ${escapeHtmlDisparo(destino.mensagem).replace(/\n/g, '<br>')}
                    </div>
                `;
            }

            html += '</div>';
        });

        if(parse.destinos.length > 10){
            html += `
                <div class="text-muted small">
                    Exibindo 10 de ${parse.destinos.length} destino(s).
                </div>
            `;
        }

        $('#conteudoPreviewTemplateDisparo').html(html);
        $('#previewTemplateDisparo').show();
    }

    $(document).on('input paste', '#numerosDestino', function(){
        agendarAtualizacaoContadorNumerosDestinoDisparo();
    });

    $(document).on('change', '#listaContatosDisparo', function(){
        atualizarContadorNumerosDestinoDisparo();
    });

    $(document).on('blur', '#numerosDestino', function(){

        let linhas = $(this).val().split(/\r?\n/);
        let formatadas = [];

        linhas.forEach(function(linha){
            if(String(linha || '').trim() === ''){
                return;
            }

            let linhaFormatada = formatarLinhaNumeroDestinoDisparo(linha);

            if(linhaFormatada === null){
                return;
            }

            formatadas.push(linhaFormatada);
        });

        $(this).val(formatadas.join("\n"));
        atualizarContadorNumerosDestinoDisparo();

    });

    $(document).on('change', '#meta', function(){

        let metaId = $(this).val();

        let templateSelect = $('#template');

        templateSelect.html('');


        resetarPreviewDisparo();

        if(metaId == ''){

            templateSelect.append(`
                <option value="">
                    Selecione primeiro a Conta Meta
                </option>
            `);

            templateSelect.prop('disabled', true);
            resetarPreviewDisparo();
            atualizarAjudaNumerosDestinoDisparo(0);

            return;

        }

        templateSelect.append(`
            <option value="">
                Selecione
            </option>
        `);

        let totalTemplatesAprovados = 0;

        window.TEMPLATES_DISPARO.forEach(function(template){

            if(String(template.MTA_ID) == String(metaId)){

                totalTemplatesAprovados++;

                templateSelect.append(`
                    <option
                        value="${template.TMP_ID}"
                        data-componentes="${btoa(template.TMP_Componentes)}"
                        data-header-tipo="${escapeHtmlDisparo(template.TMP_HeaderTipo || '')}"
                        data-header-midia-url-exemplo="${escapeHtmlDisparo(template.TMP_HeaderMidiaUrlExemplo || '')}"
                        data-header-documento-nome="${escapeHtmlDisparo(template.TMP_HeaderDocumentoNome || '')}"
                    >
                        ${template.TMP_Nome}
                    </option>
                `);

            }

        });

        if(totalTemplatesAprovados === 0){
            templateSelect.html(`
                <option value="">
                    Nenhum template aprovado disponível para envio nesta conta.
                </option>
            `);
            templateSelect.prop('disabled', true);
            return;
        }

        templateSelect.prop('disabled', false);

    });

    if(
        $('#meta').length
        &&
        typeof window.TOTAL_CONTAS_META !== 'undefined'
        &&
        window.TOTAL_CONTAS_META == 1
    ){

        setTimeout(function(){

            $('#meta').trigger('change');
            $('#template').prop('disabled', false);

        }, 300);

    }

    $('#telefoneTeste').mask('(00) 00000-0000');

    atualizarAjudaNumerosDestinoDisparo(0);
    atualizarContadorNumerosDestinoDisparo();

    let cancelarDisparo = false;
    let intervaloStatusDisparo = null;
    let statusDisparoPorMessageId = {};

    function restaurarBotaoEnviarDisparo()
    {
        $('#btnEnviarDisparo')
            .prop('disabled', false)
            .html('<i class="fas fa-paper-plane"></i> Enviar Template');
    }

    function resetarEstadoVisualDisparo()
    {
        $('#painelExecucaoDisparo').hide();
        $('#painelEdicaoDisparo').show();
        $('#areaProgressoDisparo').hide();
        $('#resumoFinalDisparo').html('');
        $('#listaStatusNumeros').html('');

        $('#barraProgressoDisparo')
            .removeClass('progress-bar-animated')
            .css('width', '0%')
            .html('0%');

        $('#textoProgressoDisparo').html('Preparando envio...');
        $('#btnPararDisparo')
            .prop('disabled', false)
            .html('<i class="fas fa-stop"></i> Parar envio');

        restaurarBotaoEnviarDisparo();
    }

    function restaurarTelaEdicaoDisparoAposErro()
    {
        $('#painelExecucaoDisparo').hide();
        $('#painelEdicaoDisparo').show();
        $('#barraProgressoDisparo').removeClass('progress-bar-animated');
        restaurarBotaoEnviarDisparo();
    }

    $(document).on('click', '#btnPararDisparo', function(){

        cancelarDisparo = true;

        $(this)
            .prop('disabled', true)
            .html('<i class="fas fa-spinner fa-spin"></i> Parando...');

    });

    $(document).on('submit', '#formDisparo', function(e){

        e.preventDefault();

        cancelarDisparo = false;
        statusDisparoPorMessageId = {};

        if(typeof window.finalizarMonitoramentoLoteDisparo === 'function'){
            window.finalizarMonitoramentoLoteDisparo();
        }

        if(intervaloStatusDisparo){
            clearInterval(intervaloStatusDisparo);
            intervaloStatusDisparo = null;
        }

        let form = $(this);
        let parse = parseDestinosDisparo();

        if(parse.erros.length > 0){
            alert(parse.erros.join("\n"));
            return;
        }

        let listaSelecionadaId = $('#listaContatosDisparo').val() || '';
        let totalListaSelecionada = obterTotalListaContatosDisparo();

        if(listaSelecionadaId === '' && parse.destinos.length == 0){
            alert('Informe pelo menos um número válido ou selecione uma lista de contatos.');
            return;
        }

        if(listaSelecionadaId !== '' && parse.variaveis.length > 0){
            alert('Este template possui variáveis. Nesta etapa, use listas apenas com templates sem variáveis ou informe os números manualmente com as variáveis necessárias.');
            return;
        }

        if(listaSelecionadaId !== '' && totalListaSelecionada == 0 && parse.destinos.length == 0){
            alert('A lista selecionada não possui contatos ativos. Informe números manualmente ou escolha outra lista.');
            return;
        }

        atualizarPreviewDestinosDisparo(parse);

        if(parse.temExtrasSemVariaveis){
            if(!confirm(
                'O template selecionado não possui variáveis, mas algumas linhas têm dados após a vírgula. Esses dados extras serão ignorados. Deseja continuar?'
            )){
                return;
            }
        }

        if(!confirm(
            'Confira a prévia dos destinos e confirme o envio estimado de ' +
            (parse.destinos.length + totalListaSelecionada) + ' mensagem(ns). Deseja iniciar agora?'
        )){
            return;
        }

        $('#resumoFinalDisparo').html('');
        $('#listaStatusNumeros').html('');

        $('#areaProgressoDisparo').show();

        $('#barraProgressoDisparo')
            .addClass('progress-bar-animated')
            .css('width', '3%')
            .html('3%');

        $('#textoProgressoDisparo').html(
            'Preparando envio e salvando destinos na fila...'
        );

        $('#painelEdicaoDisparo').hide();
        $('#painelExecucaoDisparo').show();

        $('#btnEnviarDisparo')
            .prop('disabled', true)
            .html('<i class="fas fa-spinner fa-spin"></i> Enviando...');

        $('#btnPararDisparo')
            .prop('disabled', false)
            .html('<i class="fas fa-stop"></i> Parar envio');

        for(let i = 0; i < totalListaSelecionada; i++){
            $('#listaStatusNumeros').append(`
                <tr id="linha_numero_${i}">
                    <td>Contato da lista selecionada</td>
                    <td><span class="badge badge-secondary">Na fila</span></td>
                    <td class="small text-muted">-</td>
                    <td>-</td>
                </tr>
            `);
        }

        parse.destinos.forEach(function(destino, index){
            index = index + totalListaSelecionada;

            let variaveisHtml = '';

            if(parse.variaveis.length > 0){
                variaveisHtml = '<div class="small text-muted">';

                parse.variaveis.forEach(function(v, i){
                    variaveisHtml +=
                        '{{' + escapeHtmlDisparo(v) + '}}: ' +
                        escapeHtmlDisparo(destino.valores[i] || '') + '<br>';
                });

                variaveisHtml += '</div>';
            }

            $('#listaStatusNumeros').append(`
                <tr id="linha_numero_${index}">
                    <td>
                        ${escapeHtmlDisparo(destino.numero_formatado)}
                        ${variaveisHtml}
                    </td>
                    <td>
                        <span class="badge badge-secondary">
                            Na fila
                        </span>
                    </td>
                    <td class="small text-muted">-</td>
                    <td>-</td>
                </tr>
            `);

        });

        $('#linha_numero_0 td:eq(1)').html('<span class="badge badge-info">Preparando envio</span>');
        $('#linha_numero_0 td:eq(2)').html('<span class="text-muted">Preparando envio para a Meta.</span>');

        let total = parse.destinos.length;
        let loteId = null;
        let timeoutPollingLote = null;
        let timeoutProximoBloco = null;
        let consultasStatusLote = 0;
        let estadoItensLote = {};
        let processandoBlocoLote = false;
        let processamentoImediatoAtivo = false;
        let consultandoLote = false;

        function montarDestinoFila(destino)
        {
            let variaveis = {};

            parse.variaveis.forEach(function(v, index){
                variaveis[v] = destino.valores[index] || '';
            });

            return {
                numero: destino.numero,
                variaveis: variaveis
            };
        }

        function badgeStatusFila(status)
        {
            const labels = {
                pendente: 'Na fila',
                processando: 'Enviando',
                aguardando_confirmacao: 'Aguardando confirmação',
                enviado: 'Enviado',
                sent: 'Enviado',
                entregue: 'Entregue',
                delivered: 'Entregue',
                lido: 'Lido',
                read: 'Lido',
                erro: 'Erro',
                failed: 'Erro'
            };

            const classes = {
                pendente: 'badge-secondary',
                processando: 'badge-info',
                aguardando_confirmacao: 'badge-info',
                enviado: 'badge-success',
                sent: 'badge-success',
                entregue: 'badge-primary',
                delivered: 'badge-primary',
                lido: 'badge-success',
                read: 'badge-success',
                erro: 'badge-danger',
                failed: 'badge-danger'
            };

            return '<span class="badge ' + (classes[status] || 'badge-secondary') + '">' +
                escapeHtmlDisparo(labels[status] || status || 'Na fila') +
                '</span>';
        }

        function atualizarResumoFila(lote, itens)
        {
            let concluidos = 0;
            let enviados = 0;
            let erros = 0;
            let pendentes = 0;
            let iniciouProcessamento = false;

            itens.forEach(function(item){
                if(['processando','aguardando_confirmacao','enviado','sent','entregue','delivered','lido','read','erro','failed'].includes(item.DMI_Status)){
                    iniciouProcessamento = true;
                }

                if(['aguardando_confirmacao','enviado','sent','entregue','delivered','lido','read','erro','failed'].includes(item.DMI_Status)){
                    concluidos++;
                }

                if(['aguardando_confirmacao','enviado','sent','entregue','delivered','lido','read'].includes(item.DMI_Status)){
                    enviados++;
                }

                if(['erro','failed'].includes(item.DMI_Status)){
                    erros++;
                }

                if(['pendente','processando'].includes(item.DMI_Status)){
                    pendentes++;
                }
            });

            let percentual = total > 0 ? Math.round((concluidos / total) * 100) : 0;

            $('#barraProgressoDisparo')
                .css('width', percentual + '%')
                .html(percentual + '%');

            if(!iniciouProcessamento){
                $('#textoProgressoDisparo').html(
                    'Aguardando o início do processamento. O servidor verifica novos envios automaticamente.'
                );
            }else{
                $('#textoProgressoDisparo').html(
                    'Processamento iniciado. Enviando... | Processados: ' + concluidos +
                    ' de ' + total + ' | Aceitos: ' + enviados +
                    ' | Erros: ' + erros + ' | Pendentes: ' + pendentes
                );
            }

            if(lote && lote.DML_Status == 'concluido'){
                $('#barraProgressoDisparo')
                    .css('width', '100%')
                    .html('100%');

                if(loteAguardaAtualizacaoWebhook(itens)){
                    $('#textoProgressoDisparo').html(
                        'Envio concluído. Aguardando confirmações da Meta para atualizar enviado, entregue ou lido.'
                    );
                    return;
                }

                $('#barraProgressoDisparo').removeClass('progress-bar-animated');
                $('#textoProgressoDisparo').html('Processamento concluído.');

                $('#resumoFinalDisparo').html(`
                    <div class="alert ${erros > 0 || pendentes > 0 ? 'alert-warning' : 'alert-success'} mt-3">
                        <strong>Processamento concluído.</strong><br>
                        Total: ${total} | Aceitos: ${enviados} | Erros: ${erros} | Pendentes: ${pendentes} | Status final: ${escapeHtmlDisparo(lote.DML_Status || 'concluido')}
                        <br>
                        <button
                        type="button"
                        class="btn btn-sm btn-outline-secondary mt-2"
                        id="btnVoltarEdicaoDisparo"
                        >
                            Novo envio
                        </button>
                    </div>
                `);

                finalizarMonitoramentoLote();
            }
        }

        function descricaoStatusFila(item)
        {
            if(item.DMI_Erro){
                return escapeHtmlDisparo(item.DMI_Erro);
            }

            const descricoes = {
                pendente: 'Aguardando processamento pelo servidor.',
                processando: 'Enviando...',
                aguardando_confirmacao: 'Enviado para a Meta. Aguardando confirmação.',
                enviado: 'Mensagem enviada pela Meta.',
                sent: 'Mensagem enviada pela Meta.',
                entregue: 'Mensagem entregue ao destinatário.',
                delivered: 'Mensagem entregue ao destinatário.',
                lido: 'Mensagem lida pelo destinatário.',
                read: 'Mensagem lida pelo destinatário.',
                erro: 'Erro ao processar envio.',
                failed: 'A Meta informou falha no envio.'
            };

            return '<span class="text-muted">' +
                escapeHtmlDisparo(descricoes[item.DMI_Status] || item.DMI_MessageId || 'Aguardando processamento pelo servidor.') +
                '</span>';
        }

        function atualizarItensFila(itens)
        {
            itens.forEach(function(item, index){
                let chaveItem = String(item.DMI_ID || index);
                let assinaturaItem = [
                    item.DMI_Status || '',
                    item.DMI_MessageId || '',
                    item.DMI_Erro || '',
                    item.DMI_Retorno || ''
                ].join('|');

                if(estadoItensLote[chaveItem] === assinaturaItem){
                    return;
                }

                estadoItensLote[chaveItem] = assinaturaItem;

                $('#linha_numero_' + index + ' td:eq(1)').html(
                    badgeStatusFila(item.DMI_Status)
                );

                $('#linha_numero_' + index + ' td:eq(2)').html(
                    descricaoStatusFila(item)
                );

                aplicarDetalhesLinha(
                    index,
                    {
                        numero: item.DMI_Numero,
                        status: item.DMI_Status,
                        mensagem_amigavel: item.DMI_Erro || item.DMI_MessageId || 'Aguardando processamento pelo servidor.',
                        message_id: item.DMI_MessageId || null,
                        retorno_meta_api: item.DMI_Retorno || null
                    }
                );
            });
        }

        function finalizarMonitoramentoLote()
        {
            processamentoImediatoAtivo = false;
            processandoBlocoLote = false;
            consultandoLote = false;

            if(timeoutPollingLote){
                clearTimeout(timeoutPollingLote);
                timeoutPollingLote = null;
            }

            if(timeoutProximoBloco){
                clearTimeout(timeoutProximoBloco);
                timeoutProximoBloco = null;
            }
        }

        window.finalizarMonitoramentoLoteDisparo = finalizarMonitoramentoLote;

        function consultarLote()
        {
            if(!loteId || consultandoLote){
                return;
            }

            if(timeoutPollingLote){
                clearTimeout(timeoutPollingLote);
                timeoutPollingLote = null;
            }

            if(document.hidden){
                agendarConsultaLote(5000);
                return;
            }

            consultandoLote = true;
            let proximaConsultaDelay = null;

            $.ajax({
                url: BASE_URL + '/index.php?url=disparo/statusLoteAjax',
                method: 'POST',
                data: {
                    csrf_token: (typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : ''),
                    lote_id: loteId
                },
                dataType: 'json',
                success: function(retorno){
                    if(!retorno.sucesso){
                        proximaConsultaDelay = calcularDelayPollingLote({}, []);
                        return;
                    }

                    consultasStatusLote++;
                    atualizarItensFila(retorno.itens || []);
                    atualizarResumoFila(retorno.lote || {}, retorno.itens || []);
                    proximaConsultaDelay = calcularDelayPollingLote(retorno.lote || {}, retorno.itens || []);
                },
                error: function(){
                    proximaConsultaDelay = 5000;
                },
                complete: function(){
                    consultandoLote = false;

                    if(proximaConsultaDelay !== null){
                        agendarConsultaLote(proximaConsultaDelay);
                    }
                }
            });
        }

        function lotePossuiPendentes(lote, itens)
        {
            if(lote && lote.DML_Status == 'concluido'){
                return false;
            }

            return (itens || []).some(function(item){
                return ['pendente','processando'].includes(item.DMI_Status);
            });
        }


        function loteAguardaAtualizacaoWebhook(itens)
        {
            return (itens || []).some(function(item){
                return ['aguardando_confirmacao','enviado','sent','entregue','delivered'].includes(item.DMI_Status);
            });
        }

        function lotePossuiStatusEmAberto(itens)
        {
            return (itens || []).some(function(item){
                return ['pendente','processando','aguardando_confirmacao','enviado','sent','entregue','delivered'].includes(item.DMI_Status);
            });
        }

        function calcularDelayPollingLote(lote, itens)
        {
            if(!loteId || cancelarDisparo){
                return null;
            }

            if(lote && lote.DML_Status == 'concluido' && !lotePossuiStatusEmAberto(itens)){
                return null;
            }

            if(lotePossuiStatusEmAberto(itens)){
                return consultasStatusLote < 30 ? 1000 : 3000;
            }

            return consultasStatusLote < 30 ? 1000 : 5000;
        }

        function agendarConsultaLote(delay)
        {
            if(!loteId || cancelarDisparo){
                return;
            }

            if(timeoutPollingLote){
                clearTimeout(timeoutPollingLote);
            }

            timeoutPollingLote = setTimeout(consultarLote, delay);
        }

        function finalizarProcessamentoImediatoLote()
        {
            processamentoImediatoAtivo = false;
            processandoBlocoLote = false;

            if(timeoutProximoBloco){
                clearTimeout(timeoutProximoBloco);
                timeoutProximoBloco = null;
            }
        }

        function agendarProximoBloco(delay)
        {
            if(!processamentoImediatoAtivo || cancelarDisparo){
                return;
            }

            if(timeoutProximoBloco){
                clearTimeout(timeoutProximoBloco);
            }

            timeoutProximoBloco = setTimeout(processarProximoBloco, delay);
        }

        function processarProximoBloco()
        {
            if(!loteId || cancelarDisparo){
                return;
            }

            if(processandoBlocoLote || consultandoLote){
                agendarProximoBloco(500);
                return;
            }

            if(document.hidden){
                agendarProximoBloco(5000);
                return;
            }

            if(timeoutProximoBloco){
                clearTimeout(timeoutProximoBloco);
                timeoutProximoBloco = null;
            }

            processandoBlocoLote = true;

            $.ajax({
                url: BASE_URL + '/index.php?url=disparo/processarLoteAjax',
                method: 'POST',
                data: {
                    csrf_token: (typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : ''),
                    lote_id: loteId
                },
                dataType: 'json',
                timeout: 45000,
                success: function(retorno){
                    processandoBlocoLote = false;

                    if(!retorno.sucesso){
                        $('#textoProgressoDisparo').html(
                            'Não foi possível processar este bloco agora. O worker continuará como fallback.'
                        );
                        agendarProximoBloco(15000);
                        return;
                    }

                    $('#textoProgressoDisparo').html('Processamento iniciado.');

                    atualizarItensFila(retorno.itens || []);
                    atualizarResumoFila(retorno.lote || {}, retorno.itens || []);

                    if(lotePossuiPendentes(retorno.lote || {}, retorno.itens || [])){
                        agendarProximoBloco(8000);
                    }else{
                        finalizarProcessamentoImediatoLote();
                        consultarLote();
                    }
                },
                error: function(){
                    processandoBlocoLote = false;
                    $('#textoProgressoDisparo').html(
                        'Falha temporária ao processar bloco. Nova tentativa em alguns segundos; o worker permanece como fallback.'
                    );
                    agendarProximoBloco(15000);
                }
            });
        }

        function aplicarDetalhesLinha(index, detalhesEnvio)
        {
            let detalhes = encodeDetalhesDisparo(detalhesEnvio);

            if(detalhes === ''){
                $('#linha_numero_' + index + ' td:eq(3)').html('-');
                return;
            }

            $('#linha_numero_' + index + ' td:eq(3)').html(`
                <button
                type="button"
                class="btn btn-xs btn-outline-info btnDetalhesDisparo"
                data-detalhes="${detalhes}"
                >
                    Ver detalhes
                </button>
            `);
        }

        $('#textoProgressoDisparo').html('Salvando destinos na fila...');

        let formDataLote = new FormData();
        formDataLote.append('csrf_token', (typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : ''));
        formDataLote.append('meta', $('#meta').val());
        formDataLote.append('template', $('#template').val());
        formDataLote.append('lista_id', listaSelecionadaId);
        formDataLote.append('destinos_json', JSON.stringify(parse.destinos.map(montarDestinoFila)));


        $.ajax({
            url: BASE_URL + '/index.php?url=disparo/criarLoteAjax',
            method: 'POST',
            data: formDataLote,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(retorno){
                if(!retorno.sucesso){
                    $('#resumoFinalDisparo').html(
                        '<div class="alert alert-danger mt-3">' +
                        escapeHtmlDisparo(retorno.erro || 'Erro ao criar lote.') +
                        '</div>'
                    );
                    restaurarTelaEdicaoDisparoAposErro();
                    return;
                }

                loteId = retorno.lote_id;

                $('#textoProgressoDisparo').html(
                    'Lote criado com sucesso. Iniciando envio agora...'
                );

                $('#linha_numero_0 td:eq(1)').html('<span class="badge badge-info">Enviando para Meta</span>');
                $('#linha_numero_0 td:eq(2)').html('<span class="text-muted">Primeiro envio em preparação.</span>');

                $('#resumoFinalDisparo').html(`
                    <div class="alert alert-info mt-3">
                        <strong>Lote #${loteId} criado com sucesso.</strong><br>
                        O envio foi colocado na fila e será iniciado automaticamente em instantes.
                    </div>
                `);

                $('#listaStatusNumeros tr').each(function(index){
                    if(index === 0){
                        return;
                    }

                    $(this).find('td:eq(1)').html(
                        '<span class="badge badge-secondary">Na fila</span>'
                    );

                    $(this).find('td:eq(2)').html(
                        '<span class="text-muted">Aguardando processamento pelo servidor.</span>'
                    );
                });

                processamentoImediatoAtivo = true;
                processarProximoBloco();
                consultarLote();
            },
            error: function(xhr){
                $('#resumoFinalDisparo').html(
                    '<div class="alert alert-danger mt-3">Falha ao criar lote de disparo manual.</div>'
                );
                restaurarTelaEdicaoDisparoAposErro();
            }
        });

    });

    $(document).on('click', '#btnVoltarEdicaoDisparo', function(){

        cancelarDisparo = false;
        statusDisparoPorMessageId = {};

        if(typeof window.finalizarMonitoramentoLoteDisparo === 'function'){
            window.finalizarMonitoramentoLoteDisparo();
        }

        if(intervaloStatusDisparo){
            clearInterval(intervaloStatusDisparo);
            intervaloStatusDisparo = null;
        }

        resetarEstadoVisualDisparo();
        atualizarContadorNumerosDestinoDisparo();

    });

    function preencherModalDetalhesDisparo(detalhesJson)
    {
        let detalhes = {};

        try{
            detalhes = JSON.parse(detalhesJson);
        }catch(e){
            detalhes = {};
        }

        $('#detalheDisparoNumero').text(detalhes.numero || '-');
        $('#detalheDisparoStatus').text(detalhes.status || '-');
        $('#detalheDisparoMensagem').text(
            detalhes.mensagem_amigavel
            || detalhes.erro_tecnico
            || '-'
        );
        $('#detalheDisparoJson').val(detalhesJson);
    }

    $(document).on('click', '.btnDetalhesDisparo', function(){

        preencherModalDetalhesDisparo(
            decodeDetalhesDisparo(
                $(this).data('detalhes')
            )
        );

        $('#modalDetalhesDisparo').modal('show');

    });

    $(document).on('click', '#btnCopiarDetalhesDisparo', function(){

        let campo = document.getElementById('detalheDisparoJson');

        if(!campo){
            return;
        }

        campo.focus();
        campo.select();

        if(navigator.clipboard && navigator.clipboard.writeText){
            navigator.clipboard.writeText(campo.value);
        }else{
            document.execCommand('copy');
        }

        $(this).html('<i class="fas fa-check"></i> Copiado');

        setTimeout(function(){
            $('#btnCopiarDetalhesDisparo').html(
                '<i class="fas fa-copy"></i> Copiar detalhes'
            );
        }, 2000);

    });

    $(document).on('change', '#lista_id', function(){

        if($(this).val() == 'nova'){

            $('#areaNovaLista').show();
            $('#nova_lista').attr('required', true);

        }else{

            $('#areaNovaLista').hide();
            $('#nova_lista').attr('required', false);
            $('#nova_lista').val('');

        }

    });

    $(document).on('change', '.custom-file-input', function(){

        let fileName =
            $(this)
            .val()
            .split('\\')
            .pop();

        if(fileName == ''){
            fileName = 'Escolher arquivo';
        }

        $(this)
            .next('.custom-file-label')
            .html(fileName);

    });

    $('.datatable').each(function () {

        if (!$.fn.DataTable.isDataTable(this)) {

            $(this).DataTable({
                language: {

                    decimal: "",
                    emptyTable: "Nenhum registro encontrado",
                    info: "Mostrando de _START_ até _END_ de _TOTAL_ registros",
                    infoEmpty: "Mostrando 0 até 0 de 0 registros",
                    infoFiltered: "(filtrado de _MAX_ registros no total)",
                    thousands: ".",

                    lengthMenu: "Mostrar _MENU_ registros",

                    loadingRecords: "Carregando...",
                    processing: "Processando...",

                    search: "Pesquisar:",

                    zeroRecords: "Nenhum registro encontrado",

                    paginate: {
                        first: "Primeiro",
                        last: "Último",
                        next: "Próximo",
                        previous: "Anterior"
                    },

                    aria: {
                        sortAscending:
                            ": ativar para ordenar a coluna em ordem crescente",
                        sortDescending:
                            ": ativar para ordenar a coluna em ordem decrescente"
                    }

                },
                order: [[0, 'asc']]
            });

        }

    });

});





function aplicarMascaras()
{
    $('.telefone').mask(
        '(00) 00000-0000'
    );





    $('.valor').mask(
        '000.000.000,00',
        {
            reverse: true
        }
    );
}

function validarCpf(cpf)
{
    cpf = cpf.replace(/\D/g,'');

    if(cpf.length != 11){
        return false;
    }

    if(/^(\d)\1+$/.test(cpf)){
        return false;
    }

    let soma = 0;

    for(let i = 0; i < 9; i++){

        soma += parseInt(cpf.charAt(i)) * (10 - i);

    }

    let resto = (soma * 10) % 11;

    if(resto == 10 || resto == 11){
        resto = 0;
    }

    if(resto != parseInt(cpf.charAt(9))){
        return false;
    }

    soma = 0;

    for(let i = 0; i < 10; i++){

        soma += parseInt(cpf.charAt(i)) * (11 - i);

    }

    resto = (soma * 10) % 11;

    if(resto == 10 || resto == 11){
        resto = 0;
    }

    if(resto != parseInt(cpf.charAt(10))){
        return false;
    }

    return true;
}





function validarCnpj(cnpj)
{
    cnpj = cnpj.replace(/\D/g,'');

    if(cnpj.length != 14){
        return false;
    }

    if(/^(\d)\1+$/.test(cnpj)){
        return false;
    }

    let tamanho = cnpj.length - 2;

    let numeros = cnpj.substring(0, tamanho);

    let digitos = cnpj.substring(tamanho);

    let soma = 0;

    let pos = tamanho - 7;

    for(let i = tamanho; i >= 1; i--){

        soma += numeros.charAt(tamanho - i) * pos--;

        if(pos < 2){
            pos = 9;
        }

    }

    let resultado = soma % 11 < 2
        ? 0
        : 11 - soma % 11;

    if(resultado != digitos.charAt(0)){
        return false;
    }

    tamanho = tamanho + 1;

    numeros = cnpj.substring(0, tamanho);

    soma = 0;

    pos = tamanho - 7;

    for(let i = tamanho; i >= 1; i--){

        soma += numeros.charAt(tamanho - i) * pos--;

        if(pos < 2){
            pos = 9;
        }

    }

    resultado = soma % 11 < 2
        ? 0
        : 11 - soma % 11;

    if(resultado != digitos.charAt(1)){
        return false;
    }

    return true;
}
