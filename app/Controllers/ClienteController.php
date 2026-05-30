<?php

namespace Controllers;

use Core\Controller;
use Core\Auth;
use Core\Database;
use Models\Cliente;
use Core\Session;
use PDOException;

class ClienteController extends Controller
{
    private $clienteModel;





    public function __construct()
    {
        Auth::admin();

        $this->clienteModel =
            new Cliente();
    }





    public function index()
    {
        $clientes =
            $this->clienteModel->listar();

        $this->view(
            'clientes/index',
            [
                'titulo' => 'Clientes',
                'clientes' => $clientes
            ]
        );
    }





    public function salvar()
    {
        try{

            /*
            |--------------------------------------------------------------------------
            | EDIÇÃO
            |--------------------------------------------------------------------------
            */

            if(!empty($_POST['id'])){

                $this->clienteModel->atualizar(
                    $_POST['id'],
                    $_POST
                );

                if(!empty($_POST['senha'])){

                    $senhaHash = password_hash(
                        $_POST['senha'],
                        PASSWORD_DEFAULT
                    );





                    $db = Database::getInstance();





                    $sql = "
                        UPDATE usuarios
                        SET USU_Senha = :senha
                        WHERE CLI_ID = :cliente
                    ";





                    $stmt =
                        $db->prepare($sql);





                    $stmt->execute([

                        ':senha' => $senhaHash,

                        ':cliente' => $_POST['id']

                    ]);

                }

                Session::flash(
                    'success',
                    'Cliente atualizado com sucesso.'
                );

            }else{

                /*
                |--------------------------------------------------------------------------
                | NOVO CLIENTE
                |--------------------------------------------------------------------------
                */

                $clienteId =
                    $this->clienteModel
                    ->salvar($_POST);





                $senhaHash = password_hash(
                    $_POST['senha'],
                    PASSWORD_DEFAULT
                );





                $sql = "
                    INSERT INTO usuarios
                    (
                        CLI_ID,
                        USU_Nome,
                        USU_Email,
                        USU_Senha,
                        USU_Nivel
                    )
                    VALUES
                    (
                        :cliente,
                        :nome,
                        :email,
                        :senha,
                        'cliente'
                    )
                ";





                $db = Database::getInstance();

                $stmt =
                    $db->prepare($sql);





                $stmt->execute([

                    ':cliente' => $clienteId,

                    ':nome' => $_POST['nome'],

                    ':email' => $_POST['email'],

                    ':senha' => $senhaHash

                ]);





                Session::flash(
                    'success',
                    'Cliente cadastrado com sucesso.'
                );

            }

        }catch(PDOException $e){

            Session::flash(
                'error',
                'Já existe um usuário com este e-mail.'
            );

        }





        $this->redirect('cliente');
    }





    public function inativar()
    {
        if(empty($_GET['id'])){

            Session::flash(
                'error',
                'Cliente não informado.'
            );

            $this->redirect('cliente');

            return;
        }





        $id = (int) $_GET['id'];





        $this->clienteModel->inativar($id);





        Session::flash(
            'success',
            'Cliente inativado com sucesso.'
        );





        $this->redirect('cliente');
    }
}