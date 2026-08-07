<?php
namespace Services\Indicacao;
class IndicacaoStatusTransitionService
{
    private const INDICACAO=['cadastrada'=>['aguardando_pagamento','cancelada','fraude','inelegivel'],'aguardando_pagamento'=>['pagamento_confirmado','cancelada','fraude','inelegivel'],'pagamento_confirmado'=>['em_confirmacao','cancelada','fraude'],'em_confirmacao'=>['aprovada','cancelada','fraude','inelegivel']];
    private const CREDITO=['pendente'=>['em_confirmacao','cancelado'],'em_confirmacao'=>['liberado','cancelado'],'liberado'=>['bloqueado','reservado','cancelado','expirado'],'bloqueado'=>['liberado','cancelado','expirado'],'reservado'=>['liberado','utilizado','cancelado']];
    private const CODIGO=['nao_liberado'=>['ativo','cancelado'],'ativo'=>['suspenso','cancelado'],'suspenso'=>['ativo','cancelado']];
    public static function validar($tipo,$anterior,$novo): void
    {
        $mapas=['indicacao'=>self::INDICACAO,'credito'=>self::CREDITO,'codigo'=>self::CODIGO];
        if(!isset($mapas[$tipo][$anterior]) || !in_array($novo,$mapas[$tipo][$anterior],true)) throw new \DomainException("Transição inválida de {$anterior} para {$novo}.");
    }
}
