<?php

namespace Services\Indicacao;

class IndicacaoReservaStatusService
{
    private const TRANSICOES=['reservada'=>['utilizada','liberada','cancelada']];
    public static function validar($anterior,$novo):void{if(!isset(self::TRANSICOES[$anterior])||!in_array($novo,self::TRANSICOES[$anterior],true))throw new \DomainException("Transição de reserva inválida de {$anterior} para {$novo}.");}
}
