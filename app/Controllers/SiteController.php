<?php

namespace Controllers;

use Core\Controller;
use Core\Database;
use Core\Session;
use PDO;
use PDOException;
use Models\Plano;

class SiteController extends Controller
{
    private const SENHA_MINIMA = 6;

    public function index()
    {
        $planoModel = new Plano();

        $planos = $planoModel->listarAtivos();

        $this->view('site/home', [
            'titulo' => 'Disparador.net WhatsApp',
            'planos' => $planos
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
        $dadosCadastro = [
            'tipo_pessoa' => $tipoPessoa,
            'cpf_cnpj' => $_POST['cpf_cnpj'] ?? '',
            'nome' => $nome,
            'razao_social' => $razaoSocial,
            'nome_fantasia' => $nomeFantasia,
            'email' => $email,
            'telefone' => $_POST['telefone'] ?? '',
            'aceiteTermos' => $_POST['aceiteTermos'] ?? null
        ];


        if (
            empty($cpfCnpj) ||
            empty($nome) ||
            empty($email) ||
            empty($telefone) ||
            empty($senha) ||
            empty($confirmarSenha)
        ) {
            $this->voltarCadastroComDados(
                $dadosCadastro,
                'Preencha todos os campos obrigatórios.'
            );
        }

        if (!$this->documentoValido($cpfCnpj)) {
            $this->voltarCadastroComDados(
                $dadosCadastro,
                'Informe um CPF ou CNPJ válido.'
            );
        }

        if (strlen($senha) < self::SENHA_MINIMA) {
            $this->voltarCadastroComDados(
                $dadosCadastro,
                'A senha deve ter pelo menos ' . self::SENHA_MINIMA . ' caracteres.'
            );
        }

        if ($senha !== $confirmarSenha) {
            $this->voltarCadastroComDados(
                $dadosCadastro,
                'As senhas informadas não conferem.'
            );
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->voltarCadastroComDados(
                $dadosCadastro,
                'Informe um e-mail válido.'
            );
        }


        if (defined('RECAPTCHA_SECRET_KEY') && RECAPTCHA_SECRET_KEY != '') {
            $captcha = $_POST['g-recaptcha-response'] ?? '';

            if ($captcha == '') {
                $this->voltarCadastroComDados(
                    $dadosCadastro,
                    'Confirme que você não é um robô.'
                );
            }

            $ch = curl_init();

            curl_setopt_array($ch, [
                CURLOPT_URL => 'https://www.google.com/recaptcha/api/siteverify',
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query([
                    'secret' => RECAPTCHA_SECRET_KEY,
                    'response' => $captcha,
                    'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
                ]),
                CURLOPT_RETURNTRANSFER => true
            ]);

            $retorno = curl_exec($ch);
            curl_close($ch);

            $retorno = json_decode($retorno, true);

            if (empty($retorno['success'])) {
                $this->voltarCadastroComDados(
                    $dadosCadastro,
                    'Falha na validação do reCAPTCHA. Tente novamente.'
                );
            }
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
                $this->voltarCadastroComDados(
                    $dadosCadastro,
                    'Já existe uma conta cadastrada com este e-mail.'
                );
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
                    'ativo',
                    :observacoes,
                    'S'
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
                ':observacoes' => 'Cadastro realizado pelo site público. Conta ativada automaticamente.'
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
                    'cliente_admin',
                    'S'
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
                'Cadastro realizado com sucesso. Você já pode acessar sua conta.'
            );

            header('Location: ' . rtrim(BASE_URL, '/') . '/index.php?url=login');
            exit;

        } catch (PDOException $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }

            $this->voltarCadastroComDados(
                $dadosCadastro ?? ($_POST ?? []),
                'Erro ao realizar cadastro.'
            );
        }
    }


    private function documentoValido($documento)
    {
        $documento = preg_replace('/\D/', '', (string) $documento);

        if (strlen($documento) == 11) {
            return $this->cpfValido($documento);
        }

        if (strlen($documento) == 14) {
            return $this->cnpjValido($documento);
        }

        return false;
    }

    private function cpfValido($cpf)
    {
        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $soma = 0;

            for ($i = 0; $i < $t; $i++) {
                $soma += (int) $cpf[$i] * (($t + 1) - $i);
            }

            $digito = ((10 * $soma) % 11) % 10;

            if ((int) $cpf[$t] !== $digito) {
                return false;
            }
        }

        return true;
    }

    private function cnpjValido($cnpj)
    {
        if (preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        $pesosPrimeiro = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $pesosSegundo = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        $digito1 = $this->calcularDigitoCnpj(substr($cnpj, 0, 12), $pesosPrimeiro);
        $digito2 = $this->calcularDigitoCnpj(substr($cnpj, 0, 12) . $digito1, $pesosSegundo);

        return substr($cnpj, -2) === $digito1 . $digito2;
    }

    private function calcularDigitoCnpj($base, $pesos)
    {
        $soma = 0;

        foreach ($pesos as $indice => $peso) {
            $soma += (int) $base[$indice] * $peso;
        }

        $resto = $soma % 11;

        return (string) ($resto < 2 ? 0 : 11 - $resto);
    }

    private function voltarCadastroComDados($dados, $mensagem)
    {
        unset(
            $dados['senha'],
            $dados['confirmar_senha'],
            $dados['g-recaptcha-response']
        );

        Session::set('cadastro_dados', $dados);
        Session::flash('error', $mensagem);

        header('Location: ' . rtrim(BASE_URL, '/') . '/index.php?url=site/cadastro');
        exit;
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