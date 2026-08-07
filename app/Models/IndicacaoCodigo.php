<?php
namespace Models;
use Core\Database; use PDO;
class IndicacaoCodigo
{
 private $db; public function __construct($db=null){$this->db=$db?:Database::getInstance();}
 public function criar(array $d): int {$s=$this->db->prepare("INSERT INTO indicacao_codigos (CLI_ID,ICP_ID,ICD_Codigo,ICD_CodigoNormalizado,ICD_Status) VALUES (?,?,?,?,'nao_liberado')");$s->execute([(int)$d['cliente_id'],(int)$d['campanha_id'],$d['codigo'],$d['normalizado']]);return (int)$this->db->lastInsertId();}
 public function existeNormalizado($c): bool {$s=$this->db->prepare('SELECT 1 FROM indicacao_codigos WHERE ICD_CodigoNormalizado=? LIMIT 1');$s->execute([$c]);return (bool)$s->fetchColumn();}
 public function buscar($id){$s=$this->db->prepare('SELECT * FROM indicacao_codigos WHERE ICD_ID=?');$s->execute([(int)$id]);return $s->fetch(PDO::FETCH_ASSOC)?:null;}
 public function buscarNormalizado($c,$lock=false){$s=$this->db->prepare('SELECT * FROM indicacao_codigos WHERE ICD_CodigoNormalizado=?'.($lock&&$this->driver()==='mysql'?' FOR UPDATE':''));$s->execute([$c]);return $s->fetch(PDO::FETCH_ASSOC)?:null;}
 public function status($id,$anterior,$novo): bool {$datas=['ativo'=>'ICD_LiberadoEm','suspenso'=>'ICD_SuspensoEm','cancelado'=>'ICD_CanceladoEm'];$extra=isset($datas[$novo])?",{$datas[$novo]}=CURRENT_TIMESTAMP":'';$s=$this->db->prepare("UPDATE indicacao_codigos SET ICD_Status=?{$extra} WHERE ICD_ID=? AND ICD_Status=?");$s->execute([$novo,(int)$id,$anterior]);return $s->rowCount()===1;}
 private function driver(){return $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);}
}
