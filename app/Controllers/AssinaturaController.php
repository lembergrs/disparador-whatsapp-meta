<?php

namespace Controllers;

use Core\Controller;
use Core\Auth;
use Core\Session;
use Models\Assinatura;
use Models\Cliente;
use Models\Plano;
use Services\FinanceiroWorkflowService;

class AssinaturaController extends Controller
{
    public function index()
    {
        Auth::admin();

        $assinaturaModel = new Assinatura();
        $clienteModel = new Cliente();
        $planoModel = new Plano();

        $this->view(
            'assinaturas/index',
            [
                'titulo' => 'Assinaturas',
                'assinaturas' => $assinaturaModel->listar(),
                'clientes' => $clienteModel->listar(),
                'planos' => $planoModel->listarAtivos()
            ]
        );
    }

    public function salvar()
    {
        $this->validarCsrfPost();

        Auth::admin();

        if($_SERVER['REQUEST_METHOD'] != 'POST'){
            $this->redirect('assinatura');
        }

        $dados = $this->normalizarDadosFormulario($_POST);
        $id = !empty($_POST['assinatura_id']) ? (int) $_POST['assinatura_id'] : null;
        (new FinanceiroWorkflowService())->salvarAssinaturaAdministrativa($id, $dados);
        Session::flash('success', $id ? 'Assinatura atualizada.' : 'Assinatura criada.');

        $this->redirect('assinatura');
    }

    public function cancelar()
    {
        $this->validarCsrfPost();

        Auth::admin();
        (new FinanceiroWorkflowService())->cancelarContratoPorAssinatura((int) ($_GET['id'] ?? 0));
        Session::flash('success', 'Assinatura cancelada.');
        $this->redirect('assinatura');
    }

    public function ativar()
    {
        $this->validarCsrfPost();

        Auth::admin();
        (new FinanceiroWorkflowService())->alterarStatusAssinatura((int) ($_GET['id'] ?? 0), 'ativa');
        Session::flash('success', 'Assinatura ativada.');
        $this->redirect('assinatura');
    }

    public function marcarVencida()
    {
        $this->validarCsrfPost();

        Auth::admin();
        (new FinanceiroWorkflowService())->alterarStatusAssinatura((int) ($_GET['id'] ?? 0), 'vencida');
        Session::flash('success', 'Assinatura marcada como vencida.');
        $this->redirect('assinatura');
    }

    private function normalizarDadosFormulario($post)
    {
        return [
            'cliente' => (int) ($post['cliente'] ?? 0),
            'plano' => (int) ($post['plano'] ?? 0),
            'ciclo' => $post['ciclo'] ?? 'mensal',
            'status' => $post['status'] ?? 'pendente',
            'valor' => str_replace(',', '.', str_replace('.', '', $post['valor'] ?? '0')),
            'dia_vencimento' => (int) ($post['dia_vencimento'] ?? date('d')),
            'data_inicio' => $post['data_inicio'] ?? date('Y-m-d'),
            'data_fim' => $post['data_fim'] ?: null,
            'proxima_cobranca' => $post['proxima_cobranca'] ?: null
        ];
    }
}
