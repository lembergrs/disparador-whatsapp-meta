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
            return 'Não foi possível criar o template na Meta. Tente novamente em alguns instantes.';
        }

        $erro = $response['error'] ?? [];

        if(!is_array($erro)){
            return 'Não foi possível criar o template na Meta. Tente novamente em alguns instantes.';
        }

        $tituloUsuario = trim((string) ($erro['error_user_title'] ?? ''));
        $mensagemUsuario = trim((string) ($erro['error_user_msg'] ?? ''));

        if($mensagemUsuario !== ''){
            if($this->erroCategoriaTemplateEmProcessamento($tituloUsuario, $mensagemUsuario)){
                $mensagemUsuario = 'Já existe um template com este nome e idioma em processamento/exclusão na Meta. Aguarde o prazo informado pela Meta ou utilize outro nome para o template.';
            }

            if($tituloUsuario !== ''){
                return '<strong>' . htmlspecialchars($tituloUsuario, ENT_QUOTES, 'UTF-8') . '</strong><br>'
                    . htmlspecialchars($mensagemUsuario, ENT_QUOTES, 'UTF-8');
            }

            return htmlspecialchars($mensagemUsuario, ENT_QUOTES, 'UTF-8');
        }

        if(!empty($erro['message'])){
            return htmlspecialchars((string) $erro['message'], ENT_QUOTES, 'UTF-8');
        }

        return 'Não foi possível criar o template na Meta. Verifique os dados informados e tente novamente.';
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
