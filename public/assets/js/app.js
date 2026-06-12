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

        let senha = Math.random()
        .toString(36)
        .slice(-8);

        $('#senha').val(senha);

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

        $('#senha').val('');

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

            $('[name=token]').val(
                $(this).data('token')
            );

            $('[name=url_base]').val(
                $(this).data('url')
            );

            $('[name=numero]').val(
                $(this).data('numero')
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





        $('#formMeta').attr(
            'action',
            BASE_URL
            + '/index.php?url=metaConta/salvar'
        );





        $('#modalMeta .modal-title').html(
            'Nova Conta Meta'
        );

    });

    $(document).on(
        'click',
        '.btnVisualizarTemplate',
        function(){

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





            componentes.forEach(function(comp){

                if(comp.text){

                    let matches =
                        comp.text.match(
                            /{{(.*?)}}/g
                        );





                    if(matches){

                        matches.forEach(function(v){

                            v = v
                                .replace('{{','')
                                .replace('}}','');

                            if(!variaveis.includes(v)){

                                variaveis.push(v);

                            }

                        });

                    }

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

            console.log(e);
            return;

        }

        let variaveis = [];

        componentes.forEach(function(comp){

            if(comp.text){

                let matches = comp.text.match(/{{(.*?)}}/g);

                if(matches){

                    matches.forEach(function(v){

                        v = v.replace('{{','').replace('}}','');

                        if(!variaveis.includes(v)){
                            variaveis.push(v);
                        }

                    });

                }

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

            console.log(e);
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

    function escapeHtmlDisparo(texto)
    {
        return String(texto ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
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

        componentes.forEach(function(comp){
            if(!comp.text){
                return;
            }

            let matches = comp.text.match(/{{(.*?)}}/g);

            if(!matches){
                return;
            }

            matches.forEach(function(v){
                v = v.replace('{{','').replace('}}','').trim();

                if(v !== '' && !variaveis.includes(v)){
                    variaveis.push(v);
                }
            });
        });

        variaveis.sort(function(a, b){
            if(!isNaN(a) && !isNaN(b)){
                return parseInt(a) - parseInt(b);
            }

            return String(a).localeCompare(String(b));
        });

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

    $(document).on('blur', '#numerosDestino', function(){

        let linhas = $(this).val().split(/\r?\n/);
        let formatadas = [];

        linhas.forEach(function(linha){
            linha = linha.trim();

            if(linha === ''){
                return;
            }

            let partes = linha.split(',');
            let numero = partes.shift();
            let numeroLimpo = limparNumeroDisparo(numero);

            if(numeroLimpo === ''){
                return;
            }

            let linhaFormatada = formatarNumeroDisparo(numeroLimpo);

            if(partes.length > 0){
                linhaFormatada += ',' + partes.map(function(valor){
                    return valor.trim();
                }).join(',');
            }

            formatadas.push(linhaFormatada);
        });

        $(this).val(formatadas.join("\n"));

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

        window.TEMPLATES_DISPARO.forEach(function(template){

            if(String(template.MTA_ID) == String(metaId)){

                templateSelect.append(`
                    <option
                        value="${template.TMP_ID}"
                        data-componentes="${btoa(template.TMP_Componentes)}"
                    >
                        ${template.TMP_Nome}
                    </option>
                `);

            }

        });

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

    let cancelarDisparo = false;

    $(document).on('click', '#btnPararDisparo', function(){

        cancelarDisparo = true;

        $(this)
            .prop('disabled', true)
            .html('<i class="fas fa-spinner fa-spin"></i> Parando...');

    });

    $(document).on('submit', '#formDisparo', function(e){

        e.preventDefault();

        cancelarDisparo = false;

        let form = $(this);
        let parse = parseDestinosDisparo();

        if(parse.erros.length > 0){
            alert(parse.erros.join("\n"));
            return;
        }

        if(parse.destinos.length == 0){
            alert('Informe pelo menos um número válido.');
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
            'Confira a prévia dos destinos e confirme o envio de ' +
            parse.destinos.length + ' mensagem(ns). Deseja iniciar agora?'
        )){
            return;
        }

        $('#resumoFinalDisparo').html('');
        $('#listaStatusNumeros').html('');

        $('#areaProgressoDisparo').show();

        $('#barraProgressoDisparo')
            .addClass('progress-bar-animated')
            .css('width', '0%')
            .html('0%');

        $('#textoProgressoDisparo').html(
            'Preparando lista de envio...'
        );

        $('#painelEdicaoDisparo').hide();
        $('#painelExecucaoDisparo').show();

        $('#btnEnviarDisparo')
            .prop('disabled', true)
            .html('<i class="fas fa-spinner fa-spin"></i> Enviando...');

        $('#btnPararDisparo')
            .prop('disabled', false)
            .html('<i class="fas fa-stop"></i> Parar envio');

        parse.destinos.forEach(function(destino, index){

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
                            Pendente
                        </span>
                    </td>
                    <td class="small text-muted">-</td>
                    <td>-</td>
                </tr>
            `);

        });

        let total = parse.destinos.length;
        let enviados = 0;
        let erros = 0;
        let cancelados = 0;
        let atual = 0;

        function rolarStatus()
        {
            let box = $('#boxStatusNumeros');

            if(box.length){
                box.scrollTop(
                    box[0].scrollHeight
                );
            }
        }

        function atualizarProgresso()
        {
            let percentual =
                Math.round(
                    (atual / total) * 100
                );

            $('#barraProgressoDisparo')
                .css('width', percentual + '%')
                .html(percentual + '%');

            $('#textoProgressoDisparo')
                .html(
                    'Processando '
                    + atual
                    + ' de '
                    + total
                    + ' | Enviados: '
                    + enviados
                    + ' | Erros: '
                    + erros
                    + ' | Cancelados: '
                    + cancelados
                );
        }

        function finalizarEnvio(tipo)
        {
            $('#btnEnviarDisparo')
                .prop('disabled', false)
                .html('<i class="fas fa-paper-plane"></i> Enviar Template');

            $('#btnPararDisparo')
                .prop('disabled', false)
                .html('<i class="fas fa-stop"></i> Parar envio');

            $('#barraProgressoDisparo')
                .removeClass('progress-bar-animated');

            if(tipo == 'cancelado'){

                $('#textoProgressoDisparo').html(
                    'Envio cancelado pelo usuário.'
                );

                $('#resumoFinalDisparo').html(`
                    <div class="alert alert-warning mt-3">
                        <strong>Envio cancelado.</strong><br>
                        Enviados: ${enviados} | Erros: ${erros} | Cancelados: ${cancelados}
                        <br>
                        <button
                        type="button"
                        class="btn btn-sm btn-outline-secondary mt-2"
                        id="btnVoltarEdicaoDisparo"
                        >
                            Editar números
                        </button>
                    </div>
                `);

                return;
            }

            $('#barraProgressoDisparo')
                .css('width', '100%')
                .html('100%');

            $('#textoProgressoDisparo').html(
                'Envio concluído.'
            );

            $('#resumoFinalDisparo').html(`
                <div class="alert ${erros > 0 ? 'alert-warning' : 'alert-success'} mt-3">
                    <strong>Envio concluído.</strong><br>
                    Enviados: ${enviados} | Erros: ${erros}
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
        }

        function cancelarPendentes()
        {
            for(let i = atual; i < total; i++){

                $('#linha_numero_' + i + ' td:eq(1)').html(
                    '<span class="badge badge-warning">Cancelado</span>'
                );

                $('#linha_numero_' + i + ' td:eq(2)').html(
                    '<span class="text-muted">Envio cancelado antes de iniciar.</span>'
                );

                cancelados++;

            }

            atualizarProgresso();
            rolarStatus();
            finalizarEnvio('cancelado');
        }

        function montarDadosEnvio(destino)
        {
            let dados = [
                {
                    name: 'meta',
                    value: $('#meta').val()
                },
                {
                    name: 'template',
                    value: $('#template').val()
                },
                {
                    name: 'numero',
                    value: destino.numero
                }
            ];

            parse.variaveis.forEach(function(v, index){
                dados.push({
                    name: 'variaveis[' + v + ']',
                    value: destino.valores[index] || ''
                });
            });

            return dados;
        }

        function mensagemCurtaErroDisparo(erro)
        {
            erro = String(erro || 'Erro ao enviar mensagem').trim();

            if(erro.length == 0){
                return 'Erro ao enviar mensagem';
            }

            if(erro.includes('Invalid parameter')){
                return 'Erro nos parâmetros do template.';
            }

            if(erro.includes('Parameter name')){
                return 'Erro nos nomes das variáveis do template.';
            }

            if(erro.includes('Unsupported post request')){
                return 'Erro ao conectar com a Meta.';
            }

            if(erro.length > 120){
                return erro.substring(0, 117) + '...';
            }

            return erro;
        }

        function montarDetalhesEnvioDisparo(destino, retorno, status, motivo)
        {
            return {
                numero: (retorno && retorno.numero_formatado)
                    ? retorno.numero_formatado
                    : destino.numero_formatado,
                status: status,
                mensagem_amigavel: motivo,
                message_id: retorno ? (retorno.message_id || null) : null,
                erro_tecnico: retorno ? (retorno.erro || null) : null,
                retorno_meta_api: retorno ? (retorno.retorno || retorno) : null,
                payload_enviado: retorno && retorno.retorno
                    ? (retorno.retorno.payload || null)
                    : null
            };
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

        function enviarProximo()
        {
            if(cancelarDisparo){
                cancelarPendentes();
                return;
            }

            if(atual >= total){
                finalizarEnvio('concluido');
                return;
            }

            let destino = parse.destinos[atual];

            $('#linha_numero_' + atual + ' td:eq(1)').html(
                '<span class="badge badge-info">Enviando...</span>'
            );

            $('#linha_numero_' + atual + ' td:eq(2)').html(
                '<span class="text-muted">Aguardando resposta da Meta...</span>'
            );

            rolarStatus();

            $.ajax({
                url: BASE_URL + '/index.php?url=disparo/enviarAjax',
                method: 'POST',
                data: montarDadosEnvio(destino),
                dataType: 'json',

                success: function(retorno){

                    if(retorno.sucesso){

                        enviados++;

                        $('#linha_numero_' + atual + ' td:eq(1)').html(
                            '<span class="badge badge-success">Enviado</span>'
                        );

                        $('#linha_numero_' + atual + ' td:eq(2)').html(
                            '<span class="text-success">Mensagem enviada com sucesso</span>'
                        );

                    }else{

                        erros++;

                        $('#linha_numero_' + atual + ' td:eq(1)').html(
                            '<span class="badge badge-danger">Erro</span>'
                        );

                        $('#linha_numero_' + atual + ' td:eq(2)').html(
                            escapeHtmlDisparo(
                                mensagemCurtaErroDisparo(retorno.erro)
                            )
                        );

                    }

                    let motivoDetalhe = retorno.sucesso
                        ? 'Mensagem enviada com sucesso'
                        : mensagemCurtaErroDisparo(retorno.erro);

                    aplicarDetalhesLinha(
                        atual,
                        montarDetalhesEnvioDisparo(
                            destino,
                            retorno,
                            retorno.sucesso ? 'Enviado' : 'Erro',
                            motivoDetalhe
                        )
                    );

                    if(retorno.numero_formatado){
                        $('#linha_numero_' + atual + ' td:eq(0)').contents().first()[0].textContent =
                            retorno.numero_formatado;
                    }

                },

                error: function(xhr){

                    erros++;

                    $('#linha_numero_' + atual + ' td:eq(1)').html(
                        '<span class="badge badge-danger">Erro</span>'
                    );

                    $('#linha_numero_' + atual + ' td:eq(2)').html(
                        'Falha na requisição de envio.'
                    );

                    aplicarDetalhesLinha(
                        atual,
                        montarDetalhesEnvioDisparo(
                            destino,
                            {
                                erro: 'Falha na requisição de envio.',
                                retorno: {
                                    status: xhr.status,
                                    responseText: xhr.responseText
                                }
                            },
                            'Erro',
                            'Falha na requisição de envio.'
                        )
                    );

                },

                complete: function(){

                    atual++;

                    atualizarProgresso();
                    rolarStatus();

                    setTimeout(
                        enviarProximo,
                        500
                    );

                }
            });
        }

        setTimeout(function(){

            atualizarProgresso();
            enviarProximo();

        }, 500);

    });

    $(document).on('click', '#btnVoltarEdicaoDisparo', function(){

        $('#painelExecucaoDisparo').hide();
        $('#painelEdicaoDisparo').show();

        $('#areaProgressoDisparo').hide();
        $('#resumoFinalDisparo').html('');
        $('#listaStatusNumeros').html('');

        cancelarDisparo = false;

    });

    $(document).on('click', '.btnDetalhesDisparo', function(){

        alert(
            decodeDetalhesDisparo(
                $(this).data('detalhes')
            )
        );

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
