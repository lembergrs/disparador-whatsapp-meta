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
                    <div class="card mb-2">

                        <div class="card-header">

                            <strong>
                                ${comp.type}
                            </strong>

                        </div>

                        <div class="card-body">
                `;

                if(comp.format){

                    html += `
                        <p>
                            <strong>Formato:</strong>
                            ${comp.format}
                        </p>
                    `;

                }


                if(comp.text){

                    html += `
                        <div class="alert alert-light">

                            ${comp.text}

                        </div>
                    `;

                }


                if(comp.example){

                    html += `
                        <small class="text-muted">

                            Exemplo disponível

                        </small>
                    `;

                }






                html += `
                        </div>
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

        $('#areaVariaveis').html('');
        $('#conteudoPreviewTemplateDisparo').html('');
        $('#previewTemplateDisparo').hide();

        if(!componentesBase64){
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

        let variaveis = [];

        componentes.forEach(function(comp){

            if(comp.text){

                let matches =
                    comp.text.match(/{{(.*?)}}/g);

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

        if(variaveis.length == 0){
            return;
        }

        let html = '';

        variaveis.forEach(function(v){

            html += `
                <div class="form-group">

                    <label>
                        Variável {{${v}}}
                    </label>

                    <input
                    type="text"
                    name="variaveis[${v}]"
                    class="form-control"
                    required
                    >

                </div>
            `;

        });

        $('#areaVariaveis').html(html);

    });

    $(document).on('blur', '#numerosDestino', function(){

        let linhas =
            $(this)
            .val()
            .split(/[\n,;]+/);

        let formatados = [];

        linhas.forEach(function(numero){

            numero =
                numero.replace(/\D/g, '');

            if(numero.length == 0){
                return;
            }

            if(numero.startsWith('55') && numero.length > 11){
                numero = numero.substring(2);
            }

            if(numero.length == 11){

                numero =
                    '(' + numero.substring(0,2) + ') ' +
                    numero.substring(2,7) + '-' +
                    numero.substring(7);

            }else if(numero.length == 10){

                numero =
                    '(' + numero.substring(0,2) + ') ' +
                    numero.substring(2,6) + '-' +
                    numero.substring(6);

            }

            formatados.push(numero);

        });

        $(this).val(
            formatados.join("\n")
        );

    });

    $(document).on('change', '#meta', function(){

        let metaId = $(this).val();

        let templateSelect = $('#template');

        templateSelect.html('');

        $('#areaVariaveis').html('');

        if(metaId == ''){

            templateSelect.append(`
                <option value="">
                    Selecione primeiro a Conta Meta
                </option>
            `);

            templateSelect.prop('disabled', true);

            return;

        }

        templateSelect.append(`
            <option value="">
                Selecione
            </option>
        `);

        window.TEMPLATES_DISPARO.forEach(function(template){

            if(template.MTA_ID == metaId){

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

    $('#telefoneTeste').mask('(00) 00000-0000');

    $(document).on('submit', '#formDisparo', function(e){

        e.preventDefault();

        let form =
            $(this);

        let numerosTexto =
            $('#numerosDestino').val();

        let numeros =
            numerosTexto.split(/[\n,;]+/);

        let numerosLimpos = [];

        numeros.forEach(function(numero){

            numero =
                numero.replace(/\D/g, '');

            if(numero.length == 0){
                return;
            }

            if(numero.substring(0,2) != '55'){
                numero = '55' + numero;
            }

            if(!numerosLimpos.includes(numero)){
                numerosLimpos.push(numero);
            }

        });

        if(numerosLimpos.length == 0){

            alert('Informe pelo menos um número válido.');
            return;

        }

        $('#areaProgressoDisparo').show();

        $('#btnEnviarDisparo')
            .prop('disabled', true)
            .html('<i class="fas fa-spinner fa-spin"></i> Enviando...');

        let total = numerosLimpos.length;
        let enviados = 0;
        let erros = 0;
        let atual = 0;

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
                );
        }

        function enviarProximo()
        {
            if(atual >= total){

                $('#btnEnviarDisparo')
                    .prop('disabled', false)
                    .html('<i class="fas fa-paper-plane"></i> Enviar Template');

                $('#textoProgressoDisparo')
                    .html(
                        'Envio concluído. Enviados: '
                        + enviados
                        + ' | Erros: '
                        + erros
                    );

                return;
            }

            let numero =
                numerosLimpos[atual];

            let dados =
                form.serializeArray();
                
            dados.push({
                name: 'numero',
                value: numero
            });

            $.ajax({
                url: BASE_URL + '/index.php?url=disparo/enviarAjax',
                method: 'POST',
                data: dados,
                dataType:
                    'json',

                success: function(retorno){
                    if(retorno.sucesso){
                        enviados++;
                    }else{
                        erros++;
                    }
                },
                error: function(){
                    erros++;
                },
                complete: function(){
                    atual++;
                    atualizarProgresso();
                    setTimeout(
                        enviarProximo,
                        500
                    );
                }
            });
        }
        atualizarProgresso();
        enviarProximo();
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

    $('.datatable').DataTable({
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
