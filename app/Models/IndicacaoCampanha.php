<?php
namespace Models;
use Core\Database; use PDO;
class IndicacaoCampanha
{
 private $db; public function __construct($db=null){$this->db=$db?:Database::getInstance();}
 public function criar(array $d): int {$s=$this->db->prepare("INSERT INTO indicacao_campanhas (ICP_Nome,ICP_Descricao,ICP_Percentual,ICP_DataInicio,ICP_DataFim,ICP_Ativo,ICP_Publica,ICP_RegrasSnapshot,ICP_CriadoPor_USU_ID) VALUES (?,?,?,?,?,'N',?,?,?)");$s->execute([$d['nome'],$d['descricao']??null,$d['percentual'],$d['data_inicio'],$d['data_fim']??null,$d['publica']?'S':'N',$d['regras_snapshot'],$d['usuario_id']??null]);return (int)$this->db->lastInsertId();}
 public function buscar($id,$lock=false){$s=$this->db->prepare('SELECT * FROM indicacao_campanhas WHERE ICP_ID=?'.($lock&&$this->driver()==='mysql'?' FOR UPDATE':''));$s->execute([(int)$id]);return $s->fetch(PDO::FETCH_ASSOC)?:null;}
 public function buscarPublicaAtiva($lock=false){$s=$this->db->prepare("SELECT * FROM indicacao_campanhas WHERE ICP_Ativo='S' AND ICP_Publica='S' LIMIT 1".($lock&&$this->driver()==='mysql'?' FOR UPDATE':''));$s->execute();return $s->fetch(PDO::FETCH_ASSOC)?:null;}
 public function atualizar($id,array $d): bool {$s=$this->db->prepare('UPDATE indicacao_campanhas SET ICP_Nome=?,ICP_Descricao=?,ICP_Percentual=?,ICP_DataInicio=?,ICP_DataFim=?,ICP_RegrasSnapshot=? WHERE ICP_ID=?');$s->execute([$d['nome'],$d['descricao']??null,$d['percentual'],$d['data_inicio'],$d['data_fim']??null,$d['regras_snapshot'],(int)$id]);return $s->rowCount()===1;}
 public function possuiIndicacoes($id): bool {$s=$this->db->prepare('SELECT 1 FROM indicacoes WHERE ICP_ID=? LIMIT 1');$s->execute([(int)$id]);return (bool)$s->fetchColumn();}
 public function status($id,$ativo): bool {$s=$this->db->prepare('UPDATE indicacao_campanhas SET ICP_Ativo=? WHERE ICP_ID=?');$s->execute([$ativo?'S':'N',(int)$id]);return $s->rowCount()===1;}
 private function driver(){return $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);}
}
