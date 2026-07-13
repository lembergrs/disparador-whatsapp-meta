<?php

$assert = function($condition, $message){
    if(!$condition){
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

class FakeEmbeddedSignupFrontendCoordinator
{
    public $code = null;
    public $finish = null;
    public $sent = 0;
    public $cancelled = false;
    public $errored = false;
    public $timeoutForced = false;

    public function receiveFinish(array $payload){ $this->finish = $payload; $this->trySend(false); }
    public function receiveCode($code){ $this->code = $code; $this->trySend(false); }
    public function timeout(){ $this->timeoutForced = true; $this->trySend(true); }
    public function cancel(){ $this->cancelled = true; }
    public function error(){ $this->errored = true; }
    public function trySend($force){
        if($this->sent > 0 || !$this->code || $this->cancelled || $this->errored){ return; }
        if(!$this->finish && !$force){ return; }
        $this->sent++;
    }
}

$c = new FakeEmbeddedSignupFrontendCoordinator();
$c->receiveFinish(['event' => 'FINISH', 'data' => ['waba_id' => '1']]);
$assert($c->sent === 0, 'FINISH antes do code não envia ainda');
$c->receiveCode('CODE');
$assert($c->sent === 1, 'code após FINISH envia uma vez');
$c->receiveCode('CODE2');
$assert($c->sent === 1, 'callback repetido não duplica envio');

$c = new FakeEmbeddedSignupFrontendCoordinator();
$c->receiveCode('CODE');
$assert($c->sent === 0, 'code antes do FINISH aguarda');
$c->receiveFinish(['event' => 'FINISH', 'data' => ['phone_number_id' => '2']]);
$assert($c->sent === 1, 'FINISH após code envia');

$c = new FakeEmbeddedSignupFrontendCoordinator();
$c->receiveCode('CODE');
$c->timeout();
$assert($c->sent === 1 && $c->timeoutForced, 'timeout sem FINISH envia fallback uma única vez');
$c->receiveFinish(['event' => 'FINISH']);
$assert($c->sent === 1, 'FINISH tardio após timeout não duplica envio');

$c = new FakeEmbeddedSignupFrontendCoordinator();
$c->receiveCode('CODE');
$c->cancel();
$c->receiveFinish(['event' => 'FINISH']);
$c->timeout();
$assert($c->sent === 0, 'CANCEL impede envio ao backend');

$c = new FakeEmbeddedSignupFrontendCoordinator();
$c->receiveCode('CODE');
$c->error();
$c->receiveFinish(['event' => 'FINISH']);
$c->timeout();
$assert($c->sent === 0, 'ERROR impede envio ao backend');

$view = file_get_contents(__DIR__ . '/../app/Views/configuracao/meta.php');
$assert(strpos($view, 'FB.login') !== false, 'view usa FB.login');
$assert(strpos($view, 'business.facebook.com/messaging/whatsapp/onboard') === false, 'view não monta URL manual business.facebook.com');
$assert(strpos($view, 'window.open') === false, 'view não usa window.open para Embedded Signup');
$assert(strpos($view, 'btnReabrirEmbeddedSignupMeta') === false, 'botão reabrir foi removido');
$assert(strpos($view, 'finalizarEmbeddedSignup') !== false, 'view envia code e FINISH ao endpoint final');

echo "Embedded signup frontend coordinator tests passed\n";
