<?php
namespace Models;
use Core\Database; use PDO;
class Indicacao
{
 private $db; public function __construct($db=null){$this->db=$db?:Database::getInstance();}
 public function criar(array $d): int {$s=$this->db->prepare("INSERT INTO indicacoes (ICD_ID,ICP_ID,CLI_Indicador_ID,CLI_Indicado_ID,IND_PercentualSnapshot,IND_Origem,IND_Status,IND_CadastradaEm) VALUES (?,?,?,?,?,?,'cadastrada',?)");$s->execute([(int)$d['codigo_id'],(int)$d['campanha_id'],(int)$d['indicador_id'],(int)$d['indicado_id'],$d['percentual'],$d['origem'],$d['cadastrada_em']]);return (int)$this->db->lastInsertId();}
 public function buscar($id){$s=$this->db->prepare('SELECT * FROM indicacoes WHERE IND_ID=?');$s->execute([(int)$id]);return $s->fetch(PDO::FETCH_ASSOC)?:null;}
 public function status($id,$anterior,$novo,$motivo=null): bool {$campos=['pagamento_confirmado'=>'IND_PagamentoConfirmadoEm','aprovada'=>'IND_AprovadaEm','cancelada'=>'IND_CanceladaEm','fraude'=>'IND_FraudeEm','inelegivel'=>'IND_InelegivelEm'];$extra=isset($campos[$novo])?",{$campos[$novo]}=CURRENT_TIMESTAMP":'';$s=$this->db->prepare("UPDATE indicacoes SET IND_Status=?,IND_Motivo=?{$extra} WHERE IND_ID=? AND IND_Status=?");$s->execute([$novo,$motivo,(int)$id,$anterior]);return $s->rowCount()===1;}
}
