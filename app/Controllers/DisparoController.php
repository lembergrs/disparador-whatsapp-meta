<?php

namespace Controllers;

use Core\Controller;
use Core\Auth;
use Models\MetaConta;
use Models\TemplateMeta;
use Models\Disparo;
use Services\MetaService;

class DisparoController extends Controller
{
    private $metaModel;

    private $templateModel;





    public function __construct()
    {
        Auth::cliente();

        $this->metaModel =
            new MetaConta();

        $this->templateModel =
            new TemplateMeta();
    }






    public function index()
    {
        $usuario =
            Auth::usuario();





        $contas =
            $this->metaModel
            ->listarPorCliente(
                $usuario['CLI_ID']
            );





        $templates =
            $this->templateModel
            ->listarPorCliente(
                $usuario['CLI_ID']
            );





        $this->view(
            'disparos/index',
            [

                'titulo' => 'Disparador',

                'contas' => $contas,

                'templates' => $templates

            ]
        );
    }

    public function enviar()
    {
        $usuario =
            Auth::usuario();





        $template =
            $this->templateModel
            ->buscar(
                $_POST['template']
            );





        $meta =
            new MetaService(
                $_POST['meta']
            );





        $response =
            $meta->enviarTemplate(

                $_POST['numero'],

                $template,

                $_POST['variaveis']
                ?? []

            );






        $messageId = null;

        $status = 'erro';





        if(isset($response['messages'][0]['id'])){

            $messageId =
                $response['messages'][0]['id'];

            $status = 'enviado';

        }






        $disparo =
            new Disparo();





        $disparo->salvar([

            'cliente' =>
                $usuario['CLI_ID'],

            'meta' =>
                $_POST['meta'],

            'template_id' =>
                $_POST['template'],

            'numero' =>
                $_POST['numero'],

            'template' =>
                $template['TMP_Nome'],

            'variaveis' =>
                $_POST['variaveis']
                ?? [],

            'message_id' =>
                $messageId,

            'status' =>
                $status,

            'retorno' =>
                $response

        ]);






        if($status == 'enviado'){

            \Core\Session::flash(
                'success',
                'Mensagem enviada com sucesso.'
            );

        }else{

            $erro =
                $response['error']['message']
                ??
                'Erro ao enviar mensagem';





            \Core\Session::flash(
                'error',
                $erro
            );
        }






        $this->redirect(
            'disparo'
        );
    }

}