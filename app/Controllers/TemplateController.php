<?php

namespace Controllers;

use Core\Controller;
use Core\Auth;
use Core\Session;
use Models\TemplateMeta;
use Models\MetaConta;
use Services\MetaService;
use Services\TemplateMediaPreviewService;
use Exception;

class TemplateController extends Controller
{
    private $templateModel;

    private $metaModel;





    public function __construct()
    {
        Auth::clienteAdmin();

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
            ->listarPorUsuario($usuario);

        $contas =
            $this->metaModel
            ->listarPorUsuario($usuario);

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

        if(!$this->metaModel->buscarPorUsuario($metaId, $usuario)){
            Session::flash('error', 'Conta Meta inválida.');
            $this->redirect('template');
        }





        $meta =
            new MetaService($metaId, $usuario['CLI_ID']);





        $previewLocal = null;
        $headerTipo = strtoupper((string) ($_POST['header_tipo'] ?? ''));

        try{
            if(in_array($headerTipo, ['IMAGE', 'VIDEO', 'DOCUMENT'], true) && !empty($_FILES['header_media'])){
                $previewService = new TemplateMediaPreviewService();
                $previewLocal = $previewService->salvarCopiaPreview(
                    $_FILES['header_media'],
                    $headerTipo
                );
            }

            $response =
                $meta->criarTemplate($_POST);
        }catch(Exception $e){
            if($previewLocal){
                (new TemplateMediaPreviewService())->removerCopia($previewLocal);
            }
            Session::flash('error', $e->getMessage());
            $this->redirect('template');
        }





        if(isset($response['id'])){

            if(!empty($response['template_local'])){
                $templateLocal = $response['template_local'];
                $templateLocal['id'] = $response['id'];
                $templateLocal['status'] = $response['status'] ?? ($templateLocal['status'] ?? 'PENDING');

                if($previewLocal){
                    $templateLocal['header_media_url_exemplo'] = $previewLocal['url'];
                    $templateLocal['header_media_nome'] = $previewLocal['nome_original'];
                    $templateLocal['header_media_tipo'] = $previewLocal['tipo'];
                }

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

            if($previewLocal){
                (new TemplateMediaPreviewService())->removerCopia($previewLocal);
            }

            Session::set(
                'template_meta_error_modal',
                $this->extrairErroTemplateMeta($response)
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

        $template = $this->templateModel->buscarPorUsuario($id, $usuario);

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

        if(!$this->metaModel->buscarPorUsuario($metaId, $usuario)){
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

        $this->templateModel->inativarPorUsuario(
            $id,
            $usuario
        );

        Session::flash(
            'success',
            'Template removido da listagem.'
        );

        $this->redirect('template');
    }




    private function extrairErroTemplateMeta($response)
    {
        $modal = [
            'titulo' => 'Não foi possível criar o template',
            'destaque' => '',
            'mensagem' => 'Não foi possível criar o template na Meta. Verifique os dados informados e tente novamente.'
        ];

        if(!is_array($response)){
            return $modal;
        }

        $erro = $response['error'] ?? [];

        if(!is_array($erro)){
            return $modal;
        }

        $tituloUsuario = trim((string) ($erro['error_user_title'] ?? ''));
        $mensagemUsuario = trim((string) ($erro['error_user_msg'] ?? ''));

        if($mensagemUsuario !== ''){
            $modal['destaque'] = $tituloUsuario;
            $modal['mensagem'] = $this->erroCategoriaTemplateEmProcessamento($tituloUsuario, $mensagemUsuario)
                ? 'Já existe um template com este nome e idioma em processamento/exclusão na Meta. Aguarde o prazo informado pela Meta ou utilize outro nome para o template.'
                : $mensagemUsuario;

            return $modal;
        }

        if(!empty($erro['message'])){
            $modal['destaque'] = $tituloUsuario;
            $modal['mensagem'] = (string) $erro['message'];

            return $modal;
        }

        return $modal;
    }

    private function erroCategoriaTemplateEmProcessamento($titulo, $mensagem)
    {
        $texto = mb_strtolower((string) $titulo . ' ' . (string) $mensagem, 'UTF-8');

        return strpos($texto, 'categoria') !== false
            && (
                strpos($texto, 'exclu') !== false
                || strpos($texto, 'process') !== false
                || strpos($texto, 'alterar a categoria') !== false
            );
    }


}
