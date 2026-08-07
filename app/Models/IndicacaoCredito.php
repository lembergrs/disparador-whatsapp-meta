<?php
namespace Models;
use Core\Database;
use PDO;
class IndicacaoCredito{
    private $db; public function __construct(){ $this->db=Database::getInstance(); }
    public function buscar($id){$s=$this->db->prepare('SELECT * FROM indicacao_creditos WHERE ICR_ID=?');$s->execute([$id]);return $s->fetch(PDO::FETCH_ASSOC);}
    public function buscarPorIndicacao($indicacaoId){$s=$this->db->prepare('SELECT * FROM indicacao_creditos WHERE IND_ID=? LIMIT 1');$s->execute([$indicacaoId]);return $s->fetch(PDO::FETCH_ASSOC);}
    public function criar(array $d){$s=$this->db->prepare('INSERT INTO indicacao_creditos (IND_ID,CLI_Indicador_ID,ICP_ID,ICR_Percentual,ICR_Status) VALUES (?,?,?,?,?)');$s->execute([$d['indicacao_id'],$d['indicador_id'],$d['campanha_id'],$d['percentual'],$d['status']??'pendente']);return (int)$this->db->lastInsertId();}
    public function listarFIFO($indicadorId,$limite=100){$limite=max(1,min(500,(int)$limite));$s=$this->db->prepare("SELECT * FROM indicacao_creditos WHERE CLI_Indicador_ID=? AND ICR_Status='liberado' ORDER BY ICR_LiberadoEm ASC, ICR_ID ASC LIMIT {$limite}");$s->execute([$indicadorId]);return $s->fetchAll(PDO::FETCH_ASSOC);}
    public function alterarStatus($id,$anterior,$novo,array $datas=[]){$sets=['ICR_Status=?'];$args=[$novo];$map=['ICR_LiberadoEm'=>'liberado_em','ICR_BloqueadoEm'=>'bloqueado_em','ICR_CanceladoEm'=>'cancelado_em','ICR_ExpiradoEm'=>'expirado_em','ICR_UtilizadoEm'=>'utilizado_em'];foreach($map as $c=>$k){if(array_key_exists($k,$datas)){$sets[]="$c=?";$args[]=$datas[$k];}}$args[]=$id;$args[]=$anterior;$s=$this->db->prepare('UPDATE indicacao_creditos SET '.implode(',',$sets).' WHERE ICR_ID=? AND ICR_Status=?');$s->execute($args);return $s->rowCount()===1;}
}
