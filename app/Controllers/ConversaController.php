<?php

namespace Controllers;

use Core\Controller;
use Core\Auth;
use Core\Session;
use Models\Conversa;
use Services\MetaService;

class ConversaController extends Controller
{
    private $conversaModel;

    public function __construct()
    {
        Auth::cliente();

        $this->conversaModel = new Conversa();
    }

    public function index()
    {
        $usuario = Auth::usuario();

        $conversas =
            $this->conversaModel->listarConversas(
                $usuario['CLI_ID']
            );

        $conversaSelecionada = null;
        $mensagens = [];
        $janelaAberta = false;

        if(!empty($_GET['id'])){

            $conversaSelecionada =
                $this->conversaModel->buscar(
                    $_GET['id'],
                    $usuario['CLI_ID']
                );

            if($conversaSelecionada){

                $mensagens =
                    $this->conversaModel
                    ->listarMensagens(
                        $conversaSelecionada['CVS_ID']
                    );

                $janelaAberta =
                    $this->janelaAtendimentoAberta(
                        $conversaSelecionada['CVS_ID']
                    );

                $this->conversaModel
                    ->marcarComoLida(
                        $conversaSelecionada['CVS_ID'],
                        $usuario['CLI_ID']
                    );
            }
        }

        $this->view(
            'conversas/index',
            [
                'titulo' => 'Conversas',
                'conversas' => $conversas,
                'conversaSelecionada' => $conversaSelecionada,
                'mensagens' => $mensagens,
                'janelaAberta' => $janelaAberta
            ]
        );
    }

    public function enviar()
    {
        $usuario = Auth::usuario();

        $conversaId =
            $_POST['conversa_id'] ?? null;

        $mensagem =
            trim($_POST['mensagem'] ?? '');

        if(!$conversaId || $mensagem == ''){

            Session::flash(
                'error',
                'Informe a mensagem.'
            );

            $this->redirect('conversa');

            return;
        }

        $conversa =
            $this->conversaModel->buscar(
                $conversaId,
                $usuario['CLI_ID']
            );

        if(!$conversa){

            Session::flash(
                'error',
                'Conversa não encontrada.'
            );

            $this->redirect('conversa');

            return;
        }

        if(!$this->janelaAtendimentoAberta($conversaId)){

            Session::flash(
                'error',
                'A janela de atendimento de 24 horas está fechada. Use um template aprovado para iniciar nova conversa.'
            );

            $this->redirect(
                'conversa&id=' . $conversaId
            );

            return;
        }

        try{

            $meta =
                new MetaService(
                    $conversa['MTA_ID']
                );

            $response =
                $meta->enviarTexto(
                    $conversa['CVS_Numero'],
                    $mensagem
                );

            $messageId = null;
            $status = 'erro';

            if(isset($response['response']['messages'][0]['id'])){

                $messageId =
                    $response['response']['messages'][0]['id'];

                $status = 'enviado';

            }

            $this->conversaModel->salvarMensagem([

                'conversa_id' =>
                    $conversaId,

                'direcao' =>
                    'enviada',

                'tipo' =>
                    'text',

                'texto' =>
                    $mensagem,

                'message_id' =>
                    $messageId,

                'status' =>
                    $status,

                'retorno' =>
                    $response,

                'data_mensagem' =>
                    date('Y-m-d H:i:s')

            ]);

            if($status == 'enviado'){

                Session::flash(
                    'success',
                    'Mensagem enviada com sucesso.'
                );

            }else{

                $erro =
                    $response['response']['error']['message']
                    ??
                    'Erro ao enviar mensagem.';

                Session::flash(
                    'error',
                    $erro
                );
            }

        }catch(\Exception $e){

            Session::flash(
                'error',
                $e->getMessage()
            );

        }

        $this->redirect(
            'conversa&id=' . $conversaId
        );
    }

    private function janelaAtendimentoAberta($conversaId)
    {
        $ultimaRecebida =
            $this->conversaModel
            ->ultimaMensagemRecebida(
                $conversaId
            );

        if(!$ultimaRecebida){
            return false;
        }

        $limite =
            strtotime(
                $ultimaRecebida['MSG_DataMensagem']
            ) + (24 * 60 * 60);

        return time() <= $limite;
    }

