<?php

namespace Controllers;

use Core\Controller;
use Core\Auth;
use Core\Session;
use Core\Database;
use Models\Plano;
use Models\Cobranca;
use Models\MetaConta;
use Models\Assinatura;

class FinanceiroController extends Controller
{
    public function index()
    {
        Auth::clienteAdmin();

        $usuario = Auth::usuario();

        $planoModel = new Plano();
        $cobrancaModel = new Cobranca();
        $metaContaModel = new MetaConta();
        $assinaturaModel = new Assinatura();

        $planos = $planoModel->listarAtivos();
        $numerosAtivos =
            $metaContaModel->contarAtivasPorCliente(
                $usuario['CLI_ID']
            );

        $cobranca = $cobrancaModel
            ->buscarPendentePorCliente($usuario['CLI_ID']);

        $excedenteModel =
            new \Models\ExcedenteMensal();

        $excedente =
            $excedenteModel->buscarMesAtual(
                $usuario['CLI_ID']
            );

        $assinaturaAtual =
            $assinaturaModel->buscarAtualPorCliente(
                $usuario['CLI_ID']
            );

        $this->view(
            'financeiro/index',
            [
                'titulo' => 'Financeiro',
                'planos' => $planos,
                'cobranca' => $cobranca,
                'excedente' => $excedente,
                'numerosAtivos' => $numerosAtivos,
                'assinaturaAtual' => $assinaturaAtual
            ]
        );
    }

    public function escolherPlano()
    {
        Auth::clienteAdmin();

        if($_SERVER['REQUEST_METHOD'] !== 'POST'){
            $this->redirect('financeiro');
        }

        $usuario = Auth::usuario();

        $planoId = (int) ($_POST['plano'] ?? 0);
        $ciclo = $_POST['ciclo'] ?? 'mensal';

        if(!Plano::cicloValido($ciclo)){

            Session::flash(
                'error',
                'Ciclo de cobrança inválido.'
            );

            $this->redirect('financeiro');
        }

        $cobrancaModel = new Cobranca();

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

        $metaContaModel = new MetaConta();
        $validacaoNumeros =
            $metaContaModel->validarLimiteNumerosPlano(
                $usuario['CLI_ID'],
                $plano['PLA_LimiteNumeros']
            );

        if(!$validacaoNumeros['permitido']){

            Session::flash(
                'error',
                $validacaoNumeros['mensagem']
            );

            $this->redirect('financeiro');
        }

        $valorCiclo = Plano::valorPorCiclo($plano, $ciclo);
        $proximaCobranca = date(
            'Y-m-d',
            strtotime('+' . Plano::mesesPorCiclo($ciclo) . ' months')
        );

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

            $cobrancaModel->criar([
                'cliente' => $usuario['CLI_ID'],
                'plano' => $plano['PLA_ID'],
                'valor' => $valorCiclo,
                'vencimento' => date('Y-m-d', strtotime('+3 days')),
                'tipo' => 'mensalidade'
            ]);

            $assinaturaModel = new Assinatura();
            $assinaturaModel->criarOuAtualizarPorCliente(
                $usuario['CLI_ID'],
                $plano,
                'pendente',
                [
                    'ciclo' => $ciclo,
                    'valor' => $valorCiclo,
                    'proxima_cobranca' => $proximaCobranca
                ]
            );

            $db->commit();

            $_SESSION['usuario']['CLI_Plano_DR'] =
                $plano['PLA_ID'];

            $_SESSION['usuario']['CLI_StatusPagamento'] =
                'pendente';

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
