<?php

namespace Services;

class NotificacaoStatusService
{
    private const ALIASES = [
        'pendente'=>'pendente', 'pending'=>'pendente', 'fila'=>'pendente', 'aguardando'=>'pendente',
        'aguardando_confirmacao'=>'pendente', 'processando'=>'processando', 'processing'=>'processando',
        'enviada'=>'enviada', 'enviado'=>'enviada', 'sent'=>'enviada',
        'entregue'=>'entregue', 'delivered'=>'entregue', 'lida'=>'lida', 'lido'=>'lida', 'read'=>'lida',
        'erro_temporario'=>'erro_temporario', 'falha_temporaria'=>'erro_temporario',
        'erro_definitivo'=>'erro_definitivo', 'failed'=>'erro_definitivo', 'erro'=>'erro_definitivo', 'falha'=>'erro_definitivo',
    ];

    private const VISUAL = [
        'pendente'=>['icone'=>'fa-clock', 'classe'=>'notificacao-status-pendente', 'rotulo'=>'Na fila'],
        'processando'=>['icone'=>'fa-clock', 'classe'=>'notificacao-status-pendente', 'rotulo'=>'Aguardando processamento'],
        'enviada'=>['icone'=>'fa-check', 'classe'=>'notificacao-status-enviada', 'rotulo'=>'Enviada'],
        'entregue'=>['icone'=>'fa-check-double', 'classe'=>'notificacao-status-entregue', 'rotulo'=>'Entregue'],
        'lida'=>['icone'=>'fa-check-double', 'classe'=>'notificacao-status-lida', 'rotulo'=>'Lida'],
        'erro_temporario'=>['icone'=>'fa-exclamation-triangle', 'classe'=>'notificacao-status-erro-temporario', 'rotulo'=>'Erro temporário'],
        'erro_definitivo'=>['icone'=>'fa-times-circle', 'classe'=>'notificacao-status-erro-definitivo', 'rotulo'=>'Erro definitivo'],
    ];

    public static function normalizar($status)
    {
        $status = strtolower(trim((string)$status));
        return self::ALIASES[$status] ?? null;
    }

    public static function apresentacao($status, $canal, $codigoErro = null, $mensagemErro = null)
    {
        $normalizado = self::normalizar($status);
        if(!$normalizado || !isset(self::VISUAL[$normalizado])) return self::desconhecido($status);

        if($canal === CanalNotificacao::EMAIL && in_array($normalizado, ['entregue','lida'], true)){
            return self::desconhecido($status);
        }

        $dados = self::VISUAL[$normalizado] + ['status'=>$normalizado];
        if($normalizado === 'enviada'){
            $dados['tooltip'] = $canal === CanalNotificacao::EMAIL
                ? 'Enviada ao provedor de e-mail'
                : 'Enviada para a Meta';
        }elseif($normalizado === 'entregue'){
            $dados['tooltip'] = 'Entregue ao aparelho';
        }elseif($normalizado === 'lida'){
            $dados['tooltip'] = 'Lida pelo destinatário';
        }elseif($normalizado === 'erro_temporario'){
            $dados['tooltip'] = 'Falha temporária — nova tentativa será realizada';
        }elseif($normalizado === 'erro_definitivo'){
            $dados['tooltip'] = self::tooltipErro($codigoErro, $mensagemErro);
        }else{
            $dados['tooltip'] = $dados['rotulo'];
        }
        return $dados;
    }

    private static function tooltipErro($codigo, $mensagem)
    {
        $resumo = MensagemStatusService::sanitizarErro($mensagem);
        $resumo = trim(preg_replace('/\s+/', ' ', $resumo));
        if($resumo !== '') return 'Erro definitivo: ' . mb_substr($resumo, 0, 80, 'UTF-8');
        $codigo = preg_replace('/[^A-Za-z0-9_.-]/', '', (string)$codigo);
        return $codigo !== '' ? 'Erro definitivo: código ' . $codigo : 'Erro definitivo';
    }

    private static function desconhecido($status)
    {
        $rotulo = NotificacaoFormatador::status($status);
        if(trim($rotulo) === '') $rotulo = 'Status não informado';
        return [
            'status'=>'desconhecido', 'icone'=>'fa-circle', 'classe'=>'notificacao-status-desconhecido',
            'rotulo'=>$rotulo, 'tooltip'=>$rotulo,
        ];
    }
}
