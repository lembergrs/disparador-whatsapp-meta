<?php

if(!defined('BASE_URL')) define('BASE_URL', 'https://app.disparador.test');
if(!defined('MAIL_HOST')) define('MAIL_HOST', 'smtp.example.test');
if(!defined('MAIL_PORT')) define('MAIL_PORT', 587);
if(!defined('MAIL_USERNAME')) define('MAIL_USERNAME', 'usuario_smtp');
if(!defined('MAIL_PASSWORD')) define('MAIL_PASSWORD', 'senha_smtp_teste');
if(!defined('MAIL_ENCRYPTION')) define('MAIL_ENCRYPTION', 'tls');
if(!defined('MAIL_FROM_ADDRESS')) define('MAIL_FROM_ADDRESS', 'no-reply@disparador.test');
if(!defined('MAIL_FROM_NAME')) define('MAIL_FROM_NAME', 'Disparador.net');
if(!defined('MAIL_REPLY_TO_ADDRESS')) define('MAIL_REPLY_TO_ADDRESS', 'suporte@disparador.test');
if(!defined('MAIL_REPLY_TO_NAME')) define('MAIL_REPLY_TO_NAME', 'Suporte Disparador.net');
if(!defined('MAIL_TIMEOUT')) define('MAIL_TIMEOUT', 10);

require_once __DIR__ . '/../app/Models/NotificacaoTransacional.php';
require_once __DIR__ . '/../app/Services/EmailTransacionalService.php';
require_once __DIR__ . '/../app/Services/EmailBoasVindasService.php';

use Models\NotificacaoTransacional;
use Services\EmailBoasVindasService;
use Services\EmailTransacionalService;

function emailBoasVindasAssert($cond, $msg){ if(!$cond){ fwrite(STDERR, "FAIL: {$msg}\n"); exit(1); } }

class FakeNotificacaoBoasVindas extends NotificacaoTransacional
{
    public $rows = [];
    public $processadas = 0;
    public $resultados = [];
    public function __construct(){}
    public function criarPendenteIdempotente(array $dados)
    {
        $chave = $dados['chave_idempotencia'];
        if(!isset($this->rows[$chave])){
            $this->rows[$chave] = [
                'NOT_ID' => count($this->rows) + 1,
                'CLI_ID' => $dados['cliente_id'],
                'USU_ID' => $dados['usuario_id'],
                'NOT_Tipo' => $dados['tipo'],
                'NOT_Canal' => $dados['canal'],
                'NOT_Destinatario' => $dados['destinatario'],
                'NOT_Assunto' => $dados['assunto'],
                'NOT_Status' => self::STATUS_PENDENTE,
                'NOT_Tentativas' => 0,
                'NOT_ChaveIdempotencia' => $chave
            ];
        }
        return $this->rows[$chave];
    }
    public function marcarProcessando($id)
    {
        foreach($this->rows as &$row){
            if((int) $row['NOT_ID'] === (int) $id && $row['NOT_Status'] === self::STATUS_PENDENTE){
                $row['NOT_Status'] = self::STATUS_PROCESSANDO;
                $row['NOT_Tentativas']++;
                $this->processadas++;
                return true;
            }
        }
        return false;
    }
    public function marcarResultado($id, array $resultado)
    {
        $this->resultados[] = $resultado;
        foreach($this->rows as &$row){
            if((int) $row['NOT_ID'] === (int) $id){
                $row['NOT_Status'] = !empty($resultado['sucesso']) ? self::STATUS_ENVIADO : $resultado['status'];
                return true;
            }
        }
        return false;
    }
}

class FakeEmailTransacional extends EmailTransacionalService
{
    public $chamadas = 0;
    public $ultimaMensagem;
    public $resultado;
    public function __construct($resultado = null){ $this->resultado = $resultado ?: ['sucesso' => true, 'status' => 'enviado']; }
    public function enviar(array $mensagem){ $this->chamadas++; $this->ultimaMensagem = $mensagem; return $this->resultado; }
}

