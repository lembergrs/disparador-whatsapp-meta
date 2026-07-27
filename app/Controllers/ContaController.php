<?php

namespace Controllers;

use Core\Auth;
use Core\Controller;
use Core\Session;
use Models\Cliente;
use Models\Usuario;
use Services\SenhaForteValidator;

class ContaController extends Controller
{
    public function index()
    {
        Auth::cliente();

        $usuario = Auth::usuario();
        $clienteModel = new Cliente();
        $cliente = $clienteModel->buscar((int) ($usuario['CLI_ID'] ?? 0));

        if(!$cliente){
            Session::flash('error', 'Não foi possível carregar os dados da conta.');
            $this->redirect('dashboard');
        }

        $colunasEndereco = $clienteModel->colunasExistem($this->camposEndereco());
        $possuiEndereco = !in_array(false, $colunasEndereco, true);

        $this->view('conta/index', [
            'titulo' => 'Minha Conta',
            'cliente' => $cliente,
            'possuiEndereco' => $possuiEndereco
        ]);
    }

    public function atualizarDados()
    {
        $this->validarCsrfPost();
        Auth::cliente();

        $usuario = Auth::usuario();
        $clienteId = (int) ($usuario['CLI_ID'] ?? 0);

        $nome = trim((string) ($_POST['nome'] ?? ''));
        $telefone = preg_replace('/\D/', '', (string) ($_POST['telefone'] ?? ''));
        $nomeFantasia = trim((string) ($_POST['nome_fantasia'] ?? ''));

        if($clienteId <= 0 || $nome === '' || $telefone === ''){
            Session::flash('error', 'Informe nome de contato e telefone.');
            $this->redirect('conta');
        }

        $clienteModel = new Cliente();
        $dadosAtualizacao = [
            'nome' => $nome,
            'telefone' => $telefone,
            'nome_fantasia' => $nomeFantasia !== '' ? $nomeFantasia : null
        ];

        foreach(['cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf'] as $campoEndereco){
            if(array_key_exists($campoEndereco, $_POST)){
                $dadosAtualizacao[$campoEndereco] = $_POST[$campoEndereco];
            }
        }

        $clienteModel->atualizarDadosConta($clienteId, $dadosAtualizacao);

        $this->registrarAuditoria('dados_cadastrais_atualizados', $clienteId, (int) ($usuario['id'] ?? 0));

        Session::flash('success', 'Dados cadastrais atualizados com sucesso.');
        $this->redirect('conta');
    }

    public function alterarSenha()
    {
        $this->validarCsrfPost();
        Auth::cliente();
        Auth::bloquearAcaoSensivelEmImpersonacao();

        $usuarioSessao = Auth::usuario();
        $usuarioId = (int) ($usuarioSessao['id'] ?? 0);
        $clienteId = (int) ($usuarioSessao['CLI_ID'] ?? 0);

        $senhaAtual = (string) ($_POST['senha_atual'] ?? '');
        $novaSenha = (string) ($_POST['nova_senha'] ?? '');
        $confirmarSenha = (string) ($_POST['confirmar_senha'] ?? '');

        if($usuarioId <= 0 || $clienteId <= 0){
            Session::flash('error', 'Usuário não identificado.');
            $this->redirect('conta#seguranca');
        }

        if(!SenhaForteValidator::forte($novaSenha)){
            Session::flash('error', SenhaForteValidator::mensagem());
            $this->redirect('conta#seguranca');
        }

        if($novaSenha !== $confirmarSenha){
            Session::flash('error', 'As senhas informadas não conferem.');
            $this->redirect('conta#seguranca');
        }

        $usuarioModel = new Usuario();
        $usuario = $usuarioModel->buscar($usuarioId);

        if(
            !$usuario
            || (int) ($usuario['CLI_ID'] ?? 0) !== $clienteId
            || !password_verify($senhaAtual, (string) ($usuario['USU_Senha'] ?? ''))
        ){
            Session::flash('error', 'Senha atual inválida.');
            $this->redirect('conta#seguranca');
        }

        $usuarioModel->alterarSenhaAutenticado($usuarioId, $clienteId, $novaSenha);
        $this->registrarAuditoria('senha_alterada', $clienteId, $usuarioId);

        Session::flash('success', 'Senha alterada com sucesso.');
        $this->redirect('conta#seguranca');
    }

    private function camposEndereco()
    {
        return [
            'CLI_CEP',
            'CLI_Logradouro',
            'CLI_Numero',
            'CLI_Complemento',
            'CLI_Bairro',
            'CLI_Cidade',
            'CLI_UF'
        ];
    }

    private function registrarAuditoria($acao, $clienteId, $usuarioId)
    {
        $diretorioLog = dirname(__DIR__, 2) . '/storage/logs';

        if(!is_dir($diretorioLog)){
            mkdir($diretorioLog, 0770, true);
        }

        $linha = sprintf(
            "[%s] acao=%s cliente_id=%d usuario_id=%d ip=%s%s",
            date('Y-m-d H:i:s'),
            $acao,
            $clienteId,
            $usuarioId,
            preg_replace('/[\r\n\t]+/', ' ', (string) ($_SERVER['REMOTE_ADDR'] ?? '')),
            PHP_EOL
        );

        error_log($linha, 3, $diretorioLog . '/auditoria-conta.log');
    }
}
