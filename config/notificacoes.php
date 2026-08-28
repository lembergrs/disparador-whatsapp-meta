<?php

use Services\CanalNotificacao;
use Services\EventoNotificacao;

return [
    'eventos' => [
        EventoNotificacao::BOAS_VINDAS => [CanalNotificacao::EMAIL, CanalNotificacao::WHATSAPP],
        EventoNotificacao::META_CONECTADA => [CanalNotificacao::EMAIL],
        EventoNotificacao::CADASTRO_PENDENTE_CONEXAO => [],
        EventoNotificacao::TRIAL_3_DIAS => [CanalNotificacao::EMAIL],
        EventoNotificacao::TRIAL_ULTIMO_DIA => [CanalNotificacao::EMAIL],
        EventoNotificacao::TRIAL_ENCERRADO => [CanalNotificacao::EMAIL],
        EventoNotificacao::PAGAMENTO_APROVADO => [CanalNotificacao::EMAIL],
        EventoNotificacao::PAGAMENTO_PENDENTE => [CanalNotificacao::EMAIL],
        EventoNotificacao::CONTA_REATIVADA => [CanalNotificacao::EMAIL],
        EventoNotificacao::COBRANCA_DISPONIVEL => [CanalNotificacao::EMAIL],
        EventoNotificacao::LEMBRETE_VENCIMENTO_D3 => [CanalNotificacao::EMAIL],
        EventoNotificacao::COBRANCA_VENCIDA_D1 => [CanalNotificacao::EMAIL],
        EventoNotificacao::LEMBRETE_VENCIDA_D3 => [CanalNotificacao::EMAIL],
        EventoNotificacao::AVISO_SUSPENSAO_D5 => [CanalNotificacao::EMAIL],
        EventoNotificacao::SUSPENSAO_INADIMPLENCIA_D7 => [CanalNotificacao::EMAIL],
        EventoNotificacao::PAGAMENTO_CONFIRMADO => [CanalNotificacao::EMAIL],
    ],
];
