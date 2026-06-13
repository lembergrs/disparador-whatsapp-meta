<?php

namespace Controllers;

use Core\Controller;
use Core\Auth;
use Core\Session;
use Models\MetaConta;
use Models\Cliente;
use Services\MetaService;

class MetaContaController extends Controller
{
    private $metaModel;

    private $clienteModel;





    public function __construct()
    {
        Auth::admin();

        $this->metaModel =
            new MetaConta();

        $this->clienteModel =
            new Cliente();
    }





    public function index()
    {
        $contas =
            $this->metaModel->listar();

        $clientes =
            $this->clienteModel->listar();





        $this->view(
            'meta_contas/index',
            [

                'titulo' => 'Contas Meta',

                'contas' => $contas,

                'clientes' => $clientes

            ]
        );
    }





    public function salvar()
    {
        $clienteId =
            (int) ($_POST['cliente'] ?? 0);

        $limite =
            $this->metaModel
            ->avaliarLimiteNumerosPorCliente(
                $clienteId
            );

        if(!$limite['permitido']){

            Session::flash(
                'error',
                $limite['mensagem']
            );

            $this->redirect(
                'metaConta'
            );
        }

        $this->metaModel->salvar(
            $_POST
        );





        Session::flash(
            'success',
            'Conta Meta cadastrada com sucesso.'
        );





        $this->redirect(
            'metaConta'
        );
    }





    public function inativar()
    {
        $id = $_GET['id'];

        $this->metaModel->inativar(
            $id
        );





        Session::flash(
            'success',
            'Conta Meta inativada.'
        );





        $this->redirect(
            'metaConta'
        );
    }

    public function testar()
    {
        $id = $_GET['id'];

        try{

            $meta =
                new MetaService($id);

            $resultado =
                $meta->testarConexao();





            if($resultado['sucesso']){

                Session::flash(
                    'success',
                    'Conexão realizada com sucesso.'
                );

            }else{

                Session::flash(
                    'error',
                    'Erro ao conectar com a Meta.'
                );

            }

        }catch(\Exception $e){

            Session::flash(
                'error',
                $e->getMessage()
            );

        }





        $this->redirect(
            'metaConta'
        );
    }

    public function atualizar()
    {
        $id =
            (int) ($_POST['id'] ?? 0);

        $contaAtual =
            $this->metaModel->buscar(
                $id
            );

        if(!$contaAtual){

            Session::flash(
                'error',
                'Conta Meta inválida.'
            );

            $this->redirect(
                'metaConta'
            );
        }

        $clienteId =
            (int) ($_POST['cliente'] ?? 0);

        $clienteAlterado =
            (int) $contaAtual['CLI_ID']
            !==
            $clienteId;

        if($clienteAlterado){

            $limite =
                $this->metaModel
                ->avaliarLimiteNumerosPorCliente(
                    $clienteId,
                    $id
                );

            if(!$limite['permitido']){

                Session::flash(
                    'error',
                    $limite['mensagem']
                );

                $this->redirect(
                    'metaConta'
                );
            }
        }

        $this->metaModel->atualizar(
            $id,
            $_POST
        );





        Session::flash(
            'success',
            'Conta Meta atualizada com sucesso.'
        );





        $this->redirect(
            'metaConta'
        );
    }

}
