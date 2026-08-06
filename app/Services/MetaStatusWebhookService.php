<?php

namespace Services;

class MetaStatusWebhookService
{
    private $conversas;
    private $secundario;
    private $notificacoes;
    public function __construct($conversas, callable $secundario = null, callable $notificacoes = null){ $this->conversas=$conversas; $this->secundario=$secundario; $this->notificacoes=$notificacoes; }

    public function processarLote(array $statuses)
    {
        $resumo = ['processados'=>0,'ignorados'=>0,'erros'=>0];
        foreach($statuses as $status){
            try{
                $messageId = trim((string)($status['id'] ?? ''));
                $novo = MensagemStatusService::normalizar($status['status'] ?? '');
                if($messageId === '' || !$novo){ $resumo['ignorados']++; continue; }
                $timestamp = $this->timestamp($status['timestamp'] ?? null);
                $erro = $this->erro($status);
                $alterou = false;
                try{ $alterou = $this->conversas->atualizarStatusPorMetaMessageId($messageId, $novo, $timestamp, $erro) || $alterou; }catch(\Throwable $e){ $resumo['erros']++; }
                try{ if($this->secundario) call_user_func($this->secundario, $messageId, $novo, $erro, $timestamp); }catch(\Throwable $e){ $resumo['erros']++; }
                try{ if($this->notificacoes) $alterou = (bool)call_user_func($this->notificacoes, $messageId, $novo, $erro, $timestamp) || $alterou; }catch(\Throwable $e){ $resumo['erros']++; }
                $alterou ? $resumo['processados']++ : $resumo['ignorados']++;
            }catch(\Throwable $e){ $resumo['erros']++; }
        }
        return $resumo;
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
