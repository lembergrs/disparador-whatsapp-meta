<?php

namespace Controllers;

use Core\Controller;
use Core\Auth;
use Core\Session;
use Models\Plano;
use Models\Cobranca;
use Core\Database;
use Models\Cliente;
use Models\MetaConta;
use Models\Assinatura;
use Services\FinanceiroRecorrenciaService;

class FinanceiroAdminController extends Controller
{
    public function index()
    {
        Auth::admin();

        $planoModel = new Plano();
        $cobrancaModel = new Cobranca();

        $clienteModel = new Cliente();

        $clientesFinanceiro =
            $clienteModel->listarFinanceiro();

        $this->view(
            'financeiro_admin/index',
            [
                'titulo' => 'Financeiro',
                'planos' => $planoModel->listarAtivos(),
                'cobrancas' => $cobrancaModel->listar(),
                'clientesFinanceiro' => $clientesFinanceiro
            ]
        );
    }

    public function salvarPlano()
    {
        Auth::admin();

        if($_SERVER['REQUEST_METHOD'] != 'POST'){
            $this->redirect('financeiroAdmin');
        }

        if(trim((string) ($_POST['valor_mensal'] ?? '')) === ''){
            Session::flash(
                'error',
                'Informe o valor mensal do plano.'
            );

            $this->redirect('financeiroAdmin');
        }

        $planoModel = new Plano();

        $planoModel->salvar([
            'nome' => $_POST['nome'],
            'periodicidade' => $_POST['periodicidade'],
            'valor_mensal' => $_POST['valor_mensal'] ?? $_POST['valor'] ?? '',
            'valor_trimestral' => $_POST['valor_trimestral'] ?? '',
            'valor_semestral' => $_POST['valor_semestral'] ?? '',
            'valor_anual' => $_POST['valor_anual'] ?? '',
            'numeros' => $_POST['numeros'],
            'usuarios' => $_POST['usuarios'],
            'mensagens' => $_POST['mensagens'],
            'excedente' => $_POST['excedente'],
            'cor' => $_POST['cor']
        ]);

        Session::flash(
            'success',
            'Plano salvo com sucesso.'
        );

        $this->redirect('financeiroAdmin');
    }

    public function editarPlano()
    {
        Auth::admin();

        if($_SERVER['REQUEST_METHOD'] != 'POST'){
            $this->redirect('financeiroAdmin');
        }

        $id = (int) ($_POST['plano_id'] ?? 0);

        if(!$id){

            Session::flash(
                'error',
                'Plano inválido.'
            );

            $this->redirect('financeiroAdmin');
        }

        if(trim((string) ($_POST['valor_mensal'] ?? '')) === ''){
            Session::flash(
                'error',
                'Informe o valor mensal do plano.'
            );

            $this->redirect('financeiroAdmin');
        }

        $planoModel = new Plano();

        $planoModel->editar($id, [
            'nome' => $_POST['nome'],
            'periodicidade' => $_POST['periodicidade'],
            'valor_mensal' => $_POST['valor_mensal'] ?? $_POST['valor'] ?? '',
            'valor_trimestral' => $_POST['valor_trimestral'] ?? '',
            'valor_semestral' => $_POST['valor_semestral'] ?? '',
            'valor_anual' => $_POST['valor_anual'] ?? '',
            'numeros' => $_POST['numeros'],
            'usuarios' => $_POST['usuarios'],
            'mensagens' => $_POST['mensagens'],
            'excedente' => $_POST['excedente'],
            'cor' => $_POST['cor']
        ]);

        Session::flash(
            'success',
            'Plano atualizado com sucesso.'
        );

        $this->redirect('financeiroAdmin');
    }

    public function inativarPlano()
    {
        Auth::admin();

        $id = (int) ($_GET['id'] ?? 0);

        if(!$id){

            Session::flash(
                'error',
                'Plano inválido.'
            );

            $this->redirect('financeiroAdmin');
        }

        $planoModel = new Plano();

        $planoModel->inativar($id);

        Session::flash(
            'success',
            'Plano inativado com sucesso.'
        );

        $this->redirect('financeiroAdmin');
    }

