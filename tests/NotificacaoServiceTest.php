<?php

defined('BASE_URL') || define('BASE_URL', 'https://disparador.test');
require_once __DIR__ . '/../app/Services/CanalNotificacao.php';
require_once __DIR__ . '/../app/Services/EventoNotificacao.php';
require_once __DIR__ . '/../app/Services/EmailService.php';
require_once __DIR__ . '/../app/Services/NotificacaoService.php';

use Services\CanalNotificacao;
use Services\EmailService;
use Services\EventoNotificacao;
use Services\NotificacaoService;

class NotificacaoFakeModel
{
    public $criados = [];
    public function criar(array $dados){ $this->criados[] = $dados; return count($this->criados); }
    public function finalizar($id, array $resultado){ return true; }
}

class EmailFake extends EmailService
{
    public $enviados = [];
    public function __construct(){ }
    public function enviar($destinatario, $nome, $assunto, $html, $texto){ $this->enviados[] = compact('destinatario','nome','assunto','html','texto'); return ['sucesso'=>true,'status'=>'enviada']; }
}

$email = new EmailFake();
$model = new NotificacaoFakeModel();
$config = ['eventos' => [EventoNotificacao::BOAS_VINDAS => [CanalNotificacao::EMAIL]]];
$service = new NotificacaoService([CanalNotificacao::EMAIL => $email], $model, $config);
$result = $service->disparar(EventoNotificacao::BOAS_VINDAS, ['CLI_ID'=>1,'CLI_Nome'=>'Ana','CLI_Email'=>'ana@example.com']);

assert($result['resultados']['email']['sucesso'] === true);
assert(count($model->criados) === 1);
assert(strpos($email->enviados[0]['html'], 'Disparador.net') !== false);
assert(strpos($email->enviados[0]['html'], 'Ana') !== false);

echo "NotificacaoServiceTest OK\n";
