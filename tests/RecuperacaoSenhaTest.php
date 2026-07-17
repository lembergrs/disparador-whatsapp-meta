<?php
if(!defined('BASE_URL')) define('BASE_URL', 'https://app.disparador.test');
if(!defined('MAIL_HOST')) define('MAIL_HOST', 'smtp.example.test');
if(!defined('MAIL_FROM_ADDRESS')) define('MAIL_FROM_ADDRESS', 'no-reply@disparador.test');
if(!defined('MAIL_PORT')) define('MAIL_PORT', 587);
if(!defined('MAIL_USERNAME')) define('MAIL_USERNAME', '');
if(!defined('MAIL_PASSWORD')) define('MAIL_PASSWORD', '');
if(!defined('MAIL_ENCRYPTION')) define('MAIL_ENCRYPTION', 'tls');
if(!defined('MAIL_FROM_NAME')) define('MAIL_FROM_NAME', 'Disparador.net');
if(!defined('MAIL_REPLY_TO_ADDRESS')) define('MAIL_REPLY_TO_ADDRESS', 'suporte@disparador.test');
if(!defined('MAIL_REPLY_TO_NAME')) define('MAIL_REPLY_TO_NAME', 'Suporte Disparador.net');
if(!defined('MAIL_TIMEOUT')) define('MAIL_TIMEOUT', 10);

require_once __DIR__ . '/../app/Services/SenhaForteValidator.php';
require_once __DIR__ . '/../app/Models/NotificacaoTransacional.php';
require_once __DIR__ . '/../app/Models/RecuperacaoSenha.php';
require_once __DIR__ . '/../app/Services/Email/EmailTransacionalService.php';
require_once __DIR__ . '/../app/Services/Email/EmailRecuperacaoSenhaService.php';
require_once __DIR__ . '/../app/Services/Email/EmailBoasVindasService.php';
require_once __DIR__ . '/../app/Services/RecuperacaoSenhaService.php';

use Models\RecuperacaoSenha;
use Models\NotificacaoTransacional;
use Services\RecuperacaoSenhaService;
use Services\Email\EmailRecuperacaoSenhaService;
use Services\Email\EmailTransacionalService;

function recSenhaAssert($cond, $msg){ if(!$cond){ fwrite(STDERR, "FAIL: {$msg}\n"); exit(1); } }

class FakeDbRecuperacao { public $tx = 0; public function beginTransaction(){ $this->tx++; } public function commit(){ $this->tx--; } public function rollBack(){ $this->tx = 0; } public function inTransaction(){ return $this->tx > 0; } }
class FakeRecuperacaoSenhaModel extends RecuperacaoSenha {
    public $usuario; public $tokens = []; public $recentesIp = 0; public $recentesUsuario = 0; public $senhaHash; public function __construct(){}
    public function buscarUsuarioRecuperavelPorEmail($email){ return $this->usuario && $email === $this->usuario['USU_Email'] ? $this->usuario : false; }
    public function contarRecentesPorIp($ip, $minutos = 5){ return $this->recentesIp; }
    public function contarRecentesPorUsuario($usuarioId, $minutos = 3){ return $this->recentesUsuario; }
    public function invalidarPendentesUsuario($usuarioId){ foreach($this->tokens as &$t){ if($t['RSE_USU_ID']==$usuarioId && empty($t['RSE_UtilizadoEm'])) $t['RSE_InvalidadoEm']='agora'; } return true; }
    public function criar($usuarioId, $tokenHash, $expiraEm, $ip = null, $userAgent = null){ $id=count($this->tokens)+1; $this->tokens[$id]=['RSE_ID'=>$id,'RSE_USU_ID'=>$usuarioId,'RSE_TokenHash'=>$tokenHash,'RSE_ExpiraEm'=>$expiraEm,'RSE_UtilizadoEm'=>null,'RSE_InvalidadoEm'=>null,'RSE_IP'=>$ip,'USU_ID'=>$this->usuario['USU_ID'],'CLI_ID'=>$this->usuario['CLI_ID'],'USU_Nome'=>$this->usuario['USU_Nome'],'USU_Email'=>$this->usuario['USU_Email'],'USU_Ativo'=>'S']; return $id; }
    public function buscarPorHash($tokenHash, $bloquear = false){ foreach($this->tokens as $t){ if($t['RSE_TokenHash']===$tokenHash) return $t; } return false; }
    public function atualizarSenhaUsuario($usuarioId, $senhaHash){ $this->senhaHash=$senhaHash; return true; }
    public function marcarUtilizado($recuperacaoId){ if(!empty($this->tokens[$recuperacaoId]) && empty($this->tokens[$recuperacaoId]['RSE_UtilizadoEm']) && empty($this->tokens[$recuperacaoId]['RSE_InvalidadoEm'])){ $this->tokens[$recuperacaoId]['RSE_UtilizadoEm']='agora'; return true; } return false; }
    public function invalidarOutrosPendentes($usuarioId, $excetoId){ foreach($this->tokens as &$t){ if($t['RSE_USU_ID']==$usuarioId && $t['RSE_ID']!=$excetoId && empty($t['RSE_UtilizadoEm'])) $t['RSE_InvalidadoEm']='agora'; } return true; }
}
class FakeEmailRecSenha extends EmailRecuperacaoSenhaService { public $chamadas=0; public $ultimoLink=''; public function __construct(){} public function enviar(array $usuario, $link, $solicitacaoId){ $this->chamadas++; $this->ultimoLink=$link; return ['sucesso'=>true,'status'=>'enviado']; } }

