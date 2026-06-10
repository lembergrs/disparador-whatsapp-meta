<?php

namespace Controllers;

use Core\Controller;
use Core\Auth;
use Core\Session;
use Models\Plano;
use Models\Cobranca;
use Core\Database;

class FinanceiroAdminController extends Controller
{
    public function index()
    {
        Auth::admin();

        $planoModel = new Plano();
        $cobrancaModel = new Cobranca();

        $this->view(
            'financeiro_admin/index',
            [
                'titulo' => 'Financeiro',
                'planos' => $planoModel->listarAtivos(),
                'cobrancas' => $cobrancaModel->listar()
            ]
        );
    }

    public function salvarPlano()
    {
        Auth::admin();

        if($_SERVER['REQUEST_METHOD'] != 'POST'){
            $this->redirect('financeiroAdmin');
        }

        $planoModel = new Plano();

        $planoModel->salvar([
            'nome' => $_POST['nome'],
            'periodicidade' => $_POST['periodicidade'],
            'valor' => $_POST['valor'],
            'numeros' => $_POST['numeros'],
            'usuarios' => $_POST['usuarios'],
            'mensagens' => $_POST['mensagens'],
            'excedente' => $_POST['excedente']
        ]);

        Session::flash(
            'success',
            'Plano salvo com sucesso.'
        );

        $this->redirect('financeiroAdmin');
    }

    public function marcarPago()
    {
        Auth::admin();

        $id = (int) ($_GET['id'] ?? 0);

        if(!$id){
            $this->redirect('financeiroAdmin');
        }

        $cobrancaModel = new Cobranca();

        $cobranca =
            $cobrancaModel->buscar($id);

        if(!$cobranca){
            $this->redirect('financeiroAdmin');
        }

        $db = Database::getInstance();

        $db->beginTransaction();

        try{

            $cobrancaModel->marcarPago($id);

            $sql = $db->prepare("
                UPDATE clientes
                SET
                    CLI_StatusPagamento = 'pago',
                    CLI_StatusCadastro = 'ativo',
                    CLI_DataLiberacao = NOW()
                WHERE CLI_ID = ?
            ");

            $sql->execute([
                $cobranca['CLI_ID']
            ]);

            $db->commit();

            Session::flash(
                'success',
                'Pagamento confirmado.'
            );

        }catch(\Exception $e){

            $db->rollBack();

            Session::flash(
                'error',
                'Erro ao confirmar pagamento.'
            );
        }

        $this->redirect('financeiroAdmin');
    }

    public function cancelarCobranca()
    {
        Auth::admin();

        $id = (int) ($_GET['id'] ?? 0);

        if(!$id){
            $this->redirect('financeiroAdmin');
        }

        $cobrancaModel = new Cobranca();

        $cobrancaModel->cancelar($id);

        Session::flash(
            'success',
            'Cobrança cancelada.'
        );

        $this->redirect('financeiroAdmin');
    }
}