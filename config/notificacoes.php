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
    ],
];
