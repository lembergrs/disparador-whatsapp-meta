<?php

namespace Services;

class NotificacaoFormatador
{
    private const EVENTOS = [
        EventoNotificacao::BOAS_VINDAS => 'Boas-vindas',
        EventoNotificacao::META_CONECTADA => 'Conta Meta conectada',
        EventoNotificacao::TRIAL_3_DIAS => 'Trial termina em 3 dias',
        EventoNotificacao::TRIAL_ULTIMO_DIA => 'Último dia do trial',
        EventoNotificacao::TRIAL_ENCERRADO => 'Trial encerrado',
        EventoNotificacao::PAGAMENTO_APROVADO => 'Pagamento aprovado',
        EventoNotificacao::PAGAMENTO_PENDENTE => 'Pagamento pendente',
        EventoNotificacao::CONTA_REATIVADA => 'Conta reativada',
    ];

    private const CANAIS = [
        CanalNotificacao::EMAIL => 'E-mail',
        CanalNotificacao::WHATSAPP => 'WhatsApp',
        CanalNotificacao::INTERNO => 'Notificação interna',
        CanalNotificacao::PUSH => 'Push',
        CanalNotificacao::SMS => 'SMS',
    ];

    private const STATUS = [
        'pendente' => 'Pendente',
        'processando' => 'Processando',
        'enviada' => 'Enviada',
        'enviado' => 'Enviada',
        'erro_temporario' => 'Erro temporário',
        'erro_definitivo' => 'Erro definitivo',
        'erro' => 'Erro',
        'lida' => 'Lida',
        'cancelada' => 'Cancelada',
    ];

    public static function evento($evento){ return self::EVENTOS[$evento] ?? self::humanizar($evento); }
    public static function canal($canal){ return self::CANAIS[$canal] ?? self::humanizar($canal); }
    public static function status($status){ return self::STATUS[$status] ?? self::humanizar($status); }

    public static function badgeStatus($status)
    {
        if(in_array($status, ['enviada', 'enviado', 'lida'], true)) return 'success';
        if(in_array($status, ['pendente', 'processando'], true)) return 'warning';
        if(in_array($status, ['erro_temporario', 'erro_definitivo', 'erro'], true)) return 'danger';
        if($status === 'cancelada') return 'secondary';
        return 'info';
    }

    public static function sanitizarTexto($texto)
    {
        $texto = trim((string) $texto);
        $texto = preg_replace('/[\r\n\t]+/', ' ', $texto);
        $texto = preg_replace('/(password|senha|token|secret|authorization|smtp|credential|credencial)\s*[:=]\s*\S+/i', '$1=[mascarado]', $texto);
        $texto = preg_replace('/Bearer\s+[A-Za-z0-9._\-]+/i', 'Bearer [mascarado]', $texto);
        $texto = preg_replace('/Stack trace:.*/is', '[stack trace ocultado]', $texto);
        return mb_substr($texto, 0, 1000, 'UTF-8');
    }

    public static function dadosSeguros($dados)
    {
        if($dados === null || $dados === '') return [];
        $json = json_decode((string) $dados, true);
        if(json_last_error() !== JSON_ERROR_NONE){
            return ['texto' => self::sanitizarTexto($dados)];
        }
        return self::mascararArray($json);
    }

    private static function mascararArray($valor)
    {
        if(!is_array($valor)) return $valor;
        $seguro = [];
        foreach($valor as $chave => $item){
            $chaveTexto = strtolower((string) $chave);
            if(preg_match('/token|senha|password|secret|authorization|credencial|credential|smtp|payload/i', $chaveTexto)){
                $seguro[$chave] = '[mascarado]';
                continue;
            }
            $seguro[$chave] = is_array($item) ? self::mascararArray($item) : self::sanitizarTexto($item);
        }
        return $seguro;
    }

    private static function humanizar($valor)
    {
        $valor = str_replace('_', ' ', strtolower((string) $valor));
        return mb_convert_case($valor, MB_CASE_TITLE, 'UTF-8');
    }
}
