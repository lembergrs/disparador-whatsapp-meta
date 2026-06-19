<?php

namespace Controllers;

use Core\Controller;
use Core\Auth;
use Core\Session;
use Models\MetaConta;

class ConfiguracaoController extends Controller
{
    private $metaContaModel;

    public function __construct()
    {
        Auth::clienteAdmin();

        $this->metaContaModel =
            new MetaConta();
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

            $this->redirect('configuracao/meta');
        }

        return $dados;
    }

    public function salvarAutoResposta()
    {
        $this->validarCsrfPost();

        $usuario = Auth::usuario();

        if(
            !in_array(
                $usuario['nivel'] ?? null,
                ['cliente', 'cliente_admin'],
                true
            )
        ){
            die('Acesso negado');
        }

        if(!$this->metaContaModel->colunasAutoRespostaExistem()){
            Session::flash(
                'error',
                'A configuração de auto resposta ainda não está disponível. Entre em contato com o suporte.'
            );

            $this->redirect('configuracao/meta');
        }

        $contaId =
            (int) ($_POST['conta_id'] ?? 0);

        $dados =
            $this->prepararDadosAutoResposta(
                $_POST
            );

        $atualizou =
            $this->metaContaModel->atualizarAutoRespostaPorCliente(
                $contaId,
                (int) $usuario['CLI_ID'],
                $dados
            );

        Session::flash(
            $atualizou ? 'success' : 'error',
            $atualizou
                ? 'Auto resposta atualizada com sucesso.'
                : 'Conta Meta não encontrada para o seu cliente.'
        );

        $this->redirect('configuracao/meta');
    }

    public function meta()
    {
        $usuario =
            Auth::usuario();

        $contas =
            $this->metaContaModel
            ->listarPorCliente(
                $usuario['CLI_ID']
            );

        $limiteNumeros =
            $this->metaContaModel
            ->avaliarLimiteNumerosPorCliente(
                $usuario['CLI_ID']
            );

        $this->view(
            'configuracao/meta',
            [
                'titulo' => 'Números WhatsApp',
                'contas' => $contas,
                'limiteNumeros' => $limiteNumeros
            ]
        );
    }
}
