<?php

namespace Controllers;

use Core\Controller;
use Core\Auth;
use Core\Session;
use Models\MetaConta;
use Models\Cliente;
use Services\MetaService;

class MetaContaController extends Controller
{
    private $metaModel;

    private $clienteModel;





    public function __construct()
    {
        Auth::admin();

        $this->metaModel =
            new MetaConta();

        $this->clienteModel =
            new Cliente();
    }





    private function gerarWebhookVerifyToken()
    {
        return bin2hex(
            random_bytes(32)
        );
    }


    private function prepararDadosAutoResposta($dados)
    {
        $dados['auto_resposta_ativa'] =
            ($dados['auto_resposta_ativa'] ?? 'N') == 'S'
            ? 'S'
            : 'N';

        $dados['auto_resposta_texto'] =
            trim(
                $dados['auto_resposta_texto'] ?? ''
            );

        $dados['auto_resposta_intervalo_minutos'] =
            max(
                1,
                (int) ($dados['auto_resposta_intervalo_minutos'] ?? 1440)
            );

        if($dados['auto_resposta_ativa'] == 'S' && $dados['auto_resposta_texto'] == ''){

            Session::flash(
                'error',
                'Informe o texto da auto resposta para ativá-la.'
            );

            $this->redirect(
                'metaConta'
            );
        }

        return $dados;
    }

    private function prepararDadosWebhookVerifyToken($dados)
    {
        $dados['webhook_verify_token'] =
            trim(
                $dados['webhook_verify_token'] ?? ''
            );

        if($dados['webhook_verify_token'] === ''){

            $dados['webhook_verify_token'] =
                $this->gerarWebhookVerifyToken();
        }

        return $dados;
    }

    public function index()
    {
        $contas =
            $this->metaModel->listar();

        $clientes =
            $this->clienteModel->listar();

        $colunaWebhookVerifyTokenExiste =
            $this->metaModel->colunaWebhookVerifyTokenExiste();





        $this->view(
            'meta_contas/index',
            [

                'titulo' => 'Contas Meta',

                'contas' => $contas,

                'clientes' => $clientes,

                'colunaWebhookVerifyTokenExiste' => $colunaWebhookVerifyTokenExiste,

                'colunasAutoRespostaExistem' => $this->metaModel->colunasAutoRespostaExistem()

            ]
        );
    }





    public function salvar()
    {
        $clienteId =
            (int) ($_POST['cliente'] ?? 0);

        $limite =
            $this->metaModel
            ->avaliarLimiteNumerosPorCliente(
                $clienteId
            );

        if(!$limite['permitido']){

            Session::flash(
                'error',
                $limite['mensagem']
            );

            $this->redirect(
                'metaConta'
            );
        }

        $dados =
            $this->prepararDadosAutoResposta(
                $this->prepararDadosWebhookVerifyToken(
                    $_POST
                )
            );

        if(!$this->metaModel->colunaWebhookVerifyTokenExiste()){

            Session::flash(
                'error',
                'A coluna MTA_WebhookVerifyToken não existe em meta_contas. Crie a coluna antes de salvar o token do webhook.'
            );

            $this->redirect(
                'metaConta'
            );
        }

        if(!$this->metaModel->colunasAutoRespostaExistem()){

            Session::flash(
                'error',
                'As colunas de auto resposta não existem em meta_contas. Crie as colunas antes de salvar a configuração.'
            );

            $this->redirect(
                'metaConta'
            );
        }

        $this->metaModel->salvar(
            $dados
        );





        Session::flash(
            'success',
            'Conta Meta cadastrada com sucesso.'
        );





        $this->redirect(
            'metaConta'
        );
    }





    public function inativar()
    {
        $id = $_GET['id'];

        $this->metaModel->inativar(
            $id
        );





        Session::flash(
            'success',
            'Conta Meta inativada.'
        );





        $this->redirect(
            'metaConta'
        );
    }

    public function testar()
    {
        $id = $_GET['id'];

        try{

            $meta =
                new MetaService($id);

            $resultado =
                $meta->testarConexao();





            if($resultado['sucesso']){

                Session::flash(
                    'success',
                    'Conexão realizada com sucesso.'
                );

            }else{

                Session::flash(
                    'error',
                    'Erro ao conectar com a Meta.'
                );

            }

        }catch(\Exception $e){

            Session::flash(
                'error',
                $e->getMessage()
            );

        }





        $this->redirect(
            'metaConta'
        );
    }

    public function atualizar()
    {
        $id =
            (int) ($_POST['id'] ?? 0);

        $contaAtual =
            $this->metaModel->buscar(
                $id
            );

        if(!$contaAtual){

            Session::flash(
                'error',
                'Conta Meta inválida.'
            );

            $this->redirect(
                'metaConta'
            );
        }

        $clienteId =
            (int) ($_POST['cliente'] ?? 0);

        $clienteAlterado =
            (int) $contaAtual['CLI_ID']
            !==
            $clienteId;

        if($clienteAlterado){

            $limite =
                $this->metaModel
                ->avaliarLimiteNumerosPorCliente(
                    $clienteId,
                    $id
                );

            if(!$limite['permitido']){

                Session::flash(
                    'error',
                    $limite['mensagem']
                );

                $this->redirect(
                    'metaConta'
                );
            }
        }

        $dados =
            $this->prepararDadosAutoResposta(
                $this->prepararDadosWebhookVerifyToken(
                    $_POST
                )
            );

        if(!$this->metaModel->colunaWebhookVerifyTokenExiste()){

            Session::flash(
                'error',
                'A coluna MTA_WebhookVerifyToken não existe em meta_contas. Crie a coluna antes de salvar o token do webhook.'
            );

            $this->redirect(
                'metaConta'
            );
        }

        if(!$this->metaModel->colunasAutoRespostaExistem()){

            Session::flash(
                'error',
                'As colunas de auto resposta não existem em meta_contas. Crie as colunas antes de salvar a configuração.'
            );

            $this->redirect(
                'metaConta'
            );
        }

        $this->metaModel->atualizar(
            $id,
            $dados
        );





        Session::flash(
            'success',
            'Conta Meta atualizada com sucesso.'
        );





        $this->redirect(
            'metaConta'
        );
    }

}
