<?php

require_once __DIR__ . '/../app/Services/MetaWebhookMessageIngestionService.php';

use Services\MetaWebhookMessageIngestionService;

function echoAssert($condition, $message)
{
    if(!$condition){
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

class FakeEchoConversa
{
    public $messages = [];
    public $unread = 0;
    public $conversationCalls = [];

    public function buscarMensagemPorMetaIdConta($metaId, $messageId)
    {
        return $this->messages[$metaId . ':' . $messageId] ?? null;
    }

    public function buscarOuCriar($clienteId, $metaId, $numero, $nome = null, $criarContato = true)
    {
        $this->conversationCalls[] = compact('clienteId','metaId','numero','nome','criarContato');
        return 77;
    }

    public function ingerirMensagemIdempotente($metaId, array $dados, callable $resolverConversa)
    {
        $key = $metaId . ':' . $dados['message_id'];
        if(isset($this->messages[$key])) return ['id'=>$this->messages[$key]['id'], 'created'=>false];
        $dados['conversa_id'] = $resolverConversa();
        $dados['id'] = count($this->messages) + 1;
        $this->messages[$key] = $dados;
        if($dados['direcao'] === 'recebida') $this->unread++;
        return ['id'=>$dados['id'], 'created'=>true];
    }
}

$conta = ['MTA_ID'=>9, 'CLI_ID'=>4];
$model = new FakeEchoConversa();
$autoReplies = 0;
$logs = [];
$service = new MetaWebhookMessageIngestionService(
    $model,
    function() use (&$autoReplies){ $autoReplies++; },
    function($acao, $dados) use (&$logs){ $logs[] = compact('acao','dados'); }
);

$inbound = [
    'contacts'=>[['profile'=>['name'=>'Cliente'], 'wa_id'=>'5511999990000']],
    'messages'=>[[
        'from'=>'5511999990000', 'id'=>'wamid.inbound.1', 'timestamp'=>'1760000000',
        'type'=>'text', 'text'=>['body'=>'Olá']
    ]]
];
$firstInbound = $service->processarInbound($inbound, $conta);
$secondInbound = $service->processarInbound($inbound, $conta);
$savedInbound = $model->messages['9:wamid.inbound.1'];
echoAssert($savedInbound['direcao'] === 'recebida' && $savedInbound['status'] === 'recebida', 'inbound permanece recebida');
echoAssert($model->unread === 1, 'inbound incrementa não lidas uma única vez');
echoAssert($autoReplies === 1, 'inbound executa auto resposta apenas na criação');
echoAssert($firstInbound['criadas'] === 1 && $secondInbound['duplicadas'] === 1, 'inbound duplicada é idempotente');

$echo = [
    'metadata'=>['display_phone_number'=>'5511888880000', 'phone_number_id'=>'phone-9'],
    'contacts'=>[['profile'=>['name'=>'Cliente'], 'wa_id'=>'5511999990000']],
    'message_echoes'=>[[
        'from'=>'5511888880000', 'to'=>'5511999990000', 'id'=>'wamid.echo.1',
        'timestamp'=>'1760000100', 'type'=>'text', 'text'=>['body'=>'Resposta pelo app']
    ]]
];
$firstEcho = $service->processarEchoes($echo, $conta);
$secondEcho = $service->processarEchoes($echo, $conta);
$savedEcho = $model->messages['9:wamid.echo.1'];
echoAssert($savedEcho['direcao'] === 'enviada' && $savedEcho['status'] === 'sent', 'echo é outbound enviada');
echoAssert($savedEcho['origem'] === 'business_app', 'echo preserva origem Business App');
echoAssert($model->unread === 1, 'echo não incrementa não lidas');
echoAssert($autoReplies === 1, 'echo nunca executa auto resposta');
echoAssert($firstEcho['criadas'] === 1 && $secondEcho['duplicadas'] === 1, 'echo duplicado é idempotente');
$lastConversation = end($model->conversationCalls);
echoAssert($lastConversation['numero'] === '5511999990000' && $lastConversation['criarContato'] === false, 'echo usa to como participante sem criar contato inbound');

$model->messages['9:wamid.api.existing'] = ['id'=>99, 'direcao'=>'enviada', 'origem'=>'api'];
$apiEcho = $echo;
$apiEcho['message_echoes'][0]['id'] = 'wamid.api.existing';
$apiResult = $service->processarEchoes($apiEcho, $conta);
echoAssert($apiResult['duplicadas'] === 1 && $model->messages['9:wamid.api.existing']['origem'] === 'api', 'echo correlacionado não duplica nem altera mensagem API');

$invalid = $echo;
$invalid['message_echoes'][0]['from'] = '5511777770000';
$callsBefore = count($model->conversationCalls);
$invalidResult = $service->processarEchoes($invalid, $conta);
echoAssert($invalidResult['invalidas'] === 1 && count($model->conversationCalls) === $callsBefore, 'echo ambíguo não cria conversa errada');
echoAssert(!empty($logs) && empty($logs[0]['dados']['payload']), 'diagnóstico inválido não inclui payload bruto');

$webhook = file_get_contents(__DIR__ . '/../public/webhook/meta.php');
$conversa = file_get_contents(__DIR__ . '/../app/Models/Conversa.php');
$config = file_get_contents(__DIR__ . '/../config/config.php');
echoAssert(strpos($webhook, "\$field === 'messages'") !== false, 'webhook roteia messages explicitamente');
echoAssert(strpos($webhook, "\$field === 'smb_message_echoes'") !== false, 'webhook roteia echoes explicitamente');
echoAssert(strpos($conversa, 'ingerirMensagemIdempotente') !== false && strpos($conversa, 'FOR UPDATE') !== false, 'persistência usa primitiva idempotente transacional');
echoAssert(strpos($conversa, 'INNER JOIN conversas c') !== false && strpos($conversa, 'c.MTA_ID=?') !== false, 'deduplicação é escopada por conta Meta');
echoAssert(strpos($webhook, "\$field === 'history'") !== false && strpos($webhook, "\$field === 'smb_app_state_sync'") !== false, 'roteamento posterior de history/state sync não altera echoes');
echoAssert(strpos($config, "env_valor('META_COEXISTENCE_ENABLED', 'false')") !== false, 'Coexistence permanece desabilitado por padrão');

echo "Meta webhook Coexistence echo tests passed\n";
