<?php

namespace Services\Indicacao;

use Core\Database;
use Models\IndicacaoCreditoReserva;
use PDO;

class IndicacaoCreditoReservaService
{
    private $db;private $reservas;private $creditos;private $auditoria;
    public function __construct(PDO $db=null,IndicacaoCreditoReserva $reservas=null,IndicacaoCreditoService $creditos=null,IndicacaoAuditoriaService $auditoria=null){$this->db=$db?:Database::getInstance();$this->reservas=$reservas?:new IndicacaoCreditoReserva($this->db);$this->creditos=$creditos?:new IndicacaoCreditoService(null,null,null,null,$this->db);$this->auditoria=$auditoria?:new IndicacaoAuditoriaService();}
    public function criar(array $dados):int{return $this->transacao(function()use($dados){$id=$this->reservas->inserir($dados);$this->auditoria->registrar('credito_reserva',$id,'reserva_criada',null,'reservada');$this->creditos->alterarStatus($dados['credito_id'],'reservado');return $id;});}
    public function utilizar(array $reservas):void{$this->transacao(function()use($reservas){foreach($reservas as $reserva){IndicacaoReservaStatusService::validar($reserva['ICRR_Status'],'utilizada');if(!$this->reservas->transicionar($reserva['ICRR_ID'],'reservada','utilizada'))throw new \RuntimeException('Reserva alterada concorrentemente.');$this->auditoria->registrar('credito_reserva',$reserva['ICRR_ID'],'reserva_utilizada','reservada','utilizada');$this->creditos->alterarStatus($reserva['ICR_ID'],'utilizado');}});}
    public function liberar(array $reservas,$motivo):void{$this->transacao(function()use($reservas,$motivo){foreach($reservas as $reserva){IndicacaoReservaStatusService::validar($reserva['ICRR_Status'],'liberada');if(!$this->reservas->transicionar($reserva['ICRR_ID'],'reservada','liberada'))throw new \RuntimeException('Reserva alterada concorrentemente.');$this->auditoria->registrar('credito_reserva',$reserva['ICRR_ID'],'reserva_liberada','reservada','liberada',$motivo);$this->creditos->alterarStatus($reserva['ICR_ID'],'liberado',null,$motivo);}});}
    private function transacao(callable $callback){$propria=!$this->db->inTransaction();if($propria)$this->db->beginTransaction();try{$r=$callback();if($propria)$this->db->commit();return$r;}catch(\Throwable $e){if($propria&&$this->db->inTransaction())$this->db->rollBack();throw$e;}}
}
