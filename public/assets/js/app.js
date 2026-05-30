$(document).ready(function(){

    if($('.flashMessage').length){

        setTimeout(function(){

            $('.flashMessage').fadeOut(
                500
            );

        }, 2000);

    }

    if($('#tabelaClientes').length){

        $('#tabelaClientes').DataTable({

            language: {

                url:
                '//cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json'

            }

        });

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
