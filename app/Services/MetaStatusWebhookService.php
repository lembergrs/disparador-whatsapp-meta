<?php

namespace Services;

class MetaStatusWebhookService
{
    private $conversas;
    private $secundario;
    private $notificacoes;
    private $logger;
    public function __construct($conversas, callable $secundario = null, callable $notificacoes = null, callable $logger = null){ $this->conversas=$conversas; $this->secundario=$secundario; $this->notificacoes=$notificacoes; $this->logger=$logger; }

    public function processarLote(array $statuses, $metaId = null)
    {
        $resumo = ['processados'=>0,'ignorados'=>0,'erros'=>0];
        $metaId = (int) $metaId;
        foreach($statuses as $status){
            try{
                $messageId = trim((string)($status['id'] ?? ''));
                $novo = MensagemStatusService::normalizar($status['status'] ?? '');
                if($messageId === '' || !$novo){ $resumo['ignorados']++; continue; }
                $timestamp = $this->timestamp($status['timestamp'] ?? null);
                $erro = $this->erro($status);
                $pricing = $this->pricing($status, $messageId);
                $alterou = false;
                try{
                    $alterou = $this->conversas->atualizarStatusPorMetaMessageId($messageId, $novo, $timestamp, $erro, $metaId) || $alterou;
                }catch(\Throwable $e){
                    $resumo['erros']++;
                    $this->logFalhaPersistencia('meta_status_persistencia_falhou', $messageId, $metaId, $novo, $e);
                }
                if($pricing){
                    try{
                        $alterou = $this->conversas->atualizarPricingPorMetaMessageId($messageId, $pricing, $metaId) || $alterou;
                    }catch(\Throwable $e){
                        $resumo['erros']++;
                        $this->logFalhaPersistencia('pricing_meta_persistencia_falhou', $messageId, $metaId, $novo, $e);
                    }
                }
                try{ if($this->secundario) call_user_func($this->secundario, $messageId, $novo, $erro, $timestamp); }catch(\Throwable $e){ $resumo['erros']++; }
                try{ if($this->notificacoes) $alterou = (bool)call_user_func($this->notificacoes, $messageId, $novo, $erro, $timestamp) || $alterou; }catch(\Throwable $e){ $resumo['erros']++; }
                $alterou ? $resumo['processados']++ : $resumo['ignorados']++;
            }catch(\Throwable $e){ $resumo['erros']++; }
        }
        return $resumo;
    }

    private function logFalhaPersistencia($acao, $messageId, $metaId, $status, \Throwable $e)
    {
        if(!$this->logger) return;
        try{
            call_user_func($this->logger, $acao, [
                'message_id'=>mb_substr((string)$messageId, 0, 255, 'UTF-8'),
                'meta_id'=>(int)$metaId,
                'status'=>(string)$status,
                'exception'=>get_class($e),
                'erro'=>MensagemStatusService::sanitizarErro($e->getMessage())
            ]);
        }catch(\Throwable $logError){}
    }

    private function pricing(array $status, $messageId)
    {
        if(!isset($status['pricing'])) return [];
        if(!is_array($status['pricing'])){
            if($this->logger){
                try{ call_user_func($this->logger, 'pricing_meta_invalido', ['message_id'=>$messageId, 'tipo'=>gettype($status['pricing'])]); }catch(\Throwable $e){}
            }
            return [];
        }

        $pricing = $status['pricing'];
        $dados = [];
        if(isset($pricing['billable']) && is_bool($pricing['billable'])) $dados['billable'] = $pricing['billable'];
        foreach([
            'pricing_model'=>['model',100],
            'category'=>['category',50],
            'type'=>['type',100],
            'market'=>['market',100],
            'country'=>['market',100],
            'currency'=>['currency',20]
        ] as $origem=>$config){
            [$destino,$limite] = $config;
            if(isset($pricing[$origem]) && is_scalar($pricing[$origem]) && trim((string)$pricing[$origem]) !== ''){
                $dados[$destino] = mb_substr(trim((string)$pricing[$origem]), 0, $limite, 'UTF-8');
            }
        }
        return $dados;
    }

    private function timestamp($valor)
    {
        if(!is_numeric($valor)) return null;
        $timestamp = (int)$valor;
        if($timestamp < 946684800 || $timestamp > time() + 86400) return null;
        return date('Y-m-d H:i:s', $timestamp);
    }

    private function erro(array $status)
    {
        $erro = $status['errors'][0] ?? [];
        $codigo = isset($erro['code']) ? preg_replace('/[^A-Za-z0-9_.-]/', '', (string)$erro['code']) : null;
        $mensagem = $erro['error_data']['details'] ?? $erro['message'] ?? $erro['title'] ?? null;
        return ['codigo'=>$codigo, 'mensagem'=>MensagemStatusService::sanitizarErro($mensagem)];
    }
}
