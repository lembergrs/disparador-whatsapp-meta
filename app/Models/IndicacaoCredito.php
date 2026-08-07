<?php
namespace Models;
use Core\Database;
use PDO;
class IndicacaoCredito{
    private $db; public function __construct($db=null){ $this->db=$db?:Database::getInstance(); }
    public function buscar($id){$s=$this->db->prepare('SELECT * FROM indicacao_creditos WHERE ICR_ID=?');$s->execute([(int)$id]);return $s->fetch(PDO::FETCH_ASSOC)?:null;}
    public function buscarPorIndicacao($indicacaoId,$forUpdate=false){$sql='SELECT * FROM indicacao_creditos WHERE IND_ID=? LIMIT 1';if($forUpdate&&$this->driver()==='mysql')$sql.=' FOR UPDATE';$s=$this->db->prepare($sql);$s->execute([(int)$indicacaoId]);return $s->fetch(PDO::FETCH_ASSOC)?:null;}
    public function criar(array $d){$s=$this->db->prepare('INSERT INTO indicacao_creditos (IND_ID,CLI_Indicador_ID,ICP_ID,ICR_Percentual,ICR_Status) VALUES (?,?,?,?,?)');$s->execute([(int)$d['indicacao_id'],(int)$d['indicador_id'],(int)$d['campanha_id'],$d['percentual'],$d['status']??'pendente']);return (int)$this->db->lastInsertId();}
    public function listarFIFO($indicadorId,$limite=100){$limite=max(1,min(500,(int)$limite));$s=$this->db->prepare("SELECT * FROM indicacao_creditos WHERE CLI_Indicador_ID=? AND ICR_Status='liberado' ORDER BY ICR_LiberadoEm ASC, ICR_ID ASC LIMIT {$limite}");$s->execute([(int)$indicadorId]);return $s->fetchAll(PDO::FETCH_ASSOC);}
    public function selecionarDisponiveisFifo($indicadorId,$limite,$forUpdate=true){$limite=max(1,min(12,(int)$limite));$sql="SELECT * FROM indicacao_creditos WHERE CLI_Indicador_ID=? AND ICR_Status='liberado' ORDER BY ICR_LiberadoEm ASC, ICR_ID ASC LIMIT {$limite}";if($forUpdate&&$this->driver()==='mysql')$sql.=' FOR UPDATE';$s=$this->db->prepare($sql);$s->execute([(int)$indicadorId]);return $s->fetchAll(PDO::FETCH_ASSOC);}
    public function alterarStatus($id,$anterior,$novo,array $datas=[]){$sets=['ICR_Status=?'];$args=[$novo];$map=['ICR_LiberadoEm'=>'liberado_em','ICR_BloqueadoEm'=>'bloqueado_em','ICR_CanceladoEm'=>'cancelado_em','ICR_ExpiradoEm'=>'expirado_em','ICR_UtilizadoEm'=>'utilizado_em'];foreach($map as $c=>$k){if(array_key_exists($k,$datas)){$sets[]="$c=?";$args[]=$datas[$k];}}$args[]=(int)$id;$args[]=$anterior;$s=$this->db->prepare('UPDATE indicacao_creditos SET '.implode(',',$sets).' WHERE ICR_ID=? AND ICR_Status=?');$s->execute($args);return $s->rowCount()===1;}
    private function driver(){return $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);}
}
