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

        $contatoModel = new Contato();

        $camposContato =
            $contatoModel->camposJsonPorCliente(
                $usuario['cliente_id']
            );

        $this->view(
            'campanhas/index',
            [
                'titulo' => 'Campanhas',
                'campanhas' => $campanhas,
                'templates' => $templates,
                'camposContato' => $camposContato
            ]
        );
    }

    public function criar()
    {
        $usuario =
            Auth::usuario();

        $campanhaId =
            $this->campanhaModel->salvar([

                'cliente_id' => $usuario['CLI_ID'],
                'template_id' => $_POST['template'],
                'nome' => trim($_POST['nome']),
                'descricao' => trim($_POST['descricao']),
                'data_agendamento' =>
                    !empty($_POST['data_agendamento'])
                        ? $_POST['data_agendamento']
                        : date('Y-m-d H:i:s')

            ]);


        $contatoModel = new Contato();

        $filaModel = new FilaEnvio();

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
    
    public function detalhes()
    {
        $id = $_GET['id'] ?? null;

        if(!$id){
            \Core\Session::flash(
                'error',
                'Campanha não informada.'
            );

            $this->redirect('campanha');
        }

        $campanha =
            $this->campanhaModel->buscar($id);

        $fila =
            $this->campanhaModel->listarFila($id);

        $this->view(
            'campanhas/detalhes',
            [
                'titulo' => 'Detalhes da Campanha',
                'campanha' => $campanha,
                'fila' => $fila
            ]
        );
    }

    public function cancelar()
    {
        $usuario = Auth::usuario();

        $id = $_GET['id'] ?? null;

        if(!$id){

            \Core\Session::flash(
                'error',
                'Campanha não informada.'
            );

            $this->redirect('campanha');
        }

        $this->campanhaModel->cancelar(
            $id,
            $usuario['CLI_ID']
        );

        \Core\Session::flash(
            'success',
            'Campanha cancelada com sucesso.'
        );

        $this->redirect('campanha/detalhes&id=' . $id);
    }

}