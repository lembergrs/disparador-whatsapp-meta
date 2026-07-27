<?php

namespace Services;

final class EventoNotificacao
{
    public const BOAS_VINDAS = 'boas_vindas';
    public const META_CONECTADA = 'meta_conectada';
    public const CADASTRO_PENDENTE_CONEXAO = 'cadastro_pendente_conexao';
    public const TRIAL_3_DIAS = 'trial_3_dias';
    public const TRIAL_ULTIMO_DIA = 'trial_ultimo_dia';
    public const TRIAL_ENCERRADO = 'trial_encerrado';
    public const PAGAMENTO_APROVADO = 'pagamento_aprovado';
    public const PAGAMENTO_PENDENTE = 'pagamento_pendente';
    public const CONTA_REATIVADA = 'conta_reativada';

    public static function todos()
    {
        return [
            self::BOAS_VINDAS,
            self::META_CONECTADA,
            self::CADASTRO_PENDENTE_CONEXAO,
            self::TRIAL_3_DIAS,
            self::TRIAL_ULTIMO_DIA,
            self::TRIAL_ENCERRADO,
            self::PAGAMENTO_APROVADO,
            self::PAGAMENTO_PENDENTE,
            self::CONTA_REATIVADA,
        ];
    }
}
