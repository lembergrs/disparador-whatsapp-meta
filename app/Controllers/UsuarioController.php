<?php

namespace Controllers;

use Core\Controller;
use Core\Auth;
use Core\Session;
use Models\Usuario;
use Models\Cliente;

class UsuarioController extends Controller
{
    private $usuarioModel;

    public function __construct()
    {
        Auth::clienteAdmin();

        $this->usuarioModel = new Usuario();
    }

    public function index()
    {
        $usuarioLogado = Auth::usuario();
        $clienteId = $this->clienteIdPermitido();
        $clienteModel = new Cliente();
        $cliente = $clienteModel->buscarComPlano($clienteId);
        $usuarios = $this->usuarioModel->listarPorCliente($clienteId);
        $ativos = $this->usuarioModel->contarAtivosPorCliente($clienteId);
        $limite = (int) ($cliente['PLA_LimiteUsuarios'] ?? 0);

        $this->view('usuarios/index', [
            'titulo' => 'Usuários',
            'usuariosCliente' => $usuarios,
            'usuariosAtivos' => $ativos,
            'limiteUsuarios' => $limite,
            'limiteAtingido' => $limite > 0 && $ativos >= $limite,
            'clienteSelecionadoId' => $clienteId,
            'adminInterno' => ($usuarioLogado['nivel'] ?? null) == 'admin'
        ]);
    }

    public function salvar()
    {
        $this->validarCsrfPost();

        if($_SERVER['REQUEST_METHOD'] !== 'POST'){
            $this->redirect('usuario');
        }

        $clienteId = $this->clienteIdPermitido();
        $id = (int) ($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';

        if($nome == '' || $email == ''){
            Session::flash('error', 'Informe nome e e-mail.');
            $this->redirect('usuario');
        }

        if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
            Session::flash('error', 'E-mail inválido.');
            $this->redirect('usuario');
        }

        if($this->usuarioModel->emailExiste($email, $id ?: null)){
            Session::flash('error', 'Já existe um usuário com este e-mail.');
            $this->redirect('usuario');
        }

        if($id){
            if(!$this->usuarioModel->buscarPorCliente($id, $clienteId)){
                Session::flash('error', 'Usuário não encontrado.');
                $this->redirect('usuario');
            }

            $this->usuarioModel->atualizar($id, $clienteId, $nome, $email);
            Session::flash('success', 'Usuário atualizado.');
            $this->redirect('usuario');
        }

        if(strlen($senha) < 6){
            Session::flash('error', 'Informe uma senha com pelo menos 6 caracteres.');
            $this->redirect('usuario');
        }

        $clienteModel = new Cliente();
        $cliente = $clienteModel->buscarComPlano($clienteId);
        $limite = (int) ($cliente['PLA_LimiteUsuarios'] ?? 0);
        $ativos = $this->usuarioModel->contarAtivosPorCliente($clienteId);

        if($limite > 0 && $ativos >= $limite){
            Session::flash('error', 'Você atingiu o limite de usuários do seu plano. Faça upgrade para adicionar mais usuários.');
            $this->redirect('usuario');
        }

        $this->usuarioModel->criarClienteUsuario($clienteId, $nome, $email, $senha);
        Session::flash('success', 'Usuário criado.');
        $this->redirect('usuario');
    }

    public function senha()
    {
        $this->validarCsrfPost();

        if($_SERVER['REQUEST_METHOD'] !== 'POST'){
            $this->redirect('usuario');
        }

        $clienteId = $this->clienteIdPermitido();
        $id = (int) ($_POST['id'] ?? 0);
        $senha = $_POST['senha'] ?? '';

        if(strlen($senha) < 6){
            Session::flash('error', 'Informe uma senha com pelo menos 6 caracteres.');
            $this->redirect('usuario');
        }

        if(!$this->usuarioModel->buscarPorCliente($id, $clienteId)){
            Session::flash('error', 'Usuário não encontrado.');
            $this->redirect('usuario');
        }

        $this->usuarioModel->alterarSenha($id, $clienteId, $senha);
        Session::flash('success', 'Senha alterada.');
        $this->redirect('usuario');
    }

    public function inativar()
    {
        $this->validarCsrfPost();

        $this->alterarStatus('N', 'Usuário inativado.');
    }

    public function ativar()
    {
        $this->validarCsrfPost();

        $clienteId = $this->clienteIdPermitido();
        $id = (int) ($_GET['id'] ?? 0);
        $clienteModel = new Cliente();
        $cliente = $clienteModel->buscarComPlano($clienteId);
        $limite = (int) ($cliente['PLA_LimiteUsuarios'] ?? 0);
        $ativos = $this->usuarioModel->contarAtivosPorCliente($clienteId);

        if($limite > 0 && $ativos >= $limite){
            Session::flash('error', 'Você atingiu o limite de usuários do seu plano. Faça upgrade para adicionar mais usuários.');
            $this->redirect('usuario');
        }

        if(!$this->usuarioModel->buscarPorCliente($id, $clienteId)){
            Session::flash('error', 'Usuário não encontrado.');
            $this->redirect('usuario');
        }

        $this->usuarioModel->atualizarStatus($id, $clienteId, 'S');
        Session::flash('success', 'Usuário ativado.');
        $this->redirect('usuario');
    }

    private function alterarStatus($ativo, $mensagem)
    {
        $clienteId = $this->clienteIdPermitido();
        $id = (int) ($_GET['id'] ?? 0);

        if($id == (int) (Auth::usuario()['id'] ?? 0)){
            Session::flash('error', 'Você não pode inativar seu próprio usuário.');
            $this->redirect('usuario');
        }

        if(!$this->usuarioModel->buscarPorCliente($id, $clienteId)){
            Session::flash('error', 'Usuário não encontrado.');
            $this->redirect('usuario');
        }

        $this->usuarioModel->atualizarStatus($id, $clienteId, $ativo);
        Session::flash('success', $mensagem);
        $this->redirect('usuario');
    }

    private function clienteIdPermitido()
    {
        $usuario = Auth::usuario();

        if(($usuario['nivel'] ?? null) == 'admin'){
            return (int) ($_GET['cliente'] ?? $_POST['cliente_id'] ?? 0);
        }

        return (int) $usuario['CLI_ID'];
    }
}
