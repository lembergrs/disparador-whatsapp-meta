<?php

namespace Services\Indicacao;

use InvalidArgumentException;

class IndicacaoStatusTransitionService
{
    private const MAPAS = [
        'codigo' => [
            'nao_liberado'=>['ativo','cancelado'], 'ativo'=>['suspenso','cancelado'],
            'suspenso'=>['ativo','cancelado'], 'cancelado'=>[]
        ],
        'indicacao' => [
            'cadastrada'=>['aguardando_pagamento','inelegivel','fraude','cancelada'],
            'aguardando_pagamento'=>['pagamento_confirmado','cancelada','fraude','inelegivel'],
            'pagamento_confirmado'=>['em_confirmacao','cancelada','fraude'],
            'em_confirmacao'=>['aprovada','cancelada','fraude','inelegivel'],
            'aprovada'=>['fraude'], 'cancelada'=>[], 'fraude'=>[], 'inelegivel'=>[]
        ],
        'credito' => [
            'pendente'=>['em_confirmacao','cancelado'],
            'em_confirmacao'=>['liberado','cancelado'],
            'liberado'=>['bloqueado','reservado','cancelado','expirado'],
            'bloqueado'=>['liberado','cancelado','expirado'],
            'reservado'=>['liberado','utilizado','cancelado','expirado'],
            'utilizado'=>[], 'cancelado'=>[], 'expirado'=>[]
        ]
    ];

    public function validar(string $tipo, string $atual, string $novo): void
    {
        if($atual === $novo) return;
        if(!isset(self::MAPAS[$tipo][$atual]) || !in_array($novo, self::MAPAS[$tipo][$atual], true)){
            throw new InvalidArgumentException("Transição de status inválida para {$tipo}: {$atual} -> {$novo}");
        }
    }
}