    public function verificarAtualizacao()
    {
        header('Content-Type: application/json; charset=utf-8');

        $usuario =
            Auth::usuario();

        $ultimaLocal =
            $_GET['ultima']
            ?? '';

        $dados =
            $this->conversaModel
            ->ultimaAtualizacaoCliente(
                $usuario['CLI_ID']
            );

        $ultimaBanco =
            $dados['ultima']
            ?? '';

        echo json_encode([
            'atualizar' =>
                $ultimaBanco != $ultimaLocal,

            'ultima' =>
                $ultimaBanco
        ]);
    }

    public function ajaxLista()
    {
        $usuario =
            Auth::usuario();

        $conversas =
            $this->conversaModel
            ->listarConversas(
                $usuario['CLI_ID']
            );

        $conversaSelecionada = null;

        if(!empty($_GET['id'])){

            $conversaSelecionada =
                $this->conversaModel
                ->buscar(
                    $_GET['id'],
                    $usuario['CLI_ID']
                );
        }

        require '../app/Views/conversas/partials/lista.php';
    }

    public function ajaxMensagens()
    {
        $usuario =
            Auth::usuario();

        $id =
            $_GET['id']
            ?? null;

        if(!$id){
            exit;
        }

        $conversa =
            $this->conversaModel
            ->buscar(
                $id,
                $usuario['CLI_ID']
            );

        if(!$conversa){
            exit;
        }

        $mensagens =
            $this->conversaModel
            ->listarMensagens(
                $id
            );

        $this->conversaModel
            ->marcarComoLida(
                $id,
                $usuario['CLI_ID']
            );

        require '../app/Views/conversas/partials/mensagens.php';
    }

    public function enviarAjax()
    {
        header('Content-Type: application/json; charset=utf-8');

        try{

            $usuario = Auth::usuario();

            $conversaId =
                $_POST['conversa_id'] ?? null;

            $mensagem =
                trim($_POST['mensagem'] ?? '');

            if(!$conversaId || $mensagem == ''){

                echo json_encode([
                    'sucesso' => false,
                    'erro' => 'Informe a mensagem.'
                ]);

                return;
            }

            $conversa =
                $this->conversaModel->buscar(
                    $conversaId,
                    $usuario['CLI_ID']
                );

            if(!$conversa){

                echo json_encode([
                    'sucesso' => false,
                    'erro' => 'Conversa não encontrada.'
                ]);

                return;
            }

            if(!$this->janelaAtendimentoAberta($conversaId)){

                echo json_encode([
                    'sucesso' => false,
                    'erro' => 'A janela de atendimento de 24 horas está fechada. Use um template aprovado para iniciar nova conversa.'
                ]);

                return;
            }

            $meta =
                new MetaService(
                    $conversa['MTA_ID']
                );

            $response =
                $meta->enviarTexto(
                    $conversa['CVS_Numero'],
                    $mensagem
                );

            $messageId = null;
            $status = 'erro';

            if(isset($response['response']['messages'][0]['id'])){

                $messageId =
                    $response['response']['messages'][0]['id'];

                $status = 'enviado';

            }

            $this->conversaModel->salvarMensagem([

                'conversa_id' =>
                    $conversaId,

                'direcao' =>
                    'enviada',

                'tipo' =>
                    'text',

                'texto' =>
                    $mensagem,

                'message_id' =>
                    $messageId,

                'status' =>
                    $status,

                'retorno' =>
                    $response,

                'data_mensagem' =>
                    date('Y-m-d H:i:s')

            ]);

            if($status == 'enviado'){

                echo json_encode([
                    'sucesso' => true,
                    'message_id' => $messageId
                ]);

                return;
            }

            echo json_encode([
                'sucesso' => false,
                'erro' =>
                    $response['response']['error']['message']
                    ?? 'Erro ao enviar mensagem.'
            ]);

        }catch(\Exception $e){

            echo json_encode([
                'sucesso' => false,
                'erro' => $e->getMessage()
            ]);

        }
    }

}