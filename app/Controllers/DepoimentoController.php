<?php

namespace Controllers;

use Core\Auth;
use Core\Controller;
use Core\Session;
use Models\DepoimentoCliente;

class DepoimentoController extends Controller
{
    public function index()
    {
        Auth::cliente();
        $clienteId = (int) (Auth::usuario()['CLI_ID'] ?? 0);
        $this->view('depoimentos/index', [
            'titulo' => 'Meu depoimento',
            'depoimentos' => (new DepoimentoCliente())->listarDoCliente($clienteId)
        ]);
    }

    public function enviar()
    {
        $this->validarCsrfPost();
        Auth::cliente();
        $usuario = Auth::usuario();
        $dados = [
            'nome' => $this->texto($_POST['nome_exibido'] ?? '', 120),
            'empresa' => $this->texto($_POST['empresa'] ?? '', 160),
            'cargo' => $this->texto($_POST['cargo'] ?? '', 120),
            'depoimento' => $this->texto($_POST['depoimento'] ?? '', 1000)
        ];
        if($dados['nome'] === '' || $dados['empresa'] === '' || $dados['depoimento'] === ''){
            Session::flash('error', 'Preencha nome, empresa e depoimento.');
            $this->redirect('depoimento');
        }
        if(empty($_POST['autorizacao'])){
            Session::flash('error', 'Confirme a autorização para análise e possível publicação.');
            $this->redirect('depoimento');
        }
        (new DepoimentoCliente())->criarPendente((int) ($usuario['CLI_ID'] ?? 0), $dados);
        Session::flash('success', 'Depoimento enviado. Seu depoimento será analisado antes de ser publicado.');
        $this->redirect('depoimento');
    }

    private function texto($valor, $limite)
    {
        $texto = trim(strip_tags((string) $valor));
        return function_exists('mb_substr') ? mb_substr($texto, 0, $limite, 'UTF-8') : substr($texto, 0, $limite);
    }
}
