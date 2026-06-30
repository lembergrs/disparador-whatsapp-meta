<?php

namespace Controllers;

use Core\Controller;
use Core\Auth;
use Core\Session;
use Models\TemplateMeta;
use Models\MetaConta;
use Services\MetaService;
use Exception;

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
        $this->validarCsrfPost();

        $usuario = Auth::usuario();

        $metaId =
            (int) ($_POST['meta'] ?? 0);

        if(!$this->metaModel->buscarPorCliente($metaId, $usuario['CLI_ID'])){
            Session::flash('error', 'Conta Meta inválida.');
            $this->redirect('template');
        }





        $meta =
            new MetaService($metaId, $usuario['CLI_ID']);





        try{
            $response =
                $meta->criarTemplate($_POST);
        }catch(Exception $e){
            Session::flash('error', $e->getMessage());
            $this->redirect('template');
        }





        if(isset($response['id'])){

            if(!empty($response['template_local'])){
                $templateLocal = $response['template_local'];
                $templateLocal['id'] = $response['id'];
                $templateLocal['status'] = $response['status'] ?? ($templateLocal['status'] ?? 'PENDING');

                $this->templateModel->salvarOuAtualizar(
                    $metaId,
                    $templateLocal
                );
            }

            Session::flash(
                'success',
                'Template enviado para aprovação.'
            );

        }else{

            $erro =
                $this->extrairErroTemplateMeta($response);





            Session::flash(
                'error',
                $erro
            );
        }






        $this->redirect(
            'template'
        );
    }



    public function editar()
    {
        $this->validarCsrfPost();

        $usuario = Auth::usuario();
        $id = (int) ($_POST['id'] ?? 0);

        $template = $this->templateModel->buscarPorCliente($id, $usuario['CLI_ID']);

        if(!$template){
            Session::flash('error', 'Template inválido.');
            $this->redirect('template');
        }

        Session::flash(
            'error',
            'Templates aprovados pela Meta podem exigir criação de um novo template para alteração.'
        );

        $this->redirect('template');
    }



    public function sincronizar()
    {
        $this->validarCsrfPost();

        $usuario = Auth::usuario();

        $metaId = (int) ($_POST['meta'] ?? 0);

        if(!$this->metaModel->buscarPorCliente($metaId, $usuario['CLI_ID'])){
            Session::flash('error', 'Conta Meta inválida.');
            $this->redirect('template');
        }





        $meta =
            new MetaService($metaId, $usuario['CLI_ID']);





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

    public function inativar()
    {
        $this->validarCsrfPost();

        $usuario = Auth::usuario();

        $id = (int) ($_POST['id'] ?? 0);

        if($id <= 0){

            Session::flash(
                'error',
                'Template não informado.'
            );

            $this->redirect('template');
        }

        $this->templateModel->inativar(
            $id,
            $usuario['CLI_ID']
        );

        Session::flash(
            'success',
            'Template removido da listagem.'
        );

        $this->redirect('template');
    }




    private function extrairErroTemplateMeta($response)
    {
        if(!is_array($response)){
            return 'Erro ao criar template';
        }

        if(!empty($response['error']['error_data']['details'])){
            return $response['error']['error_data']['details'];
        }

        if(!empty($response['error']['message'])){
            return $response['error']['message'];
        }

        return 'Erro ao criar template';
    }

}
