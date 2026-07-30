<?php

namespace Services;

class MensagemStatusService
{
    private const PRIORIDADE = ['pending'=>0, 'processing'=>1, 'sent'=>2, 'delivered'=>3, 'read'=>4];
    private const ALIASES = [
        'pendente'=>'pending', 'fila'=>'pending', 'aguardando_confirmacao'=>'pending',
        'processando'=>'processing', 'enviado'=>'sent', 'entregue'=>'delivered',
        'lido'=>'read', 'erro'=>'failed', 'falha'=>'failed',
    ];
    private const VISUAL = [
        'pending'=>['rotulo'=>'Aguardando envio','icone'=>'fa-clock','classe'=>'mensagem-status-pendente'],
        'processing'=>['rotulo'=>'Aguardando envio','icone'=>'fa-clock','classe'=>'mensagem-status-pendente'],
        'sent'=>['rotulo'=>'Enviada','icone'=>'fa-check','classe'=>'mensagem-status-enviada'],
        'delivered'=>['rotulo'=>'Entregue','icone'=>'fa-check-double','classe'=>'mensagem-status-entregue'],
        'read'=>['rotulo'=>'Lida','icone'=>'fa-check-double','classe'=>'mensagem-status-lida'],
        'failed'=>['rotulo'=>'Falha no envio','icone'=>'fa-exclamation-circle','classe'=>'mensagem-status-falha'],
    ];

    public static function normalizar($status)
    {
        $status = strtolower(trim((string)$status));
        if(isset(self::PRIORIDADE[$status]) || $status === 'failed') return $status;
        return self::ALIASES[$status] ?? null;
    }

    public static function prioridade($status)
    {
        $normalizado = self::normalizar($status);
        return $normalizado === 'failed' ? -1 : (self::PRIORIDADE[$normalizado] ?? null);
    }

    public static function podeAvancar($atual, $novo)
    {
        $atual = self::normalizar($atual); $novo = self::normalizar($novo);
        if(!$novo) return false;
        if(!$atual) return true;
        if($atual === $novo) return false;
        if($novo === 'failed') return in_array($atual, ['pending','processing'], true);
        if($atual === 'failed') return false;
        return self::prioridade($novo) > self::prioridade($atual);
    }

    public static function apresentacao($status, $codigoErro = null, $mensagemErro = null, $dataFalha = null)
    {
        $normalizado = self::normalizar($status);
        if(!$normalizado || !isset(self::VISUAL[$normalizado])) return null;
        $dados = self::VISUAL[$normalizado] + ['status'=>$normalizado];
        $erro = self::sanitizarErro($mensagemErro);
        $codigo = preg_replace('/[^A-Za-z0-9_.-]/', '', (string)$codigoErro);
        $dados['tooltip'] = $dados['rotulo'];
        if($normalizado === 'failed' && $codigo !== '') $dados['tooltip'] .= ' — Código Meta: ' . $codigo;
        if($normalizado === 'failed' && $erro !== '') $dados['tooltip'] .= ' — ' . $erro;
        if($normalizado === 'failed' && $dataFalha && strtotime($dataFalha)) $dados['tooltip'] .= ' — ' . date('d/m/Y H:i', strtotime($dataFalha));
        return $dados;
    }

    public static function statusAtuaisPermitidos($novo)
    {
        $novo = self::normalizar($novo);
        $grupos = [
            'pending'=>['pending','pendente','fila','aguardando_confirmacao'],
            'processing'=>['processing','processando'], 'sent'=>['sent','enviado'],
            'delivered'=>['delivered','entregue'], 'read'=>['read','lido'], 'failed'=>['failed','erro','falha'],
        ];
        if($novo === 'sent') return array_merge($grupos['pending'], $grupos['processing'], ['enviado']);
        if($novo === 'delivered') return array_merge($grupos['pending'], $grupos['processing'], $grupos['sent'], ['entregue']);
        if($novo === 'read') return array_merge($grupos['pending'], $grupos['processing'], $grupos['sent'], $grupos['delivered'], ['lido']);
        if($novo === 'failed') return array_merge($grupos['pending'], $grupos['processing'], ['erro','falha']);
        return [];
    }

    public static function statusAtuaisAceitosNoWebhook($novo)
    {
        $novo = self::normalizar($novo);
        if($novo === 'failed') return self::statusAtuaisPermitidos($novo);
        if(!in_array($novo, ['sent','delivered','read'], true)) return [];
        return ['pending','pendente','fila','aguardando_confirmacao','processing','processando','sent','enviado','delivered','entregue','read','lido'];
    }

    public static function sanitizarErro($mensagem)
    {
        $mensagem = preg_replace('/[\r\n\t]+/', ' ', trim((string)$mensagem));
        $mensagem = preg_replace('/(token|authorization|bearer|secret|password|senha)\s*[:=]?\s*\S+/i', '$1=[removido]', $mensagem);
        return mb_substr($mensagem, 0, 500, 'UTF-8');
    }
}
