<?php

namespace Controllers;

use Core\Controller;
use Core\Auth;
use Core\Session;
use Core\Database;
use Models\Plano;
use Models\Cobranca;

class FinanceiroController extends Controller
{
    public function index()
    {
        Auth::cliente();

        $usuario = Auth::usuario();

        $planoModel = new Plano();
        $cobrancaModel = new Cobranca();

        $planos = $planoModel->listarAtivos();

        $cobranca = $cobrancaModel
            ->buscarPendentePorCliente($usuario['CLI_ID']);

        $this->view(
            'financeiro/index',
            [
                'titulo' => 'Financeiro',
                'planos' => $planos,
                'cobranca' => $cobranca
            ]
        );
    }

    public function escolherPlano()
    {
        Auth::cliente();

        if($_SERVER['REQUEST_METHOD'] !== 'POST'){
            $this->redirect('financeiro');
        }

        $usuario = Auth::usuario();

        $planoId = (int) ($_POST['plano'] ?? 0);

        $cobrancaExistente =
            $cobrancaModel->buscarPendentePorCliente(
                $usuario['CLI_ID']
            );

        if($cobrancaExistente){

            Session::flash(
                'error',
                'Já existe uma cobrança pendente para este cliente.'
            );

            $this->redirect('financeiro');

        }

        $planoModel = new Plano();
        $plano = $planoModel->buscar($planoId);

        if(!$plano){

            Session::flash(
                'error',
                'Plano inválido.'
            );

            $this->redirect('financeiro');
        }

        $db = Database::getInstance();

        $db->beginTransaction();

        try{

            $sql = $db->prepare("
                UPDATE clientes
                SET
                    CLI_Plano_DR = ?,
                    CLI_StatusPagamento = 'pendente'
                WHERE CLI_ID = ?
            ");

            $sql->execute([
                $plano['PLA_ID'],
                $usuario['CLI_ID']
            ]);

            $cobrancaModel = new Cobranca();

            $cobrancaModel->criar([
                'cliente' => $usuario['CLI_ID'],
                'plano' => $plano['PLA_ID'],
                'valor' => $plano['PLA_Valor'],
                'vencimento' => date('Y-m-d', strtotime('+3 days'))
            ]);

            $db->commit();

            Session::flash(
                'success',
                'Plano selecionado. A cobrança foi criada.'
            );

        }catch(\Exception $e){

            $db->rollBack();

            Session::flash(
                'error',
                'Erro ao gerar cobrança.'
            );
        }

        $this->redirect('financeiro');
    }
}