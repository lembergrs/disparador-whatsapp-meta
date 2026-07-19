<?php

namespace Controllers;

use Core\Controller;
use Core\Auth;
use Core\Session;
use Models\Plano;
use Models\Cobranca;
use Models\Cliente;
use Services\FinanceiroWorkflowService;

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
        $this->validarCsrfPost();

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
        $this->validarCsrfPost();

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
        $this->validarCsrfPost();

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
        $this->validarCsrfPost();
        Auth::admin();
        try{
            (new FinanceiroWorkflowService())->confirmarPagamentoManual((int) ($_GET['id'] ?? 0));
            Session::flash('success', 'Pagamento lançado com sucesso.');
        }catch(\Throwable $e){
            Session::flash('error', $e instanceof \DomainException ? $e->getMessage() : 'Não foi possível lançar o pagamento.');
        }
        $this->redirect('financeiroAdmin#tabCobrancas');
    }

    public function cancelarCobranca()
    {
        $this->validarCsrfPost();
        Auth::admin();
        try{
            (new Cobranca())->cancelar((int) ($_GET['id'] ?? 0));
            Session::flash('success', 'Cobrança cancelada.');
        }catch(\Throwable $e){
            error_log('Erro ao cancelar cobrança: ' . $e->getMessage());
            Session::flash('error', 'Não foi possível cancelar a cobrança.');
        }
        $this->redirect('financeiroAdmin#tabCobrancas');
    }


    public function processarVencimentos()
    {
        $this->validarCsrfPost();
        Auth::admin();
        try{
            $resultado = (new FinanceiroWorkflowService())->processarVencimentos();
            Session::flash('success', 'Vencimentos processados com sucesso. Cobranças vencidas: ' . $resultado['cobrancas_vencidas'] . ' | Assinaturas vencidas: ' . $resultado['assinaturas_vencidas'] . ' | Clientes atualizados: ' . $resultado['clientes_atualizados'] . '.');
        }catch(\Throwable $e){ Session::flash('error', 'Erro ao processar vencimentos financeiros.'); }
        $this->redirect('financeiroAdmin#tabCobrancas');
    }


    public function gerarCobrancasRecorrentes()
    {
        $this->validarCsrfPost();
        Auth::admin();
        try{
            $resultado = (new FinanceiroWorkflowService())->gerarCobrancasRecorrentes();
            Session::flash('success', 'Cobranças recorrentes processadas. Geradas: ' . $resultado['cobrancas_geradas'] . ' | Assinaturas processadas: ' . $resultado['assinaturas_processadas'] . ' | Ignoradas por duplicidade: ' . $resultado['cobrancas_ignoradas_duplicidade'] . ' | Erros: ' . $resultado['erros'] . '.');
        }catch(\Throwable $e){ Session::flash('error', 'Erro ao gerar cobranças recorrentes.'); }
        $this->redirect('financeiroAdmin#tabCobrancas');
    }

    public function alterarPlanoCliente()
    {
        $this->validarCsrfPost();
        Auth::admin();
        try{
            (new FinanceiroWorkflowService())->alterarPlanoCliente((int) ($_POST['cliente_id'] ?? 0), (int) ($_POST['plano_id'] ?? 0), (string) ($_POST['ciclo'] ?? 'mensal'));
            Session::flash('success', 'Plano do cliente atualizado.');
        }catch(\Throwable $e){ Session::flash('error', $e instanceof \DomainException ? $e->getMessage() : 'Erro ao atualizar plano do cliente.'); }
        $this->redirect('financeiroAdmin#tabClientes');
    }

    public function suspenderCliente()
    {
        $this->validarCsrfPost();
        Auth::admin();
        try{
            (new Cliente())->atualizarEstadoFinanceiro((int) ($_GET['id'] ?? 0), ['status_cadastro'=>'suspenso']);
            Session::flash('success', 'Cliente suspenso.');
        }catch(\Throwable $e){
            error_log('Erro ao suspender cliente: ' . $e->getMessage());
            Session::flash('error', 'Não foi possível suspender o cliente.');
        }
        $this->redirect('financeiroAdmin#tabClientes');
    }
    public function reativarCliente()
    {
        $this->validarCsrfPost();
        Auth::admin();
        try{
            (new FinanceiroWorkflowService())->reativarContrato((int) ($_GET['id'] ?? 0));
            Session::flash('success', 'Cliente reativado e nova cobrança gerada.');
        }catch(\Throwable $e){ Session::flash('error', $e instanceof \DomainException ? $e->getMessage() : 'Não foi possível reativar o contrato.'); }
        $this->redirect('financeiroAdmin#tabClientes');
    }

}