    public function marcarPago()
    {
        Auth::admin();

        $id = (int) ($_GET['id'] ?? 0);

        if(!$id){
            $this->redirect('financeiroAdmin#tabCobrancas');
        }

        $cobrancaModel = new Cobranca();

        $cobranca =
            $cobrancaModel->buscar($id);

        if(!$cobranca){
            $this->redirect('financeiroAdmin#tabCobrancas');
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

            $assinaturaModel = new Assinatura();
            $assinatura = $assinaturaModel->buscarAtualPorCliente($cobranca['CLI_ID']);

            if($assinatura){
                $assinaturaModel->ativar($assinatura['ASS_ID']);
            }

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

        $this->redirect('financeiroAdmin#tabCobrancas');
    }


    public function processarVencimentos()
    {
        Auth::admin();

        try{
            $service = new FinanceiroRecorrenciaService();
            $resultado = $service->processarVencimentos();

            Session::flash(
                'success',
                'Vencimentos processados com sucesso. Cobranças vencidas: ' .
                $resultado['cobrancas_vencidas'] .
                ' | Assinaturas vencidas: ' .
                $resultado['assinaturas_vencidas'] .
                ' | Clientes atualizados: ' .
                $resultado['clientes_atualizados'] . '.'
            );
        }catch(\Exception $e){
            Session::flash(
                'error',
                'Erro ao processar vencimentos financeiros.'
            );
        }

        $this->redirect('financeiroAdmin#tabCobrancas');
    }

    public function alterarPlanoCliente()
    {
        Auth::admin();

        if($_SERVER['REQUEST_METHOD'] != 'POST'){
            $this->redirect('financeiroAdmin');
        }

        $clienteId = (int) ($_POST['cliente_id'] ?? 0);
        $planoId = (int) ($_POST['plano_id'] ?? 0);
        $ciclo = $_POST['ciclo'] ?? 'mensal';

        if(!$clienteId || !$planoId || !Plano::cicloValido($ciclo)){

            Session::flash(
                'error',
                'Cliente ou plano inválido.'
            );

            $this->redirect('financeiroAdmin');
        }

        $planoModel = new Plano();
        $plano =
            $planoModel->buscar(
                $planoId
            );

        if(!$plano){

            Session::flash(
                'error',
                'Plano inválido.'
            );

            $this->redirect('financeiroAdmin#tabClientes');
        }

        $metaContaModel = new MetaConta();
        $validacaoNumeros =
            $metaContaModel->validarLimiteNumerosPlano(
                $clienteId,
                $plano['PLA_LimiteNumeros']
            );

        if(!$validacaoNumeros['permitido']){

            Session::flash(
                'error',
                $validacaoNumeros['mensagem']
            );

            $this->redirect('financeiroAdmin#tabClientes');
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
                SET CLI_Plano_DR = ?
                WHERE CLI_ID = ?
            ");

            $sql->execute([
                $planoId,
                $clienteId
            ]);

            $assinaturaModel = new Assinatura();
            $assinaturaModel->criarOuAtualizarPorCliente(
                $clienteId,
                $plano,
                'ativa',
                [
                    'ciclo' => $ciclo,
                    'valor' => $valorCiclo,
                    'proxima_cobranca' => $proximaCobranca
                ]
            );

            $db->commit();

            Session::flash(
                'success',
                'Plano do cliente atualizado.'
            );

        }catch(\Exception $e){

            $db->rollBack();

            Session::flash(
                'error',
                'Erro ao atualizar plano do cliente.'
            );
        }

        $this->redirect('financeiroAdmin#tabClientes');
    }

    public function suspenderCliente()
    {
        Auth::admin();

        $clienteId = (int) ($_GET['id'] ?? 0);

        if(!$clienteId){

            Session::flash(
                'error',
                'Cliente inválido.'
            );

            $this->redirect('financeiroAdmin#tabClientes');
        }

        $db = Database::getInstance();

        $sql = $db->prepare("
            UPDATE clientes
            SET CLI_StatusCadastro = 'suspenso'
            WHERE CLI_ID = ?
        ");

        $sql->execute([
            $clienteId
        ]);

        Session::flash(
            'success',
            'Cliente suspenso.'
        );

        $this->redirect('financeiroAdmin#tabClientes');
    }
    public function reativarCliente()
    {
        Auth::admin();

        $clienteId = (int) ($_GET['id'] ?? 0);

        if(!$clienteId){

            Session::flash(
                'error',
                'Cliente inválido.'
            );

            $this->redirect('financeiroAdmin#tabClientes');
        }

        $db = Database::getInstance();

        $sql = $db->prepare("
            UPDATE clientes
            SET CLI_StatusCadastro = 'ativo'
            WHERE CLI_ID = ?
        ");

        $sql->execute([
            $clienteId
        ]);

        Session::flash(
            'success',
            'Cliente reativado.'
        );

        $this->redirect('financeiroAdmin#tabClientes');
    }

}
