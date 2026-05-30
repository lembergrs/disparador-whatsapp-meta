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

        // echo '<pre>';
        // print_r($usuario);
        // exit;

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

        } else {
            Session::flash(
                'error',
                'Usuário ou senha inválidos.'
            );

            $this->redirect('login');
        }
    }

    public function sair()
    {
        session_destroy();

        session_start();

        // Session::flash(
        //     'success',
        //     'Logout realizado com sucesso.'
        // );

        $this->redirect('login');
    }
}