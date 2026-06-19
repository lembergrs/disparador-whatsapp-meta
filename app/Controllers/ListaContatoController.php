<?php

namespace Controllers;

use Core\Controller;
use Core\Auth;
use Core\Session;

use Models\ListaContato;
use Models\ListaContatoItem;
use Models\Contato;

class ListaContatoController extends Controller
{
    private $listaModel;
    private $listaItemModel;
    private $contatoModel;

    public function __construct()
    {
        Auth::check();

        $this->listaModel =
            new ListaContato();

        $this->listaItemModel =
            new ListaContatoItem();
        
        $this->contatoModel = 
            new Contato();
    }

    public function index()
    {
        $usuario =
            Auth::usuario();

        $listas =
            $this->listaModel
            ->listarPorCliente(
                $usuario['cliente_id']
            );

        $this->view(
            'listas/index',
            [
                'titulo' => 'Listas de Contatos',
                'listas' => $listas
            ]
        );
    }

    public function visualizar()
    {
        $usuario =
            Auth::usuario();

        $id =
            $_POST['id']
            ?? $_GET['id']
            ?? null;

        if(!$id){

            Session::flash(
                'error',
                'Lista não informada.'
            );

            $this->redirect(
                'listaContato'
            );

            return;
        }

        $lista =
            $this->listaModel
            ->buscar(
                $id,
                $usuario['cliente_id']
            );

        if(!$lista){

            Session::flash(
                'error',
                'Lista não encontrada.'
            );

            $this->redirect(
                'listaContato'
            );

            return;
        }

        $contatos =
            $this->listaItemModel
            ->listarContatos(
                $id
            );

        $this->view(
            'listas/visualizar',
            [
                'titulo' => $lista['LST_Nome'],
                'lista' => $lista,
                'contatos' => $contatos
            ]
        );
    }

    public function criar()
    {
        $this->validarCsrfPost();

        $usuario =
            Auth::usuario();

        $nome =
            trim(
                $_POST['nome']
                ?? ''
            );

        if($nome == ''){

            Session::flash(
                'error',
                'Informe o nome da lista.'
            );

            $this->redirect('listaContato');

            return;
        }

        $listaId =
            $this->listaModel
            ->criar(
                $usuario['cliente_id'],
                $nome
            );

        Session::flash(
            'success',
            'Lista criada com sucesso.'
        );

        $this->redirect(
            'listaContato/visualizar&id='
            . $listaId
        );
    }

    public function salvarEdicao()
    {
        $this->validarCsrfPost();

        $usuario =
            Auth::usuario();

        $id =
            $_POST['id']
            ?? null;

        $nome =
            trim(
                $_POST['nome']
                ?? ''
            );

        if(!$id){

            Session::flash(
                'error',
                'Lista não informada.'
            );

            $this->redirect('listaContato');

            return;
        }

        if($nome == ''){

            Session::flash(
                'error',
                'Informe o nome da lista.'
            );

            $this->redirect('listaContato');

            return;
        }

        $lista =
            $this->listaModel
            ->buscar(
                $id,
                $usuario['cliente_id']
            );

        if(!$lista){

            Session::flash(
                'error',
                'Lista não encontrada.'
            );

            $this->redirect(
                'listaContato'
            );

            return;
        }

        $this->listaModel
            ->atualizar(
                $id,
                $usuario['cliente_id'],
                $nome
            );

        Session::flash(
            'success',
            'Lista atualizada com sucesso.'
        );

        $this->redirect(
            'listaContato'
        );
    }

    public function inativar()
    {
        $this->validarCsrfPost();

        $usuario =
            Auth::usuario();

        $id =
            $_POST['id']
            ?? null;

        if(!$id){

            Session::flash(
                'error',
                'Lista não informada.'
            );

            $this->redirect(
                'listaContato'
            );

            return;
        }

        $lista =
            $this->listaModel
            ->buscar(
                $id,
                $usuario['cliente_id']
            );

        if(!$lista){

            Session::flash(
                'error',
                'Lista não encontrada.'
            );

            $this->redirect(
                'listaContato'
            );

            return;
        }

        $this->listaModel
            ->inativar(
                $id,
                $usuario['cliente_id']
            );

        Session::flash(
            'success',
            'Lista inativada com sucesso.'
        );

        $this->redirect(
            'listaContato'
        );
    }