$model = new FakeRecuperacaoSenhaModel();
$model->usuario = ['USU_ID'=>5,'CLI_ID'=>9,'USU_Nome'=>'Maria','USU_Email'=>'maria@example.test','USU_Ativo'=>'S','USU_Senha'=>password_hash('SenhaAntiga!1', PASSWORD_DEFAULT)];
$email = new FakeEmailRecSenha();
$db = new FakeDbRecuperacao();
$service = new RecuperacaoSenhaService($model, $email, $db);
$res = $service->solicitar('maria@example.test', '127.0.0.1', 'Teste');
recSenhaAssert($res['mensagem_publica'] === RecuperacaoSenhaService::mensagemPublicaSolicitacao(), 'resposta pública neutra em e-mail existente');
recSenhaAssert($email->chamadas === 1, 'e-mail existente tenta envio');
recSenhaAssert(count($model->tokens) === 1, 'solicitação cria token');
$tokenPuro = preg_replace('/^.*token=/', '', $email->ultimoLink);
recSenhaAssert(strlen($tokenPuro) === 64 && ctype_xdigit($tokenPuro), 'token puro seguro vai apenas no link do e-mail');
recSenhaAssert($model->tokens[1]['RSE_TokenHash'] === hash('sha256', $tokenPuro), 'somente hash salvo no banco fake');
recSenhaAssert(strpos(json_encode($model->tokens), $tokenPuro) === false, 'token puro não é salvo');
$validacao = $service->validarToken($tokenPuro);
recSenhaAssert($validacao['valido'] === true, 'token válido aceito');
$reset = $service->redefinir($tokenPuro, 'NovaSenha!123', 'NovaSenha!123');
recSenhaAssert($reset['sucesso'] === true && password_verify('NovaSenha!123', $model->senhaHash), 'senha nova atualiza hash com password_hash');
recSenhaAssert(!password_verify('SenhaAntiga!1', $model->senhaHash), 'senha antiga rejeitada após troca');
$reuso = $service->redefinir($tokenPuro, 'OutraSenha!123', 'OutraSenha!123');
recSenhaAssert($reuso['sucesso'] === false, 'reutilização do token rejeitada');

$emailAntes = $email->chamadas;
$resInexistente = $service->solicitar('ninguem@example.test', '127.0.0.2', 'Teste');
recSenhaAssert($resInexistente['mensagem_publica'] === $res['mensagem_publica'] && $email->chamadas === $emailAntes, 'e-mail inexistente mantém resposta pública e não envia');
$resInvalido = $service->solicitar('email-invalido', '127.0.0.3', 'Teste');
recSenhaAssert($resInvalido['mensagem_publica'] === $res['mensagem_publica'] && $email->chamadas === $emailAntes, 'e-mail inválido mantém resposta pública e não envia');
$model->recentesIp = 5;
$resRate = $service->solicitar('maria@example.test', '127.0.0.1', 'Teste');
recSenhaAssert($resRate['mensagem_publica'] === $res['mensagem_publica'] && $email->chamadas === $emailAntes, 'rate limit por IP mantém resposta pública');
$model->recentesIp = 0;
$model->recentesUsuario = 1;
$resRateEmail = $service->solicitar('maria@example.test', '127.0.0.4', 'Teste');
recSenhaAssert($resRateEmail['mensagem_publica'] === $res['mensagem_publica'] && $email->chamadas === $emailAntes, 'rate limit por e-mail mantém resposta pública');

class FakeNotifRecSenha extends NotificacaoTransacional { public $resultado; public function __construct(){} public function criarPendenteIdempotente(array $d){ return ['NOT_ID'=>1,'NOT_Tentativas'=>0]; } public function marcarProcessando($id){ return true; } public function marcarResultado($id, array $r){ $this->resultado=$r; return true; } }
class FakeEmailTransRec extends EmailTransacionalService { public $msg; public function __construct(){} public function enviar(array $m){ $this->msg=$m; return ['sucesso'=>true,'status'=>'enviado']; } }
$notif = new FakeNotifRecSenha(); $trans = new FakeEmailTransRec(); $emailSvc = new EmailRecuperacaoSenhaService($notif, $trans);
$emailSvc->enviar(['USU_ID'=>1,'CLI_ID'=>2,'USU_Nome'=>'João <X>','USU_Email'=>'joao@example.test'], 'https://app.disparador.test/index.php?url=login/redefinirSenha&token=abc123', 44);
recSenhaAssert($trans->msg['assunto'] === 'Recuperação de senha - Disparador.net', 'assunto correto');
recSenhaAssert(strpos($trans->msg['html'], 'Clique no botão abaixo e siga as orientações para redefinir sua senha.') !== false, 'texto HTML correto sem sigas');
recSenhaAssert(strpos($trans->msg['html'], 'sigas') === false, 'não usa sigas');
recSenhaAssert(strpos($trans->msg['texto'], 'https://app.disparador.test/index.php?url=login/redefinirSenha&token=abc123') !== false, 'texto contém link com token puro somente no e-mail');

class_alias('Services\\Email\\EmailTransacionalService', 'TmpEmailTransacionalAliasCheck');
recSenhaAssert(class_exists('Services\\Email\\EmailTransacionalService') && class_exists('Services\\Email\\EmailBoasVindasService'), 'classes de e-mail existem no namespace Services\\Email');

echo "Recuperação de senha tests passed\n";
