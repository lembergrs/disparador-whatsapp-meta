<?php

namespace Services;

use Models\IndicacaoCreditoReserva;
use PDO;
use Services\Indicacao\IndicacaoReservaStatusService;

class IndicacaoCreditoReservaService
{
    private $db;
    private $reservas;
    private $creditos;
    private $auditoria;

    public function __construct(PDO $db, IndicacaoCreditoReserva $reservas, IndicacaoCreditoService $creditos, IndicacaoAuditoriaService $auditoria)
    {
        $this->db = $db;
        $this->reservas = $reservas;
        $this->creditos = $creditos;
        $this->auditoria = $auditoria;
    }

    public function criar(array $dados): int
    {
        return $this->transacao(function() use ($dados){
            $id = $this->reservas->inserir($dados);
            $this->auditar($id, 'reserva_criada', null, 'reservada');
            $this->creditos->transicionar($dados['credito_id'], 'reservado');
            return $id;
        });
    }

    public function utilizar(array $reservas): void
    {
        $this->transacao(function() use ($reservas){
            foreach($reservas as $reserva){
                IndicacaoReservaStatusService::validar($reserva['ICRR_Status'], 'utilizada');
                if(!$this->reservas->transicionar($reserva['ICRR_ID'], 'reservada', 'utilizada')) throw new \RuntimeException('Reserva alterada concorrentemente.');
                $this->auditar($reserva['ICRR_ID'], 'reserva_utilizada', 'reservada', 'utilizada');
                $this->creditos->transicionar($reserva['ICR_ID'], 'utilizado');
            }
        });
    }

    public function liberar(array $reservas, $motivo): void
    {
        $this->transacao(function() use ($reservas, $motivo){
            foreach($reservas as $reserva){
                IndicacaoReservaStatusService::validar($reserva['ICRR_Status'], 'liberada');
                if(!$this->reservas->transicionar($reserva['ICRR_ID'], 'reservada', 'liberada')) throw new \RuntimeException('Reserva alterada concorrentemente.');
                $this->auditar($reserva['ICRR_ID'], 'reserva_liberada', 'reservada', 'liberada', $motivo);
                $this->creditos->transicionar($reserva['ICR_ID'], 'liberado', $motivo);
            }
        });
    }

    private function auditar($id, $acao, $anterior, $novo, $motivo = null): void
    {
        $this->auditoria->registrar(['entidade'=>'credito_reserva','entidade_id'=>(int)$id,'acao'=>$acao,'status_anterior'=>$anterior,'status_novo'=>$novo,'motivo'=>$motivo]);
    }

    private function transacao(callable $callback)
    {
        $propria = !$this->db->inTransaction();
        if($propria) $this->db->beginTransaction();
        try{
            $resultado = $callback();
            if($propria) $this->db->commit();
            return $resultado;
        }catch(\Throwable $e){
            if($propria && $this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }
}
