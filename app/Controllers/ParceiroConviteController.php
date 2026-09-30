<?php
namespace Controllers;

use Core\Controller;
use Core\Csrf;
use Core\Session;
use Models\ParceiroConvite;
use Services\ParceiroClienteCadastroService;

class ParceiroConviteController extends Controller
{
    public function index()
    {
        $token=(string)($_GET['token']??'');
        $convite=(new ParceiroConvite())->buscarPorToken($token);
        $this->view('parceiros/cadastro_cliente_convite',['titulo'=>'Cadastro por convite','token'=>$token,'convite'=>$convite],false);
    }

    public function salvar()
    {
        if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){ $this->redirect('login'); }
        Csrf::exigirPost();
        $token=(string)($_POST['convite_token']??'');
        if(($_POST['senha']??'')!==($_POST['confirmar_senha']??'')){ Session::flash('error','As senhas informadas não conferem.'); header('Location: '.BASE_URL.'/index.php?url=parceiroConvite&token='.rawurlencode($token)); exit; }
        if(empty($_POST['aceite_termos'])){ Session::flash('error','Você precisa aceitar os Termos de Uso e a Política de Privacidade.'); header('Location: '.BASE_URL.'/index.php?url=parceiroConvite&token='.rawurlencode($token)); exit; }
        try{
            (new ParceiroClienteCadastroService())->cadastrar($token,$_POST);
            Session::flash('success','Cadastro concluído. Entre com sua conta para conectar o WhatsApp e gerenciar seus templates.');
            $this->redirect('login');
        }catch(\InvalidArgumentException|\DomainException $e){
            Session::flash('error',$e->getMessage()); header('Location: '.BASE_URL.'/index.php?url=parceiroConvite&token='.rawurlencode($token)); exit;
        }catch(\Throwable $e){
            error_log('Falha no cadastro de cliente Partner: '.$e->getMessage());
            Session::flash('error','Não foi possível concluir o cadastro. Tente novamente.'); header('Location: '.BASE_URL.'/index.php?url=parceiroConvite&token='.rawurlencode($token)); exit;
        }
    }
}
