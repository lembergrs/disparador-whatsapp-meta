<?php
namespace Models;
use Core\Database; use PDO;
class IndicacaoCredito
{
 private $db; public function __construct($db=null){$this->db=$db?:Database::getInstance();}
 public function criar(array $d): int {$s=$this->db->prepare("INSERT INTO indicacao_creditos (IND_ID,CLI_Indicador_ID,ICP_ID,ICR_Percentual,ICR_Status) VALUES (?,?,?,?,'pendente')");$s->execute([(int)$d['indicacao_id'],(int)$d['indicador_id'],(int)$d['campanha_id'],$d['percentual']]);return (int)$this->db->lastInsertId();}
 public function buscar($id){$s=$this->db->prepare('SELECT * FROM indicacao_creditos WHERE ICR_ID=?');$s->execute([(int)$id]);return $s->fetch(PDO::FETCH_ASSOC)?:null;}
 public function listarDisponiveisFifo($cliente,$limite=100): array {$limite=max(1,min(500,(int)$limite));$s=$this->db->prepare("SELECT * FROM indicacao_creditos WHERE CLI_Indicador_ID=? AND ICR_Status='liberado' ORDER BY ICR_LiberadoEm,ICR_ID LIMIT {$limite}");$s->execute([(int)$cliente]);return $s->fetchAll(PDO::FETCH_ASSOC);}
 public function status($id,$anterior,$novo): bool {$campos=['liberado'=>'ICR_LiberadoEm','bloqueado'=>'ICR_BloqueadoEm','cancelado'=>'ICR_CanceladoEm','expirado'=>'ICR_ExpiradoEm','utilizado'=>'ICR_UtilizadoEm'];$extra=isset($campos[$novo])?",{$campos[$novo]}=CURRENT_TIMESTAMP":'';$s=$this->db->prepare("UPDATE indicacao_creditos SET ICR_Status=?{$extra} WHERE ICR_ID=? AND ICR_Status=?");$s->execute([$novo,(int)$id,$anterior]);return $s->rowCount()===1;}
}
