<?php

namespace Controllers;

use Core\Controller;
use Core\Auth;
use Core\Session;
use Models\TemplateMeta;
use Models\MetaConta;
use Services\MetaService;

class TemplateController extends Controller
{
    private $templateModel;

    private $metaModel;





    public function __construct()
    {
        Auth::cliente();

        $this->templateModel =
            new TemplateMeta();

        $this->metaModel =
            new MetaConta();
    }





    public function index()
    {
        $usuario = Auth::usuario();

        $templates =
            $this->templateModel
            ->listarPorCliente(
                $usuario['CLI_ID']
            );

        $contas =
            $this->metaModel
            ->listarPorCliente(
                $usuario['CLI_ID']
            );

        $this->view(
            'templates/index',
            [

                'titulo' => 'Templates',

                'templates' => $templates,

                'contas' => $contas

            ]
        );
    }

    public function criar()
    {
        $metaId =
            $_POST['meta'];





        $meta =
            new MetaService($metaId);





        $response =
            $meta->criarTemplate($_POST);





        if(isset($response['id'])){

            Session::flash(
                'success',
                'Template enviado para aprovação.'
            );

        }else{

            $erro =
                $response['error']['message']
                ??
                'Erro ao criar template';





            Session::flash(
                'error',
                $erro
            );
        }






        $this->redirect(
            'template'
        );
    }



    public function sincronizar()
    {
        $metaId = $_GET['meta'];





        $meta =
            new MetaService($metaId);





        $templates =
            $meta->buscarTemplates();





        if(!isset($templates['data'])){

            Session::flash(
                'error',
                'Erro ao buscar templates.'
            );

            $this->redirect(
                'template'
            );
        }

        $idsMeta = [];

        foreach($templates['data'] as $template){

            $idsMeta[] = $template['id'];

            $this->templateModel
                ->salvarOuAtualizar(
                    $metaId,
                    $template
                );
        }

        $this->templateModel
            ->inativarAusentes(
                $metaId,
                $idsMeta
            );

        Session::flash(
            'success',
            'Templates sincronizados.'
        );





        $this->redirect(
            'template'
        );
    }
}