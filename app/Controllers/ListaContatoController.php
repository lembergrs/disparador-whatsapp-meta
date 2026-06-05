<?php

namespace Controllers;

use Core\Controller;
use Core\Auth;
use Core\Session;

use Models\ListaContato;
use Models\ListaContatoItem;

class ListaContatoController extends Controller
{
    private $listaModel;
    private $listaItemModel;

    public function __construct()
    {
        Auth::check();

        $this->listaModel =
            new ListaContato();

        $this->listaItemModel =
            new ListaContatoItem();
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
            $_GET['id']
            ?? null;

        if(!$id){

            Session::flash(
                'error',
                'Lista não informada.'
            );

            $this->redirect(
                'listacontato'
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
                'listacontato'
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

    public function salvarEdicao()
    {
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

        if(!$id || $nome == ''){

            Session::flash(
                'error',
                'Informe o nome da lista.'
            );

            $this->redirect(
                'listacontato'
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
                'listacontato'
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
            'listacontato'
        );
    }

    public function inativar()
    {
        $usuario =
            Auth::usuario();

        $id =
            $_GET['id']
            ?? null;

        if(!$id){

            Session::flash(
                'error',
                'Lista não informada.'
            );

            $this->redirect(
                'listacontato'
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
                'listacontato'
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
            'listacontato'
        );
    }
}