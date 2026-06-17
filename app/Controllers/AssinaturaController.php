<?php

namespace Controllers;

use Core\Controller;
use Core\Auth;
use Core\Session;
use Models\Assinatura;
use Models\Cliente;
use Models\Plano;

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
        Auth::admin();

        if($_SERVER['REQUEST_METHOD'] != 'POST'){
            $this->redirect('assinatura');
        }

        $assinaturaModel = new Assinatura();

        $dados = $this->normalizarDadosFormulario($_POST);

        if(!empty($_POST['assinatura_id'])){
            $assinaturaModel->atualizar((int) $_POST['assinatura_id'], $dados);
            Session::flash('success', 'Assinatura atualizada.');
        }else{
            $assinaturaModel->criar($dados);
            Session::flash('success', 'Assinatura criada.');
        }

        $this->redirect('assinatura');
    }

    public function cancelar()
    {
        Auth::admin();
        (new Assinatura())->cancelar((int) ($_GET['id'] ?? 0));
        Session::flash('success', 'Assinatura cancelada.');
        $this->redirect('assinatura');
    }

    public function ativar()
    {
        Auth::admin();
        (new Assinatura())->ativar((int) ($_GET['id'] ?? 0));
        Session::flash('success', 'Assinatura ativada.');
        $this->redirect('assinatura');
    }

    public function marcarVencida()
    {
        Auth::admin();
        (new Assinatura())->marcarVencida((int) ($_GET['id'] ?? 0));
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
