<?php

namespace Controllers;

use Core\Controller;
use Core\Database;
use Core\Session;
use PDO;
use PDOException;
use Models\Plano;
use Models\Cliente;
use Models\ConfiguracaoSite;
use Services\DocumentoFiscalValidator;
use Services\SenhaForteValidator;
use Services\EventoNotificacao;
use Services\NotificacaoService;
use Services\AnalyticsService;
use Services\Indicacao\IndicacaoWorkflowService;

class SiteController extends Controller
{
    private const SESSAO_CODIGO_INDICACAO = 'cadastro_codigo_indicacao';

    private function dadosWhatsappSite()
    {
        return (new ConfiguracaoSite())->obterConfiguracaoWhatsappSite();
    }

    public function index()
    {
        $planoModel = new Plano();

        $planos = $planoModel->listarAtivos();

        $this->view('site/home', [
            'titulo' => 'Disparador.net WhatsApp',
            'planos' => $planos,
            'whatsappSite' => $this->dadosWhatsappSite()
        ], false);
    }

    public function cadastro()
    {
        $this->capturarCodigoIndicacaoDaUrl();

        $this->view('site/cadastro', [
            'titulo' => 'Cadastro',
            'origensCadastro' => Cliente::ORIGENS_CADASTRO,
            'codigoIndicacao'=>$this->codigoIndicacaoEmCadastro(),
            'whatsappSite' => $this->dadosWhatsappSite()
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
        $origemCadastro = $_POST['origem_cadastro'] ?? '';
        $origemCadastroOutro = $_POST['origem_cadastro_outro'] ?? '';
        $codigoIndicacaoEnviado = trim((string) ($_POST['codigo_indicacao'] ?? ''));
        $dadosCadastro = [
            'tipo_pessoa' => $tipoPessoa,
            'cpf_cnpj' => $_POST['cpf_cnpj'] ?? '',
            'nome' => $nome,
            'razao_social' => $razaoSocial,
            'nome_fantasia' => $nomeFantasia,
            'email' => $email,
            'telefone' => $_POST['telefone'] ?? '',
            'origem_cadastro' => $origemCadastro,
            'origem_cadastro_outro' => $origemCadastroOutro,
            'codigo_indicacao'=>$codigoIndicacaoEnviado,
            'aceiteTermos' => $_POST['aceiteTermos'] ?? null
        ];
        $codigoIndicacaoSessao = $this->codigoIndicacaoEmCadastro();
        $codigoIndicacao = $this->validarCodigoIndicacaoEnviado($codigoIndicacaoEnviado, $dadosCadastro);
        $origemIndicacao = $codigoIndicacao !== null && $codigoIndicacao === $codigoIndicacaoSessao ? 'link' : 'manual';

        try{
            $origemValidada = Cliente::validarOrigemCadastro(
                $origemCadastro,
                $origemCadastroOutro
            );
        }catch(\InvalidArgumentException $e){
            $this->voltarCadastroComDados($dadosCadastro, $e->getMessage());
        }


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

        if (!DocumentoFiscalValidator::valido($cpfCnpj)) {
            $this->voltarCadastroComDados(
                $dadosCadastro,
                'Informe um CPF ou CNPJ válido.'
            );
        }

        if (!SenhaForteValidator::forte($senha)) {
            $this->voltarCadastroComDados(
                $dadosCadastro,
                SenhaForteValidator::mensagem()
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

        if (empty($_POST['aceiteTermos'])) {
            $this->voltarCadastroComDados(
                $dadosCadastro,
                'Você precisa aceitar os Termos de Uso, a Política de Privacidade e a Política de Cancelamento e Reembolso.'
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
                    CLI_OrigemCadastro,
                    CLI_OrigemCadastroOutro,
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
                    :origem_cadastro,
                    :origem_cadastro_outro,
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
                ':observacoes' => 'Cadastro realizado pelo site público. Conta ativada automaticamente.',
                ':origem_cadastro' => $origemValidada['origem'],
                ':origem_cadastro_outro' => $origemValidada['outro']
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

            $usuarioId = $db->lastInsertId();

            if($codigoIndicacao !== null){
                (new IndicacaoWorkflowService())->registrarIndicacao(
                    (int) $clienteId,
                    $codigoIndicacao,
                    $origemIndicacao
                );
            }

            $db->commit();

            Session::remove(self::SESSAO_CODIGO_INDICACAO);

            AnalyticsService::registrar('sign_up', ['method'=>'public_form', 'account_type'=>'client']);

            $resultadoBoasVindas = $this->enviarEmailBoasVindasCadastro([
                'CLI_ID' => $clienteId,
                'CLI_Nome' => $nome,
                'CLI_RazaoSocial' => $razaoSocial,
                'CLI_NomeFantasia' => $nomeFantasia ?: $nome,
                'CLI_Email' => $email,
                'CLI_Telefone' => $telefone
            ], [
                'USU_ID' => $usuarioId,
                'USU_Nome' => $nome,
                'USU_Email' => $email
            ]);

            Session::flash(
                'success',
                !empty($resultadoBoasVindas['sucesso'])
                    ? 'Cadastro realizado com sucesso. Enviamos para seu e-mail os próximos passos para conectar seu WhatsApp e começar a utilizar o Disparador.net.'
                    : 'Cadastro realizado com sucesso. Você já pode acessar sua conta.'
            );

            header('Location: ' . rtrim(BASE_URL, '/') . '/index.php?url=login');
            exit;

        } catch (PDOException|\DomainException $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }

            $this->voltarCadastroComDados(
                $dadosCadastro ?? ($_POST ?? []),
                $e instanceof \DomainException
                    ? 'Código de indicação inválido ou indisponível.'
                    : 'Erro ao realizar cadastro.'
            );
        }
    }


    private function enviarEmailBoasVindasCadastro(array $cliente, array $usuario)
    {
        try{
            return (new NotificacaoService())->disparar(EventoNotificacao::BOAS_VINDAS, array_merge($cliente, $usuario));
        }catch(\Throwable $e){
            $logDir = dirname(__DIR__, 2) . '/storage/logs';
            if(!is_dir($logDir)){
                mkdir($logDir, 0770, true);
            }

            error_log(json_encode([
                'timestamp' => date('c'),
                'tipo' => EventoNotificacao::BOAS_VINDAS,
                'CLI_ID' => (int) ($cliente['CLI_ID'] ?? 0),
                'USU_ID' => (int) ($usuario['USU_ID'] ?? 0),
                'status' => 'erro_temporario',
                'codigo' => 'falha_controlada_service'
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, 3, $logDir . '/email-transacional.log');

            return ['sucesso' => false, 'status' => 'erro_temporario', 'error_code' => 'falha_controlada_service'];
        }
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

    private function capturarCodigoIndicacaoDaUrl(): void
    {
        if(!array_key_exists('ref', $_GET)){
            return;
        }

        try{
            $validacao = (new IndicacaoWorkflowService())->validarCodigo($_GET['ref']);
            Session::set(
                self::SESSAO_CODIGO_INDICACAO,
                (string) $validacao['codigo']['ICD_CodigoNormalizado']
            );
        }catch(\DomainException $e){
            Session::remove(self::SESSAO_CODIGO_INDICACAO);
            Session::flash('error', 'Código de indicação inválido ou indisponível.');
        }
    }

    private function validarCodigoIndicacaoEnviado(string $codigo, array $dadosCadastro): ?string
    {
        if($codigo === ''){
            Session::remove(self::SESSAO_CODIGO_INDICACAO);
            return null;
        }

        try{
            $validacao = (new IndicacaoWorkflowService())->validarCodigo($codigo);
            $normalizado = (string) $validacao['codigo']['ICD_CodigoNormalizado'];
            Session::set(self::SESSAO_CODIGO_INDICACAO, $normalizado);
            return $normalizado;
        }catch(\DomainException $e){
            $this->voltarCadastroComDados(
                $dadosCadastro,
                'Código de indicação inválido ou indisponível.'
            );
        }
    }

    private function codigoIndicacaoEmCadastro(): ?string
    {
        $codigo = Session::get(self::SESSAO_CODIGO_INDICACAO);
        return is_string($codigo) && $codigo !== '' ? $codigo : null;
    }

    public function politicaPrivacidade()
    {
        $this->view(
            'site/politica_privacidade',
            [
                'titulo' => 'Política de Privacidade',
                'whatsappSite' => $this->dadosWhatsappSite()
            ],
            false
        );
    }

    public function termosUso()
    {
        $this->view(
            'site/termos_uso',
            [
                'titulo' => 'Termos de Uso',
                'whatsappSite' => $this->dadosWhatsappSite()
            ],
            false
        );
    }

    public function politicaCancelamento()
    {
        $this->view(
            'site/politica_cancelamento',
            [
                'titulo' => 'Política de Cancelamento e Reembolso',
                'whatsappSite' => $this->dadosWhatsappSite()
            ],
            false
        );
    }
}
