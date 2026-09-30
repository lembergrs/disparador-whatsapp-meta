<?php
use Core\Session;
$flash=Session::getFlash();
$d=Session::get('partner_cadastro_dados') ?? [];
Session::remove('partner_cadastro_dados');
function partnerEsc($v){return htmlspecialchars((string)($v ?? ''),ENT_QUOTES,'UTF-8');}
?>
<!doctype html><html lang="pt-br"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Seja um parceiro | Disparador.net</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<link rel="stylesheet" href="<?= ASSET_URL ?>/css/style.css?v=12">
<?php if(defined('RECAPTCHA_SITE_KEY') && RECAPTCHA_SITE_KEY!==''){ ?><script src="https://www.google.com/recaptcha/api.js" async defer></script><?php } ?>
</head><body class="site-cadastro-page"><div class="container py-5">
<div class="text-center mb-4"><a href="<?= BASE_URL ?>/index.php?url=site"><img src="<?= ASSET_URL ?>/img/logo-disparador.png" alt="Disparador.net" style="max-height:85px;max-width:320px"></a></div>
<div class="row justify-content-center"><div class="col-xl-9"><div class="card site-card-feature"><div class="card-body p-4 p-md-5">
<span class="badge badge-primary mb-3">Partner API</span><h2 class="font-weight-bold">Cadastro de parceiro</h2>
<p class="text-muted">Cadastre sua empresa para integrar sua plataforma ao Disparador.net. O cadastro será analisado antes da liberação da integração e das credenciais da API.</p>
<?php if($flash){ ?><div class="alert alert-<?= $flash['type']==='error'?'danger':'success' ?>"><?= $flash['message'] ?></div><?php } ?>
<form method="post" action="<?= BASE_URL ?>/index.php?url=parceiroCadastro/salvar" id="formCadastroPartner" novalidate>
<?= \Core\Csrf::input() ?>
<h5 class="mt-4 mb-3">Empresa e responsável</h5><div class="row">
<div class="form-group col-md-6"><label>Nome da empresa / marca</label><input name="nome" class="form-control" required value="<?= partnerEsc($d['nome']??'') ?>"></div>
<div class="form-group col-md-6"><label>Razão social</label><input name="razao_social" class="form-control" required value="<?= partnerEsc($d['razao_social']??'') ?>"></div>
<div class="form-group col-md-6"><label>Nome fantasia</label><input name="nome_fantasia" class="form-control" value="<?= partnerEsc($d['nome_fantasia']??'') ?>"></div>
<div class="form-group col-md-6"><label>CNPJ</label><input name="cnpj" id="cnpjPartner" class="form-control" required maxlength="18" inputmode="numeric" value="<?= partnerEsc($d['cnpj']??'') ?>"><div class="invalid-feedback">Informe um CNPJ válido.</div></div>
<div class="form-group col-md-6"><label>Responsável</label><input name="responsavel" class="form-control" required value="<?= partnerEsc($d['responsavel']??'') ?>"></div>
<div class="form-group col-md-6"><label>WhatsApp / telefone</label><input name="telefone" id="telefonePartner" class="form-control" required inputmode="tel" value="<?= partnerEsc($d['telefone']??'') ?>"><div class="invalid-feedback">Informe um telefone com DDD válido.</div></div>
<div class="form-group col-md-6"><label>E-mail de acesso e financeiro</label><input type="email" name="email" id="emailPartner" class="form-control text-lowercase" autocomplete="email" required value="<?= partnerEsc($d['email']??'') ?>"><div class="invalid-feedback">Informe um e-mail válido.</div></div>
</div>
<h5 class="mt-4 mb-3">Endereço fiscal para cobrança e NFS-e</h5><div class="row">
<div class="form-group col-md-3"><label>CEP</label><input name="cep" class="form-control" required maxlength="9" value="<?= partnerEsc($d['cep']??'') ?>"></div>
<div class="form-group col-md-7"><label>Logradouro</label><input name="logradouro" class="form-control" required value="<?= partnerEsc($d['logradouro']??'') ?>"></div>
<div class="form-group col-md-2"><label>Número</label><input name="numero" class="form-control" required value="<?= partnerEsc($d['numero']??'') ?>"></div>
<div class="form-group col-md-4"><label>Complemento</label><input name="complemento" class="form-control" value="<?= partnerEsc($d['complemento']??'') ?>"></div>
<div class="form-group col-md-4"><label>Bairro</label><input name="bairro" class="form-control" required value="<?= partnerEsc($d['bairro']??'') ?>"></div>
<div class="form-group col-md-4"><label>Município</label><input name="municipio" class="form-control" required value="<?= partnerEsc($d['municipio']??'') ?>"></div>
<div class="form-group col-md-2"><label>UF</label><input name="uf" class="form-control text-uppercase" maxlength="2" required value="<?= partnerEsc($d['uf']??'') ?>"></div>
<div class="form-group col-md-3"><label>Código IBGE</label><input name="codigo_ibge" class="form-control" maxlength="7" pattern="\d{7}" required value="<?= partnerEsc($d['codigo_ibge']??'') ?>"><small class="text-muted">7 dígitos do município.</small></div>
</div>
<h5 class="mt-4 mb-3">Acesso</h5><div class="row">
<div class="form-group col-md-6"><label>Senha</label><input type="password" name="senha" id="senhaCadastroPartner" class="form-control" data-password-strength minlength="8" required autocomplete="new-password"><div class="invalid-feedback">A senha deve atender aos requisitos de segurança.</div></div>
<div class="form-group col-md-6"><label>Confirmar senha</label><input type="password" name="confirmar_senha" class="form-control" data-password-confirm="#senhaCadastroPartner" required autocomplete="new-password"><div class="invalid-feedback">As senhas informadas não conferem.</div></div>
</div>
<?php if(defined('RECAPTCHA_SITE_KEY') && RECAPTCHA_SITE_KEY!==''){ ?><div class="g-recaptcha mb-3" data-sitekey="<?= RECAPTCHA_SITE_KEY ?>"></div><?php } ?>
<div class="form-check mb-4"><input class="form-check-input" type="checkbox" name="aceite_termos" id="partnerTermos" required><label class="form-check-label" for="partnerTermos">Li e concordo com os <a target="_blank" href="<?= BASE_URL ?>/index.php?url=site/termosUso">Termos de Uso</a> e a <a target="_blank" href="<?= BASE_URL ?>/index.php?url=site/politicaPrivacidade">Política de Privacidade</a>.</label></div>
<button class="btn btn-primary btn-lg btn-block"><i class="fas fa-handshake mr-2"></i>Solicitar cadastro Partner</button>
<p class="text-muted small mt-3 mb-0">O envio deste formulário não ativa a API nem gera cobrança automaticamente. Após a análise, nossa equipe orientará a validação inicial e as próximas etapas comerciais.</p>
</form></div></div></div></div></div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.inputmask/5.0.8/jquery.inputmask.min.js"></script>
<script>
$(function(){
    const $cnpj=$('#cnpjPartner'), $telefone=$('#telefonePartner'), $email=$('#emailPartner');
    $cnpj.inputmask('99.999.999/9999-99');
    $telefone.inputmask('(99) 99999-9999');

    function numeros(v){ return String(v||'').replace(/\D/g,''); }
    function cnpjValido(v){
        v=numeros(v);
        if(v.length!==14 || /^(\d)\1{13}$/.test(v)){ return false; }
        function digito(base,pesos){
            let soma=0; pesos.forEach(function(p,i){ soma+=parseInt(base.charAt(i),10)*p; });
            let resto=soma%11; return resto<2?'0':String(11-resto);
        }
        const d1=digito(v.substring(0,12),[5,4,3,2,9,8,7,6,5,4,3,2]);
        const d2=digito(v.substring(0,12)+d1,[6,5,4,3,2,9,8,7,6,5,4,3,2]);
        return v.slice(-2)===d1+d2;
    }
    function telefoneValido(v){ v=numeros(v); return v.length===10 || v.length===11; }
    function emailValido(v){ return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(v||'').trim()); }
    function marcar($campo,valido){
        $campo.toggleClass('is-invalid',!valido).toggleClass('is-valid',valido && $campo.val().trim()!=='');
        return valido;
    }
    function validarCnpj(){ return marcar($cnpj,cnpjValido($cnpj.val())); }
    function validarTelefone(){ return marcar($telefone,telefoneValido($telefone.val())); }
    function validarEmail(){ return marcar($email,emailValido($email.val())); }

    $email.on('input',function(){
        const inicio=this.selectionStart, fim=this.selectionEnd;
        this.value=this.value.toLowerCase();
        try{ this.setSelectionRange(inicio,fim); }catch(e){}
    });
    $cnpj.on('blur',validarCnpj);
    $telefone.on('blur',validarTelefone);
    $email.on('blur',validarEmail);

    $('#formCadastroPartner').on('submit',function(e){
        $email.val($.trim($email.val()).toLowerCase());
        const valido=validarCnpj() && validarTelefone() && validarEmail();
        if(!valido || !this.checkValidity()){
            e.preventDefault(); e.stopPropagation();
            this.classList.add('was-validated');
        }
    });
});
</script>
<script src="<?= ASSET_URL ?>/js/password-strength.js"></script>
</body></html>