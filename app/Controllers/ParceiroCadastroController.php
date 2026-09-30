<?php
namespace Controllers;

use Core\Controller;
use Core\Csrf;
use Core\Session;
use Services\ParceiroCadastroService;

class ParceiroCadastroController extends Controller
{
    public function index()
    {
        $this->view('parceiros/cadastro_publico',['titulo'=>'Seja um parceiro Disparador.net'],false);
    }

    public function salvar()
    {
        if(($_SERVER['REQUEST_METHOD'] ?? '')!=='POST'){ $this->redirect('parceiroCadastro'); }
        Csrf::exigirPost();

        $dados=$_POST;
        unset($dados['senha'],$dados['confirmar_senha'],$dados['csrf_token'],$dados['g-recaptcha-response']);
        Session::set('partner_cadastro_dados',$dados);

        if(($_POST['senha'] ?? '') !== ($_POST['confirmar_senha'] ?? '')){
            Session::flash('error','As senhas informadas não conferem.'); $this->redirect('parceiroCadastro');
        }
        if(empty($_POST['aceite_termos'])){
            Session::flash('error','Você precisa aceitar os Termos de Uso e a Política de Privacidade.'); $this->redirect('parceiroCadastro');
        }

        if(defined('RECAPTCHA_SECRET_KEY') && RECAPTCHA_SECRET_KEY!==''){
            $captcha=$_POST['g-recaptcha-response'] ?? '';
            if($captcha===''){ Session::flash('error','Confirme que você não é um robô.'); $this->redirect('parceiroCadastro'); }
            $ch=curl_init();
            curl_setopt_array($ch,[CURLOPT_URL=>'https://www.google.com/recaptcha/api/siteverify',CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query(['secret'=>RECAPTCHA_SECRET_KEY,'response'=>$captcha,'remoteip'=>$_SERVER['REMOTE_ADDR'] ?? '']),CURLOPT_RETURNTRANSFER=>true]);
            $ret=json_decode((string)curl_exec($ch),true); curl_close($ch);
            if(empty($ret['success'])){ Session::flash('error','Falha na validação do reCAPTCHA.'); $this->redirect('parceiroCadastro'); }
        }

        try{
            (new ParceiroCadastroService())->cadastrar($_POST);
            Session::remove('partner_cadastro_dados');
            Session::flash('success','Cadastro de parceiro recebido. Nossa equipe fará a validação antes da liberação da integração e das credenciais da API.');
            $this->redirect('login');
        }catch(\InvalidArgumentException|\DomainException $e){
            Session::flash('error',$e->getMessage()); $this->redirect('parceiroCadastro');
        }catch(\Throwable $e){
            error_log('Falha no cadastro Partner: '.$e->getMessage());
            Session::flash('error','Não foi possível concluir o cadastro. Tente novamente.'); $this->redirect('parceiroCadastro');
        }
    }
}
