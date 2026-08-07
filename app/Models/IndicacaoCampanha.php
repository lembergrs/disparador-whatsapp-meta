<?php
namespace Models;
use Core\Database;
use PDO;
class IndicacaoCampanha{
    private $db; public function __construct(){ $this->db=Database::getInstance(); }
    public function buscar($id){$s=$this->db->prepare('SELECT * FROM indicacao_campanhas WHERE ICP_ID=?');$s->execute([$id]);return $s->fetch(PDO::FETCH_ASSOC);}
    public function buscarPublicaAtiva($forUpdate=false){$sql="SELECT * FROM indicacao_campanhas WHERE ICP_Ativo='S' AND ICP_Publica='S' LIMIT 1".($forUpdate?' FOR UPDATE':'');$s=$this->db->query($sql);return $s->fetch(PDO::FETCH_ASSOC);}
    public function criar(array $d){$s=$this->db->prepare('INSERT INTO indicacao_campanhas (ICP_Nome,ICP_Descricao,ICP_Percentual,ICP_DataInicio,ICP_DataFim,ICP_Ativo,ICP_Publica,ICP_RegrasSnapshot,ICP_CriadoPor_USU_ID) VALUES (?,?,?,?,?,?,?,?,?)');$s->execute([$d['nome'],$d['descricao']??null,$d['percentual'],$d['data_inicio']??null,$d['data_fim']??null,$d['ativo']??'N',$d['publica']??'N',isset($d['regras'])?json_encode($d['regras'],JSON_UNESCAPED_UNICODE):null,$d['usuario_id']??null]);return (int)$this->db->lastInsertId();}
    public function atualizarStatus($id,$ativo,$publica=null){$sql='UPDATE indicacao_campanhas SET ICP_Ativo=?'.($publica!==null?', ICP_Publica=?':'').' WHERE ICP_ID=?';$args=$publica!==null?[$ativo,$publica,$id]:[$ativo,$id];$s=$this->db->prepare($sql);return $s->execute($args);}
}