$repo = new FakeNotificacaoBoasVindas();
$email = new FakeEmailTransacional();
$service = new EmailBoasVindasService($repo, $email);
$cliente = ['CLI_ID' => 77, 'CLI_Nome' => '<Cliente & Teste>', 'CLI_Email' => 'cliente@example.test', 'CLI_NomeFantasia' => 'Fantasia'];
$usuario = ['USU_ID' => 88, 'USU_Nome' => '<Cliente & Teste>', 'USU_Email' => 'cliente@example.test'];
$res = $service->enviarParaCadastro($cliente, $usuario);

emailBoasVindasAssert($res['sucesso'] === true, 'sucesso registra enviado');
emailBoasVindasAssert($email->chamadas === 1 && $repo->processadas === 1, 'cadastro concluído chama EmailBoasVindasService uma vez');
emailBoasVindasAssert($email->ultimaMensagem['assunto'] === 'Bem-vindo ao Disparador.net — veja os próximos passos', 'assunto correto');
emailBoasVindasAssert(strpos($email->ultimaMensagem['html'], 'Próximos passos') !== false, 'HTML contém próximos passos');
emailBoasVindasAssert(strpos($email->ultimaMensagem['texto'], 'PRÓXIMOS PASSOS') !== false, 'AltBody existe');
emailBoasVindasAssert(strpos($email->ultimaMensagem['texto'], 'período de avaliação começa somente após a conexão') !== false, 'trial descrito após conexão');
emailBoasVindasAssert(strpos($email->ultimaMensagem['html'], '&lt;Cliente &amp; Teste&gt;') !== false, 'HTML escapa nome do cliente');
emailBoasVindasAssert(strpos($email->ultimaMensagem['html'], '<Cliente & Teste>') === false, 'HTML não contém nome bruto');
emailBoasVindasAssert(strpos($email->ultimaMensagem['html'], 'senha_smtp_teste') === false && strpos($email->ultimaMensagem['texto'], 'senha_smtp_teste') === false, 'credenciais não aparecem no corpo');
emailBoasVindasAssert(strpos($email->ultimaMensagem['html'], 'https://app.disparador.test/index.php?url=login') !== false, 'URL usa BASE_URL');
emailBoasVindasAssert($service->chaveIdempotencia(77) === 'email:boas_vindas:cliente:77', 'chave idempotente é única por cliente/tipo');

$resDup = $service->enviarParaCadastro($cliente, $usuario);
emailBoasVindasAssert($email->chamadas === 1 && $resDup['error_code'] === 'envio_ja_registrado', 'duplo POST não envia dois e-mails');

$repoFalha = new FakeNotificacaoBoasVindas();
$emailFalha = new FakeEmailTransacional(['sucesso' => false, 'status' => 'erro_temporario', 'error_code' => 'smtp_temporariamente_indisponivel', 'mensagem' => 'timeout']);
$serviceFalha = new EmailBoasVindasService($repoFalha, $emailFalha);
$resFalha = $serviceFalha->enviarParaCadastro(['CLI_ID' => 78, 'CLI_Nome' => 'Falha', 'CLI_Email' => 'falha@example.test'], ['USU_ID' => 89, 'USU_Nome' => 'Falha', 'USU_Email' => 'falha@example.test']);
emailBoasVindasAssert($resFalha['status'] === 'erro_temporario' && $repoFalha->resultados[0]['status'] === 'erro_temporario', 'falha registra erro temporário');

class FakeMailerTransacional
{
    public $sent = false;
    public function isSMTP(){}
    public function isHTML($flag){}
    public function setFrom($a, $n = ''){}
    public function addReplyTo($a, $n = ''){}
    public function addAddress($a, $n = ''){}
    public function send(){ $this->sent = true; return true; }
}

$transacionalInvalido = new EmailTransacionalService(function(){ return new FakeMailerTransacional(); });
$resInvalido = $transacionalInvalido->enviar(['destinatario' => 'email-invalido', 'nome_destinatario' => 'X', 'assunto' => 'A', 'html' => '<p>x</p>', 'texto' => 'x']);
emailBoasVindasAssert($resInvalido['status'] === 'erro_definitivo' && $resInvalido['error_code'] === 'destinatario_invalido', 'destinatário inválido não chama SMTP');

echo "Email boas-vindas tests passed\n";
