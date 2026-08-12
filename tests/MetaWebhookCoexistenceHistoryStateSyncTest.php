<?php

require_once __DIR__ . '/../app/Services/TelefoneService.php';
require_once __DIR__ . '/../app/Services/MetaWebhookMessageIngestionService.php';
require_once __DIR__ . '/../app/Services/MetaWebhookStateSyncService.php';

use Services\MetaWebhookMessageIngestionService;
use Services\MetaWebhookStateSyncService;

function historyAssert($condition, $message)
{
    if(!$condition){ fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
}

class FakeHistoryConversa
{
    public $messages = [];
    public $conversations = [];
    public $unread = 0;

    public function ingerirMensagemIdempotente($metaId, array $dados, callable $resolver)
    {
        $key = $metaId . ':' . $dados['message_id'];
        if(isset($this->messages[$key])) return ['id'=>$this->messages[$key]['id'], 'created'=>false];
        $conversationId = $resolver();
        $dados['id'] = count($this->messages) + 1;
        $dados['conversa_id'] = $conversationId;
        $this->messages[$key] = $dados;
        if($dados['direcao'] === 'recebida' && ($dados['resumo_mode'] ?? '') !== 'history') $this->unread++;
        if(($dados['resumo_mode'] ?? '') === 'history'){
            $current = $this->conversations[$conversationId]['date'] ?? null;
            if(!$current || $dados['data_mensagem'] >= $current){
                $this->conversations[$conversationId]['date'] = $dados['data_mensagem'];
                $this->conversations[$conversationId]['preview'] = $dados['texto'];
            }
        }
        return ['id'=>$dados['id'], 'created'=>true];
    }

    public function buscarOuCriar($clienteId, $metaId, $numero, $nome = null, $criarContato = true)
    {
        $id = $clienteId * 1000 + $metaId;
        $this->conversations[$id]['numero'] = $numero;
        $this->conversations[$id]['criarContato'] = $criarContato;
        return $id;
    }
}

class FakeStateContato
{
    public $rows = [];
    public function buscarPorTelefone($clienteId, $telefone){ return $this->rows[$clienteId . ':' . $telefone] ?? false; }
    public function salvar($dados){
        $key = $dados['cliente_id'] . ':' . $dados['telefone'];
        if(isset($this->rows[$key])) return $this->rows[$key]['CON_ID'];
        $dados['CON_ID'] = count($this->rows) + 1;
        $dados['CON_Nome'] = $dados['nome'];
        return $this->rows[$key] = $dados;
    }
}

function historyValue(array $messages)
{
    return [
        'metadata'=>['display_phone_number'=>'5511888880000','phone_number_id'=>'phone-9'],
        'history'=>[['metadata'=>['phase'=>1,'chunk_order'=>1,'progress'=>50], 'threads'=>[[
            'id'=>'5511999990000', 'messages'=>$messages
        ]]]]
    ];
}

$model = new FakeHistoryConversa();
$autoReplies = 0;
$logs = [];
$service = new MetaWebhookMessageIngestionService($model, function() use (&$autoReplies){$autoReplies++;}, function($a,$d) use (&$logs){$logs[]=[$a,$d];});
$account = ['MTA_ID'=>9,'CLI_ID'=>4];

$inbound = ['from'=>'5511999990000','id'=>'wamid.hist.in','timestamp'=>'1750000000','type'=>'text','text'=>['body'=>'Histórico recebido'],'history_context'=>['status'=>'read']];
$outbound = ['from'=>'5511888880000','to'=>'5511999990000','id'=>'wamid.hist.out','timestamp'=>'1750000100','type'=>'text','text'=>['body'=>'Histórico enviado'],'history_context'=>['status'=>'read']];
$result = $service->processarHistorico(historyValue([$inbound,$outbound]), $account);
$savedInbound = $model->messages['9:wamid.hist.in'];
$savedOutbound = $model->messages['9:wamid.hist.out'];
historyAssert($result['criadas'] === 2, 'history cria mensagens válidas em lote');
historyAssert($savedInbound['direcao'] === 'recebida' && $savedInbound['status'] === 'recebida', 'history inbound é recebida');
historyAssert($savedOutbound['direcao'] === 'enviada' && $savedOutbound['status'] === 'read', 'history outbound preserva direção e status comprovados');
historyAssert($savedInbound['origem'] === 'history' && $savedOutbound['origem'] === 'history', 'mensagens novas usam origem history');
historyAssert($savedInbound['data_mensagem'] === date('Y-m-d H:i:s', 1750000000), 'timestamp histórico é preservado');
historyAssert($model->unread === 0 && $autoReplies === 0, 'history não gera unread nem auto resposta');
historyAssert($model->conversations[4009]['criarContato'] === false, 'history não cria contatos indiscriminadamente');

$retry = $service->processarHistorico(historyValue([$inbound,$outbound]), $account);
historyAssert($retry['duplicadas'] === 2 && count($model->messages) === 2, 'retry de history é idempotente');

$model->messages['9:wamid.existing.in'] = ['id'=>90,'direcao'=>'recebida','origem'=>'api','status'=>'recebida'];
$model->messages['9:wamid.existing.echo'] = ['id'=>91,'direcao'=>'enviada','origem'=>'business_app','status'=>'delivered'];
$existingIn = $inbound; $existingIn['id'] = 'wamid.existing.in';
$existingEcho = $outbound; $existingEcho['id'] = 'wamid.existing.echo'; $existingEcho['history_context']['status'] = 'sent';
$existingResult = $service->processarHistorico(historyValue([$existingIn,$existingEcho]), $account);
historyAssert($existingResult['duplicadas'] === 2, 'history correlaciona inbound e echo existentes');
historyAssert($model->messages['9:wamid.existing.in']['origem'] === 'api', 'origem inbound existente é preservada');
historyAssert($model->messages['9:wamid.existing.echo']['origem'] === 'business_app', 'origem echo existente é preservada');
historyAssert($model->messages['9:wamid.existing.echo']['status'] === 'delivered', 'status existente não é rebaixado');

$conversationId = 4009;
$model->conversations[$conversationId] = ['date'=>'2026-08-01 12:00:00','preview'=>'Atual'];
$old = $inbound; $old['id']='wamid.old'; $old['timestamp']=(string)strtotime('2026-07-01 12:00:00'); $old['text']['body']='Antiga';
$new = $inbound; $new['id']='wamid.new'; $new['timestamp']=(string)strtotime('2026-08-02 12:00:00'); $new['text']['body']='Mais nova';
$service->processarHistorico(historyValue([$old]), $account);
historyAssert($model->conversations[$conversationId]['preview'] === 'Atual', 'history antiga não substitui resumo atual');
$service->processarHistorico(historyValue([$new]), $account);
historyAssert($model->conversations[$conversationId]['preview'] === 'Mais nova', 'history mais nova pode atualizar resumo');

$invalidDirection = $inbound; $invalidDirection['id']='wamid.bad.direction'; $invalidDirection['from']='5511777770000';
$invalidParticipant = $outbound; $invalidParticipant['id']='wamid.bad.participant'; unset($invalidParticipant['to']);
$invalidTimestamp = $inbound; $invalidTimestamp['id']='wamid.bad.time'; $invalidTimestamp['timestamp']='invalid';
$unsupported = $inbound; $unsupported['id']='wamid.unsupported'; $unsupported['type']='revoke';
$invalidResult = $service->processarHistorico(historyValue([$invalidDirection,$invalidParticipant,$invalidTimestamp,$unsupported]), $account);
historyAssert($invalidResult['invalidas'] === 4, 'direção, participante, timestamp e tipo ambíguos são ignorados');

$otherAccount = ['MTA_ID'=>10,'CLI_ID'=>5];
$cross = $inbound; $cross['id']='wamid.hist.in';
$crossResult = $service->processarHistorico(historyValue([$cross]), $otherAccount);
historyAssert($crossResult['criadas'] === 1 && isset($model->messages['10:wamid.hist.in']), 'idempotência e conversa são isoladas por conta');
historyAssert($model->messages['10:wamid.hist.in']['conversa_id'] === 5010, 'conversa respeita CLI e MTA da conta resolvida');

$contacts = new FakeStateContato();
$messageCountBeforeState = count($model->messages);
$contacts->rows['4:5511999990000'] = ['CON_ID'=>1,'CON_Nome'=>'Nome local','telefone'=>'5511999990000'];
$stateService = new MetaWebhookStateSyncService($contacts, function($a,$d) use (&$logs){$logs[]=[$a,$d];});
$stateValue = [
    'metadata'=>['phone_number_id'=>'phone-9'],
    'state_sync'=>[
        ['type'=>'contact','action'=>'update','contact'=>['full_name'=>'Nome do app','phone_number'=>'5511999990000'],'metadata'=>['timestamp'=>'1750000000']],
        ['type'=>'contact','action'=>'add','contact'=>['full_name'=>'Novo contato','phone_number'=>'5511988880000'],'metadata'=>['timestamp'=>'1750000001']],
        ['type'=>'contact','action'=>'remove','contact'=>['phone_number'=>'5511977770000']],
        ['type'=>'chat','action'=>'update']
    ]
];
$stateResult = $stateService->processar($stateValue, $account);
historyAssert($stateResult === ['criadas'=>1,'existentes'=>1,'ignoradas'=>2,'invalidas'=>0], 'state sync aplica somente contatos add/update seguros');
historyAssert($contacts->rows['4:5511999990000']['CON_Nome'] === 'Nome local', 'state sync não sobrescreve nome local');
historyAssert($contacts->rows['4:5511988880000']['CON_Nome'] === 'Novo contato', 'state sync cria contato ausente');
$stateRetry = $stateService->processar($stateValue, $account);
historyAssert($stateRetry['criadas'] === 0 && $stateRetry['existentes'] === 2, 'retry state sync é não destrutivo');
historyAssert(count($model->messages) === $messageCountBeforeState, 'state sync não é inserido como mensagem');

$otherState = $stateService->processar(['state_sync'=>[[
    'type'=>'contact','action'=>'add','contact'=>['full_name'=>'Outro cliente','phone_number'=>'5511988880000']
]]], $otherAccount);
historyAssert($otherState['criadas'] === 1 && isset($contacts->rows['5:5511988880000']), 'contato state sync é isolado por cliente');

$webhook = file_get_contents(__DIR__ . '/../public/webhook/meta.php');
$config = file_get_contents(__DIR__ . '/../config/config.php');
$conversaSource = file_get_contents(__DIR__ . '/../app/Models/Conversa.php');
historyAssert(strpos($webhook, "\$field === 'history'") !== false && strpos($webhook, "\$field === 'smb_app_state_sync'") !== false, 'webhook roteia Phase 2B explicitamente');
historyAssert(strpos($config, "env_valor('META_COEXISTENCE_ENABLED', 'false')") !== false, 'flag continua desabilitada por padrão');
historyAssert(strpos($conversaSource, 'CVS_DataUltimaMensagem < ?') !== false, 'resumo histórico só avança para timestamp posterior');
historyAssert(strpos($conversaSource, 'ORDER BY MSG_DataMensagem ASC, MSG_ID ASC') !== false, 'mensagens são listadas em ordem cronológica');

echo "Meta webhook Coexistence history/state-sync tests passed\n";
