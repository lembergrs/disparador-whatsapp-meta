<?php
namespace Models;
use Core\Database;
use PDO;
class Indicacao{
    private $db; public function __construct(){ $this->db=Database::getInstance(); }
    public function buscar($id){$s=$this->db->prepare('SELECT * FROM indicacoes WHERE IND_ID=?');$s->execute([$id]);return $s->fetch(PDO::FETCH_ASSOC);}
    public function buscarPorIndicado($clienteId){$s=$this->db->prepare('SELECT * FROM indicacoes WHERE CLI_Indicado_ID=? LIMIT 1');$s->execute([$clienteId]);return $s->fetch(PDO::FETCH_ASSOC);}
    public function criar(array $d){$s=$this->db->prepare('INSERT INTO indicacoes (ICD_ID,ICP_ID,CLI_Indicador_ID,CLI_Indicado_ID,IND_PercentualSnapshot,IND_Origem,IND_Status) VALUES (?,?,?,?,?,?,?)');$s->execute([$d['codigo_id'],$d['campanha_id'],$d['indicador_id'],$d['indicado_id'],$d['percentual'],$d['origem'],$d['status']??'cadastrada']);return (int)$this->db->lastInsertId();}
    public function alterarStatus($id,$anterior,$novo,$motivo=null,array $datas=[]){$sets=['IND_Status=?','IND_Motivo=?'];$args=[$novo,$motivo];$map=['IND_PagamentoConfirmadoEm'=>'pagamento_confirmado_em','IND_ConfirmacaoAte'=>'confirmacao_ate','IND_AprovadaEm'=>'aprovada_em','IND_CanceladaEm'=>'cancelada_em','IND_FraudeEm'=>'fraude_em','IND_InelegivelEm'=>'inelegivel_em'];foreach($map as $c=>$k){if(array_key_exists($k,$datas)){$sets[]="$c=?";$args[]=$datas[$k];}}$args[]=$id;$args[]=$anterior;$s=$this->db->prepare('UPDATE indicacoes SET '.implode(',',$sets).' WHERE IND_ID=? AND IND_Status=?');$s->execute($args);return $s->rowCount()===1;}
}
