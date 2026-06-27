(function(){
    const regras = [
        { chave: 'minimo', texto: 'mínimo 8 caracteres', teste: senha => senha.length >= 8 },
        { chave: 'maiuscula', texto: 'letra maiúscula', teste: senha => /[A-Z]/.test(senha) },
        { chave: 'minuscula', texto: 'letra minúscula', teste: senha => /[a-z]/.test(senha) },
        { chave: 'numero', texto: 'número', teste: senha => /\d/.test(senha) },
        { chave: 'especial', texto: 'caractere especial', teste: senha => /[^A-Za-z0-9]/.test(senha) }
    ];

    function avaliar(senha){
        const resultado = {};
        let total = 0;

        regras.forEach(function(regra){
            resultado[regra.chave] = regra.teste(senha);
            if(resultado[regra.chave]){
                total++;
            }
        });

        resultado.total = total;
        resultado.forte = total === regras.length;

        return resultado;
    }

    function rotuloForca(total){
        if(total <= 2){
            return { texto: 'Fraca', classe: 'danger' };
        }

        if(total <= 4){
            return { texto: 'Média', classe: 'warning' };
        }

        return { texto: 'Forte', classe: 'success' };
    }

    function criarIndicador(campo){
        const container = document.createElement('div');
        container.className = 'password-strength mt-2';
        container.innerHTML = '<div class="small mb-1">Força: <span class="password-strength-label badge badge-danger">Fraca</span></div>'
            + '<ul class="list-unstyled small mb-0 password-strength-checklist"></ul>';

        const checklist = container.querySelector('.password-strength-checklist');

        regras.forEach(function(regra){
            const item = document.createElement('li');
            item.dataset.regra = regra.chave;
            item.className = 'text-muted';
            item.innerHTML = '<span class="password-strength-icon">○</span> ' + regra.texto;
            checklist.appendChild(item);
        });

        campo.insertAdjacentElement('afterend', container);

        return container;
    }

    function atualizar(campo, indicador){
        const avaliacao = avaliar(campo.value || '');
        const forca = rotuloForca(avaliacao.total);
        const label = indicador.querySelector('.password-strength-label');

        label.className = 'password-strength-label badge badge-' + forca.classe;
        label.textContent = forca.texto;

        regras.forEach(function(regra){
            const item = indicador.querySelector('[data-regra="' + regra.chave + '"]');
            const valido = avaliacao[regra.chave];
            item.className = valido ? 'text-success' : 'text-muted';
            item.querySelector('.password-strength-icon').textContent = valido ? '✔' : '○';
        });

        if(campo.value === ''){
            campo.setCustomValidity('');
            campo.classList.remove('is-valid', 'is-invalid');
            return false;
        }

        campo.setCustomValidity(avaliacao.forte ? '' : 'A senha não atende aos requisitos de segurança.');
        campo.classList.toggle('is-valid', avaliacao.forte);
        campo.classList.toggle('is-invalid', !avaliacao.forte);

        return avaliacao.forte;
    }

    function configurarConfirmacao(campoSenha, campoConfirmacao){
        function validarConfirmacao(){
            if(!campoConfirmacao.value){
                campoConfirmacao.setCustomValidity('');
                campoConfirmacao.classList.remove('is-valid', 'is-invalid');
                return;
            }

            const igual = campoConfirmacao.value === campoSenha.value;
            campoConfirmacao.setCustomValidity(igual ? '' : 'As senhas informadas não conferem.');
            campoConfirmacao.classList.toggle('is-valid', igual);
            campoConfirmacao.classList.toggle('is-invalid', !igual);
        }

        campoSenha.addEventListener('input', validarConfirmacao);
        campoConfirmacao.addEventListener('input', validarConfirmacao);
        campoConfirmacao.addEventListener('blur', validarConfirmacao);
    }

    document.addEventListener('DOMContentLoaded', function(){
        document.querySelectorAll('[data-password-strength]').forEach(function(campo){
            const indicador = criarIndicador(campo);

            campo.addEventListener('input', function(){
                atualizar(campo, indicador);
            });

            campo.addEventListener('blur', function(){
                atualizar(campo, indicador);
            });

            const form = campo.closest('form');

            if(form){
                form.addEventListener('submit', function(event){
                    if(campo.value !== '' && !atualizar(campo, indicador)){
                        event.preventDefault();
                        event.stopPropagation();
                    }
                });
            }

            atualizar(campo, indicador);
        });

        document.querySelectorAll('[data-password-confirm]').forEach(function(confirmacao){
            const seletor = confirmacao.dataset.passwordConfirm;
            const senha = seletor ? document.querySelector(seletor) : null;

            if(senha){
                configurarConfirmacao(senha, confirmacao);
            }
        });
    });
})();
