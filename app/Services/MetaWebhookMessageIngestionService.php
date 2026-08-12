<?php

namespace Services;

class MetaWebhookMessageIngestionService
{
    private $conversaModel;
    private $autoResponder;
    private $logger;

    public function __construct($conversaModel, callable $autoResponder = null, callable $logger = null)
    {
        $this->conversaModel = $conversaModel;
        $this->autoResponder = $autoResponder;
        $this->logger = $logger;
    }

    public function processarInbound(array $value, array $metaConta)
    {
        $resultado = ['criadas'=>0, 'duplicadas'=>0, 'invalidas'=>0];

        foreach(($value['messages'] ?? []) as $message){
            $dados = $this->parseInbound($message, $value);
            if(!$dados){
                $resultado['invalidas']++;
                continue;
            }

            $conversaId = null;
            $persistencia = $this->conversaModel->ingerirMensagemIdempotente($metaConta['MTA_ID'], [
                'direcao'=>'recebida',
                'tipo'=>$dados['tipo'],
                'texto'=>$dados['texto'],
                'message_id'=>$dados['message_id'],
                'status'=>'recebida',
                'origem'=>'api',
                'retorno'=>$message,
                'data_mensagem'=>$dados['data_mensagem']
            ], function() use ($metaConta, $dados, &$conversaId){
                return $conversaId = $this->conversaModel->buscarOuCriar(
                    $metaConta['CLI_ID'], $metaConta['MTA_ID'], $dados['participante'], $dados['nome'], true
                );
            });

            if(empty($persistencia['created'])){
                $resultado['duplicadas']++;
                continue;
            }

            $resultado['criadas']++;
            if($this->autoResponder){
                call_user_func($this->autoResponder, $metaConta, $conversaId, $dados['participante']);
            }
        }

        return $resultado;
    }

    public function processarEchoes(array $value, array $metaConta)
    {
        $resultado = ['criadas'=>0, 'duplicadas'=>0, 'invalidas'=>0];

        foreach(($value['message_echoes'] ?? []) as $message){
            $dados = $this->parseEcho($message, $value);
            if(!$dados){
                $resultado['invalidas']++;
                $this->log('echo_invalido', [
                    'phone_number_id'=>$value['metadata']['phone_number_id'] ?? null,
                    'message_id'=>$this->identificadorSeguro($message['id'] ?? null),
                    'type'=>$message['type'] ?? null
                ]);
                continue;
            }

            $persistencia = $this->conversaModel->ingerirMensagemIdempotente($metaConta['MTA_ID'], [
                'direcao'=>'enviada',
                'tipo'=>$dados['tipo'],
                'texto'=>$dados['texto'],
                'message_id'=>$dados['message_id'],
                'status'=>'sent',
                'origem'=>'business_app',
                'retorno'=>$message,
                'data_mensagem'=>$dados['data_mensagem']
            ], function() use ($metaConta, $dados){
                return $this->conversaModel->buscarOuCriar(
                    $metaConta['CLI_ID'], $metaConta['MTA_ID'], $dados['participante'], $dados['nome'], false
                );
            });

            empty($persistencia['created']) ? $resultado['duplicadas']++ : $resultado['criadas']++;
        }

        return $resultado;
    }

    private function parseInbound(array $message, array $value)
    {
        $participante = $this->telefoneValido($message['from'] ?? null);
        $messageId = trim((string) ($message['id'] ?? ''));
        if(!$participante || $messageId === ''){
            return null;
        }

        return $this->dadosMensagem(
            $message,
            $participante,
            $messageId,
            $value['contacts'][0]['profile']['name'] ?? null
        );
    }

    private function parseEcho(array $message, array $value)
    {
        $participante = $this->telefoneValido($message['to'] ?? null);
        $remetente = $this->telefoneValido($message['from'] ?? null);
        $business = $this->telefoneValido($value['metadata']['display_phone_number'] ?? null);
        $messageId = trim((string) ($message['id'] ?? ''));

        if(!$participante || !$remetente || !$business || $messageId === ''){
            return null;
        }

        if($this->somenteDigitos($remetente) !== $this->somenteDigitos($business)){
            return null;
        }

        if($this->somenteDigitos($participante) === $this->somenteDigitos($business)){
            return null;
        }

        $nome = null;
        foreach(($value['contacts'] ?? []) as $contact){
            $waId = $this->telefoneValido($contact['wa_id'] ?? null);
            if($waId && $this->somenteDigitos($waId) === $this->somenteDigitos($participante)){
                $nome = $contact['profile']['name'] ?? ($contact['profile']['username'] ?? null);
                break;
            }
        }

        return $this->dadosMensagem($message, $participante, $messageId, $nome);
    }

    private function dadosMensagem(array $message, $participante, $messageId, $nome)
    {
        $tipo = trim((string) ($message['type'] ?? 'text')) ?: 'text';
        $texto = $this->textoMensagem($message, $tipo);
        $timestamp = filter_var($message['timestamp'] ?? null, FILTER_VALIDATE_INT);

        return [
            'participante'=>$participante,
            'message_id'=>$messageId,
            'tipo'=>$tipo,
            'texto'=>$texto,
            'nome'=>$nome,
            'data_mensagem'=>$timestamp && $timestamp > 0
                ? date('Y-m-d H:i:s', $timestamp)
                : date('Y-m-d H:i:s')
        ];
    }

    private function textoMensagem(array $message, $tipo)
    {
        if($tipo === 'text') return (string) ($message['text']['body'] ?? '');
        if($tipo === 'button') return (string) ($message['button']['text'] ?? '[Botão]');
        if($tipo === 'interactive'){
            return (string) ($message['interactive']['button_reply']['title']
                ?? $message['interactive']['list_reply']['title']
                ?? '[Interativo]');
        }
        foreach(['image','video','document'] as $media){
            if($tipo === $media && !empty($message[$media]['caption'])) return (string) $message[$media]['caption'];
        }
        return '[' . strtoupper($tipo) . ']';
    }

    private function telefoneValido($telefone)
    {
        $telefone = trim((string) $telefone);
        return preg_match('/^\+?[0-9]{8,20}$/', $telefone) ? $telefone : null;
    }

    private function somenteDigitos($telefone)
    {
        return preg_replace('/\D/', '', (string) $telefone);
    }

    private function identificadorSeguro($id)
    {
        $id = trim((string) $id);
        return $id === '' ? null : substr(hash('sha256', $id), 0, 16);
    }

    private function log($acao, array $dados)
    {
        if($this->logger) call_user_func($this->logger, $acao, $dados);
    }
}
