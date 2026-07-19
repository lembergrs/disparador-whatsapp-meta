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
        try{
            $assinaturas = new Assinatura();
            $id ? $assinaturas->atualizar($id, $dados) : $assinaturas->criar($dados);
            Session::flash('success', $id ? 'Assinatura atualizada.' : 'Assinatura criada.');
        }catch(\Throwable $e){
            error_log('Erro ao salvar assinatura: ' . $e->getMessage());
            Session::flash('error', 'Não foi possível salvar a assinatura.');
        }

        $this->redirect('assinatura');
    }

    public function cancelar()
    {
        $this->validarCsrfPost();

        Auth::admin();
        try{
            (new FinanceiroWorkflowService())->cancelarAssinatura((int) ($_GET['id'] ?? 0));
            Session::flash('success', 'Assinatura cancelada.');
        }catch(\Throwable $e){
            error_log('Erro ao cancelar assinatura: ' . $e->getMessage());
            Session::flash('error', 'Não foi possível cancelar a assinatura.');
        }
        $this->redirect('assinatura');
    }

    public function ativar()
    {
        $this->validarCsrfPost();

        Auth::admin();
        try{
            (new Assinatura())->ativar((int) ($_GET['id'] ?? 0));
            Session::flash('success', 'Assinatura ativada.');
        }catch(\Throwable $e){
            error_log('Erro ao ativar assinatura: ' . $e->getMessage());
            Session::flash('error', 'Não foi possível ativar a assinatura.');
        }
        $this->redirect('assinatura');
    }

    public function marcarVencida()
    {
        $this->validarCsrfPost();

        Auth::admin();
        try{
            (new Assinatura())->marcarVencida((int) ($_GET['id'] ?? 0));
            Session::flash('success', 'Assinatura marcada como vencida.');
        }catch(\Throwable $e){
            error_log('Erro ao vencer assinatura: ' . $e->getMessage());
            Session::flash('error', 'Não foi possível atualizar a assinatura.');
        }
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
