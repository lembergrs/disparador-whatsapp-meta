<?php

namespace Controllers;

use Core\Controller;
use Core\Database;
use Core\Session;
use Core\Csrf;
use Core\Auth;
use PDO;
use Services\RecuperacaoSenhaService;
use Services\AnalyticsService;

class LoginController extends Controller
{
    public function index()
    {
        $this->view(
            'auth/login',
            [],
            false
        );
    }

    public function autenticar()
    {

        if(defined('RECAPTCHA_SECRET_KEY') && RECAPTCHA_SECRET_KEY != ''){

            $captcha = $_POST['g-recaptcha-response'] ?? '';

            if($captcha == ''){
                Session::flash('error', 'Confirme que você não é um robô.');
                $this->redirect('login');
            }

            $url = 'https://www.google.com/recaptcha/api/siteverify';

            $dados = [
                'secret' => RECAPTCHA_SECRET_KEY,
                'response' => $captcha,
                'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
            ];

            $ch = curl_init();

            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($dados),
                CURLOPT_RETURNTRANSFER => true
            ]);

            $retorno = curl_exec($ch);
            curl_close($ch);

            $retorno = json_decode($retorno, true);

            if(empty($retorno['success'])){
                Session::flash('error', 'Falha na validação do reCAPTCHA.');
                $this->redirect('login');
            }
        }

        $db = Database::getInstance();

        $sql = $db->prepare("
            SELECT
                u.*,
                c.CLI_StatusPagamento,
                c.CLI_StatusCadastro,
                c.CLI_DataLiberacao,
                c.CLI_DataCadastro,
                c.CLI_Plano_DR
            FROM usuarios u
            LEFT JOIN clientes c
                ON c.CLI_ID = u.CLI_ID
            WHERE u.USU_Email = ?
            AND u.USU_Ativo = 'S'
        ");

        $sql->execute([
            $_POST['email']
        ]);

        $usuario = $sql->fetch(PDO::FETCH_ASSOC);

        if(
            $usuario &&
            password_verify(
                $_POST['senha'],
                $usuario['USU_Senha']
            )
        ){

            if(Auth::isImpersonating()){
                Auth::logout('outro');
                session_start();
            }

            session_regenerate_id(true);

            unset($_SESSION['impersonacao']);

            $_SESSION['usuario'] = [

                'id' => $usuario['USU_ID'],
                'nome' => $usuario['USU_Nome'],
                'cliente_id' => $usuario['CLI_ID'],
                'nivel' => $usuario['USU_Nivel'],
                'CLI_ID' => $usuario['CLI_ID'],
                'CLI_StatusPagamento' =>
                    $usuario['CLI_StatusPagamento'] ?? null,
                'CLI_StatusCadastro' =>
                    $usuario['CLI_StatusCadastro'] ?? null,
                'CLI_DataLiberacao' =>
                    $usuario['CLI_DataLiberacao'] ?? null,
                'CLI_DataCadastro' =>
                    $usuario['CLI_DataCadastro'] ?? null,
                'CLI_Plano_DR' =>
                    $usuario['CLI_Plano_DR'] ?? null,
                'CMS_MensagensMesAtual' => 0

            ];

            AnalyticsService::registrar('login', ['method'=>'password']);

            $this->redirect('dashboard');

        }else{

            Session::flash(
                'error',
                'Usuário ou senha inválidos.'
            );

            $this->redirect('login');
        }
    }


    public function esqueciSenha()
    {
        $this->recuperarSenha();
    }

    public function recuperarSenha()
    {
        $this->view('auth/recuperar_senha', [], false);
    }

    public function enviarRecuperacao()
    {
        Csrf::exigirPost();

        $service = new RecuperacaoSenhaService();
        $service->solicitar(
            $_POST['email'] ?? '',
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        );

        Session::flash('success', RecuperacaoSenhaService::mensagemPublicaSolicitacao());
        $this->redirect('login/recuperarSenha');
    }

    public function redefinirSenha()
    {
        $token = $_GET['token'] ?? '';
        $validacao = (new RecuperacaoSenhaService())->validarToken($token);

        if(empty($validacao['valido'])){
            $this->view('auth/recuperacao_link_indisponivel', [], false);
            return;
        }

        $this->view('auth/redefinir_senha', ['token' => $token], false);
    }

    public function salvarNovaSenha()
    {
        Csrf::exigirPost();
        Auth::bloquearAcaoSensivelEmImpersonacao();

        $service = new RecuperacaoSenhaService();
        $resultado = $service->redefinir(
            $_POST['token_recuperacao'] ?? '',
            $_POST['nova_senha'] ?? '',
            $_POST['confirmar_senha'] ?? ''
        );

        if(!empty($resultado['sucesso'])){
            $this->view('auth/recuperacao_sucesso', [], false);
            return;
        }

        if(($resultado['tipo'] ?? '') === 'validacao'){
            Session::flash('error', $resultado['mensagem'] ?? 'Revise a nova senha informada.');
            $this->view('auth/redefinir_senha', ['token' => $_POST['token_recuperacao'] ?? ''], false);
            return;
        }

        $this->view('auth/recuperacao_link_indisponivel', [], false);
    }

    public function sair()
    {
        if(($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST'){
            http_response_code(405);
            $this->redirect('login');
        }

        Csrf::exigirPost();

        Auth::logout();

        session_start();

        $this->redirect('login');
    }
}
