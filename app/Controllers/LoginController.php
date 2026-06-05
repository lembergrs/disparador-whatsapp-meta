<?php

namespace Controllers;

use Core\Controller;
use Core\Database;
use Core\Session;
use PDO;

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
        if(!$this->validarCaptcha()){

            Session::flash(
                'error',
                'Confirme que você não é um robô.'
            );

            $this->redirect('login');
            return;
        }

        $db = Database::getInstance();

        $sql = $db->prepare("
            SELECT *
            FROM usuarios
            WHERE USU_Email = ?
            AND USU_Ativo = 'S'
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

            $_SESSION['usuario'] = [

                'id' => $usuario['USU_ID'],
                'nome' => $usuario['USU_Nome'],
                'cliente_id' => $usuario['CLI_ID'],
                'nivel' => $usuario['USU_Nivel'],
                'CLI_ID' => $usuario['CLI_ID']

            ];

            $this->redirect('dashboard');

        }else{

            Session::flash(
                'error',
                'Usuário ou senha inválidos.'
            );

            $this->redirect('login');
        }
    }

    private function validarCaptcha()
    {
        /*
            Configure em config/config.php:

            define('RECAPTCHA_SITE_KEY', 'sua_site_key');
            define('RECAPTCHA_SECRET_KEY', 'sua_secret_key');

            Se não configurar, o captcha não será obrigatório.
        */

        if(
            !defined('RECAPTCHA_SECRET_KEY') ||
            RECAPTCHA_SECRET_KEY == ''
        ){
            return true;
        }

        $captcha =
            $_POST['g-recaptcha-response']
            ?? '';

        if(empty($captcha)){
            return false;
        }

        $curl = curl_init();

        curl_setopt_array($curl, [

            CURLOPT_URL =>
                'https://www.google.com/recaptcha/api/siteverify',

            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_POST => true,

            CURLOPT_POSTFIELDS => http_build_query([

                'secret' =>
                    RECAPTCHA_SECRET_KEY,

                'response' =>
                    $captcha,

                'remoteip' =>
                    $_SERVER['REMOTE_ADDR']
                    ?? null

            ])

        ]);

        $response =
            curl_exec($curl);

        curl_close($curl);

        $resultado =
            json_decode(
                $response,
                true
            );

        return !empty($resultado['success']);
    }

    public function esqueciSenha()
    {
        $this->view(
            'auth/esqueci_senha',
            [],
            false
        );
    }

    public function sair()
    {
        session_destroy();

        session_start();

        $this->redirect('login');
    }
}