    private function normalizarTelefone($telefone)
    {
        $telefone =
            preg_replace('/\D/', '', $telefone);

        if(substr($telefone, 0, 2) != '55'){
            $telefone =
                '55' . $telefone;
        }

        return $telefone;
    }

    public function removerContato()
    {
        $this->validarCsrfPost();

        $usuario =
            Auth::usuario();

        $listaId =
            $_POST['lista'] ?? null;

        $contatoId =
            $_POST['contato'] ?? null;

        if(!$listaId || !$contatoId){

            Session::flash(
                'error',
                'Dados inválidos.'
            );

            $this->redirect(
                'listaContato'
            );

            return;
        }

        $lista =
            $this->listaModel->buscar(
                $listaId,
                $usuario['cliente_id']
            );

        if(!$lista){

            Session::flash(
                'error',
                'Lista não encontrada.'
            );

            $this->redirect(
                'listaContato'
            );

            return;
        }

        $this->listaItemModel
            ->removerContato(
                $listaId,
                $contatoId
            );

        Session::flash(
            'success',
            'Contato removido da lista.'
        );

        $this->redirect(
            'listaContato/visualizar&id='
            . $listaId
        );
    }

    public function adicionarContato()
    {
        $this->validarCsrfPost();

        $usuario =
            Auth::usuario();

        $listaId =
            $_POST['lista_id'] ?? null;

        $nome =
            trim($_POST['nome'] ?? '');

        $telefone =
            trim($_POST['telefone'] ?? '');

        if(
            !$listaId
            ||
            $nome == ''
            ||
            $telefone == ''
        ){

            Session::flash(
                'error',
                'Preencha todos os campos.'
            );

            $this->redirect(
                'listaContato/visualizar&id='
                . $listaId
            );

            return;
        }

        $lista =
            $this->listaModel->buscar(
                $listaId,
                $usuario['cliente_id']
            );

        if(!$lista){

            Session::flash(
                'error',
                'Lista não encontrada.'
            );

            $this->redirect(
                'listaContato'
            );

            return;
        }

        $telefone =
            $this->normalizarTelefone(
                $telefone
            );

        $contato =
            $this->contatoModel
            ->telefoneExiste(
                $usuario['cliente_id'],
                $telefone
            );

        if($contato){

            $contatoId =
                $contato['CON_ID'];

        }else{

            $contatoId =
                $this->contatoModel
                ->salvar([

                    'cliente_id' =>
                        $usuario['cliente_id'],

                    'nome' =>
                        $nome,

                    'telefone' =>
                        $telefone,

                    'dados_json' =>
                        null

                ]);
        }

        $this->listaItemModel
            ->adicionar(
                $listaId,
                $contatoId
            );

        Session::flash(
            'success',
            'Contato adicionado com sucesso.'
        );

        $this->redirect(
            'listaContato/visualizar&id='
            . $listaId
        );
    }

    public function duplicar()
    {
        $this->validarCsrfPost();

        $usuario =
            Auth::usuario();

        $id =
            $_POST['id'] ?? null;

        if(!$id){

            Session::flash(
                'error',
                'Lista não informada.'
            );

            $this->redirect(
                'listaContato'
            );

            return;
        }

        $lista =
            $this->listaModel->buscar(
                $id,
                $usuario['cliente_id']
            );

        if(!$lista){

            Session::flash(
                'error',
                'Lista não encontrada.'
            );

            $this->redirect(
                'listaContato'
            );

            return;
        }

        $novaListaId =
            $this->listaModel->duplicar(
                $id,
                $usuario['cliente_id']
            );

        $contatos =
            $this->listaItemModel
            ->listarIdsDaLista($id);

        foreach($contatos as $contato){

            $this->listaItemModel
                ->adicionar(
                    $novaListaId,
                    $contato['CON_ID']
                );

        }

        Session::flash(
            'success',
            'Lista duplicada com sucesso.'
        );

        $this->redirect(
            'listaContato'
        );
    }

}
