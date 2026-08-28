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
    public const COBRANCA_DISPONIVEL = 'cobranca_disponivel';
    public const LEMBRETE_VENCIMENTO_D3 = 'lembrete_vencimento_d3';
    public const COBRANCA_VENCIDA_D1 = 'cobranca_vencida_d1';
    public const LEMBRETE_VENCIDA_D3 = 'lembrete_vencida_d3';
    public const AVISO_SUSPENSAO_D5 = 'aviso_suspensao_d5';
    public const SUSPENSAO_INADIMPLENCIA_D7 = 'suspensao_inadimplencia_d7';
    public const PAGAMENTO_CONFIRMADO = 'pagamento_confirmado';

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
            self::COBRANCA_DISPONIVEL,
            self::LEMBRETE_VENCIMENTO_D3,
            self::COBRANCA_VENCIDA_D1,
            self::LEMBRETE_VENCIDA_D3,
            self::AVISO_SUSPENSAO_D5,
            self::SUSPENSAO_INADIMPLENCIA_D7,
            self::PAGAMENTO_CONFIRMADO,
        ];
    }
}
