<?php

namespace Controllers;

use Core\Controller;
use Core\Database;
use Core\Session;
use PDO;
use PDOException;

class SiteController extends Controller
{
    public function index()
    {
        $this->view('site/home', [
            'titulo' => 'Disparador WhatsApp'
        ], false);
    }

    public function cadastro()
    {
        $this->view('site/cadastro', [
            'titulo' => 'Cadastro'
        ], false);
    }

    public function salvar()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . rtrim(BASE_URL, '/') . '/index.php?url=site/cadastro');
            exit;
        }

        $tipoPessoa = $_POST['tipo_pessoa'] ?? 'PJ';
        $cpfCnpj = preg_replace('/\D/', '', $_POST['cpf_cnpj'] ?? '');
        $nome = trim($_POST['nome'] ?? '');
        $razaoSocial = trim($_POST['razao_social'] ?? '');
        $nomeFantasia = trim($_POST['nome_fantasia'] ?? '');
        if ($tipoPessoa === 'PF') {
            $razaoSocial = null;
            $nomeFantasia = $nome;
        }
        $email = trim($_POST['email'] ?? '');
        $telefone = preg_replace('/\D/', '', $_POST['telefone'] ?? '');
        $senha = $_POST['senha'] ?? '';
        $confirmarSenha = $_POST['confirmar_senha'] ?? '';

        if (
            empty($cpfCnpj) ||
            empty($nome) ||
            empty($email) ||
            empty($telefone) ||
            empty($senha) ||
            empty($confirmarSenha)
        ) {
            Session::flash('error', 'Preencha todos os campos obrigatórios.');
            header('Location: ' . rtrim(BASE_URL, '/') . '/index.php?url=site/cadastro');
            exit;
        }

        if ($senha !== $confirmarSenha) {
            Session::flash('error', 'As senhas informadas não conferem.');
            header('Location: ' . rtrim(BASE_URL, '/') . '/index.php?url=site/cadastro');
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Informe um e-mail válido.');
            header('Location: ' . rtrim(BASE_URL, '/') . '/index.php?url=site/cadastro');
            exit;
        }

        try {
            $db = Database::getInstance();

            $verificaEmail = $db->prepare("
                SELECT USU_ID
                FROM usuarios
                WHERE USU_Email = ?
                LIMIT 1
            ");
            $verificaEmail->execute([$email]);

            if ($verificaEmail->fetch(PDO::FETCH_ASSOC)) {
                Session::flash('error', 'Já existe uma conta cadastrada com este e-mail.');
                header('Location: ' . rtrim(BASE_URL, '/') . '/index.php?url=site/cadastro');
                exit;
            }

            $db->beginTransaction();

            $cliente = $db->prepare("
                INSERT INTO clientes (
                    CLI_TipoPessoa,
                    CLI_CPF_CNPJ,
                    CLI_Nome,
                    CLI_RazaoSocial,
                    CLI_NomeFantasia,
                    CLI_Email,
                    CLI_Telefone,
                    CLI_ValorMensalidade,
                    CLI_StatusPagamento,
                    CLI_StatusCadastro,
                    CLI_Observacoes,
                    CLI_Ativo
                ) VALUES (
                    :tipo_pessoa,
                    :cpf_cnpj,
                    :nome,
                    :razao_social,
                    :nome_fantasia,
                    :email,
                    :telefone,
                    0.00,
                    'pendente',
                    'pendente',
                    :observacoes,
                    'N'
                )
            ");

            $cliente->execute([
                ':tipo_pessoa' => $tipoPessoa,
                ':cpf_cnpj' => $cpfCnpj,
                ':nome' => $nome,
                ':razao_social' => $razaoSocial ?: null,
                ':nome_fantasia' => $nomeFantasia ?: $nome,
                ':email' => $email,
                ':telefone' => $telefone,
                ':observacoes' => 'Cadastro realizado pelo site público. Aguardando aprovação.'
            ]);

            $clienteId = $db->lastInsertId();

            $usuario = $db->prepare("
                INSERT INTO usuarios (
                    CLI_ID,
                    USU_Nome,
                    USU_Email,
                    USU_Senha,
                    USU_Nivel,
                    USU_Ativo
                ) VALUES (
                    :cliente_id,
                    :nome,
                    :email,
                    :senha,
                    'cliente',
                    'N'
                )
            ");

            $usuario->execute([
                ':cliente_id' => $clienteId,
                ':nome' => $nome,
                ':email' => $email,
                ':senha' => password_hash($senha, PASSWORD_DEFAULT)
            ]);

            $db->commit();

            Session::flash(
                'success',
                'Cadastro realizado com sucesso. Sua conta está aguardando aprovação.'
            );

            header('Location: ' . rtrim(BASE_URL, '/') . '/index.php?url=login');
            exit;

        } catch (PDOException $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }

            Session::flash('error', 'Erro ao realizar cadastro.');
            header('Location: ' . rtrim(BASE_URL, '/') . '/index.php?url=site/cadastro');
            exit;
        }
    }

    public function politicaPrivacidade()
    {
        $this->view(
            'site/politica_privacidade',
            [
                'titulo' => 'Política de Privacidade'
            ],
            false
        );
    }

    public function termosUso()
    {
        $this->view(
            'site/termos_uso',
            [
                'titulo' => 'Termos de Uso'
            ],
            false
        );
    }
}