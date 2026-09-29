<?php

require_once dirname(__DIR__) . '/app/Services/MetaWebhookMessageIngestionService.php';

use Services\MetaWebhookMessageIngestionService;

$assert = function($condition, $message){
    if(!$condition){
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

class FakeConversaMultimidia
{
    public $mensagens = [];

    public function buscarOuCriar($clienteId, $metaId, $numero, $nome = null, $criarContato = true)
    {
        return 77;
    }

    public function ingerirMensagemIdempotente($metaId, array $dados, callable $resolverConversa)
    {
        $dados['conversa_id'] = $resolverConversa();
        $this->mensagens[] = $dados;
        return ['id'=>count($this->mensagens), 'created'=>true];
    }
}

$model = new FakeConversaMultimidia();
$autoRespostas = 0;
$service = new MetaWebhookMessageIngestionService($model, function() use (&$autoRespostas){
    $autoRespostas++;
});

$service->processarInbound([
    'contacts'=>[['profile'=>['name'=>'Cliente']]],
    'messages'=>[[
        'from'=>'5541999999999',
        'id'=>'wamid.audio',
        'timestamp'=>'1790694000',
        'type'=>'audio',
        'audio'=>[
            'id'=>'media-audio-1',
            'mime_type'=>'audio/ogg; codecs=opus',
            'sha256'=>'hash-audio'
        ]
    ]]
], ['CLI_ID'=>1, 'MTA_ID'=>2]);

$audio = $model->mensagens[0];
$assert($audio['tipo'] === 'audio', 'Áudio deve preservar MSG_Tipo.');
$assert($audio['media_id'] === 'media-audio-1', 'Áudio deve persistir media_id.');
$assert($audio['media_mime_type'] === 'audio/ogg; codecs=opus', 'Áudio deve persistir mime_type.');
$assert($audio['media_sha256'] === 'hash-audio', 'Áudio deve persistir sha256.');
$assert($audio['media_nome'] === null, 'Áudio sem filename deve manter nome nulo.');
$assert($autoRespostas === 1, 'Mensagem de áudio continua sendo mensagem do cliente para fins de autoresposta.');

$service->processarInbound([
    'messages'=>[[
        'from'=>'5541999999999',
        'id'=>'wamid.reaction',
        'timestamp'=>'1790694010',
        'type'=>'reaction',
        'reaction'=>[
            'message_id'=>'wamid.original',
            'emoji'=>'❤️'
        ]
    ]]
], ['CLI_ID'=>1, 'MTA_ID'=>2]);

$reaction = $model->mensagens[1];
$assert($reaction['tipo'] === 'reaction', 'Reaction deve ser persistida como tipo próprio.');
$assert($reaction['reacao_message_id'] === 'wamid.original', 'Reaction deve referenciar a mensagem original.');
$assert($reaction['reacao_emoji'] === '❤️', 'Reaction deve preservar o emoji.');
$assert($reaction['texto'] === '❤️ [Reação]', 'Resumo textual da reaction deve ser legível.');
$assert($autoRespostas === 1, 'Reaction não deve disparar autoresposta.');

$service->processarInbound([
    'messages'=>[[
        'from'=>'5541999999999',
        'id'=>'wamid.document',
        'timestamp'=>'1790694020',
        'type'=>'document',
        'document'=>[
            'id'=>'media-document-1',
            'mime_type'=>'application/pdf',
            'sha256'=>'hash-document',
            'filename'=>'contrato.pdf',
            'caption'=>'Contrato assinado'
        ]
    ]]
], ['CLI_ID'=>1, 'MTA_ID'=>2]);

$documento = $model->mensagens[2];
$assert($documento['media_id'] === 'media-document-1', 'Documento deve persistir media_id.');
$assert($documento['media_nome'] === 'contrato.pdf', 'Documento deve persistir filename.');
$assert($documento['texto'] === 'Contrato assinado', 'Documento deve preservar caption como texto.');

echo "Multimedia message ingestion checks passed\n";
