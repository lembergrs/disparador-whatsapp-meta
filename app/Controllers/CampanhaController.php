<?php

namespace Controllers;

use Core\Controller;
use Core\Auth;
use Core\Session;
use Models\Campanha;
use Models\TemplateMeta;
use Models\Contato;
use Models\FilaEnvio;

class CampanhaController extends Controller
{
    private $campanhaModel;

    private $templateModel;

    public function __construct()
    {
        Auth::check();

        $this->campanhaModel =
            new Campanha();

        $this->templateModel =
            new TemplateMeta();
    }

    public function index()
    {
        $usuario =
            Auth::usuario();

        $campanhas =
            $this->campanhaModel
            ->listarPorCliente(
                $usuario['cliente_id']
            );

        $templates =
            $this->templateModel
            ->listarPorCliente(
                $usuario['cliente_id']
            );

        $this->view(
            'campanhas/index',
            [
                'titulo' => 'Campanhas',
                'campanhas' => $campanhas,
                'templates' => $templates
            ]
        );
    }

    public function criar()
    {
        $usuario =
            Auth::usuario();

        $campanhaId =
            $this->campanhaModel->salvar([

                'cliente_id' =>
                    $usuario['CLI_ID'],

                'template_id' =>
                    $_POST['template'],

                'nome' =>
                    trim($_POST['nome']),

                'descricao' =>
                    trim($_POST['descricao'])

            ]);





        $contatoModel =
            new Contato();

        $filaModel =
            new FilaEnvio();





        $contatos =
            $contatoModel
            ->listarIdsPorCliente(
                $usuario['CLI_ID']
            );





        foreach($contatos as $contato){

            $filaModel->adicionar(

                $campanhaId,

                $contato['CON_ID']

            );

        }





        $this->campanhaModel
            ->atualizarTotalContatos(

                $campanhaId,

                count($contatos)

            );





        \Core\Session::flash(

            'success',

            'Campanha criada com '
            . count($contatos)
            . ' contatos.'

        );





        $this->redirect(
            'campanha'
        );
    }
}