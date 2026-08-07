<?php

namespace Models;

use Core\Database;
use PDO;

class IndicacaoCreditoReserva
{
    private $db;
    public function __construct($db=null){$this->db=$db?:Database::getInstance();}
    public function inserir(array $dados):int{$s=$this->db->prepare("INSERT INTO indicacao_credito_reservas (ICR_ID,ICRR_ReferenciaTipo,ICRR_ReferenciaID,ICRR_Ciclo,ICRR_MesesCiclo,ICRR_ValorBaseCicloCentavos,ICRR_MensalidadeEquivalenteCentavos,ICRR_Percentual,ICRR_DescontoCentavos,ICRR_Status,ICRR_ReservadoEm) VALUES (?,?,?,?,?,?,?,?,?,'reservada',?)");$s->execute([(int)$dados['credito_id'],$dados['referencia_tipo'],$dados['referencia_id'],$dados['ciclo'],(int)$dados['meses_ciclo'],(int)$dados['valor_base_centavos'],(int)$dados['mensalidade_equivalente_centavos'],$dados['percentual'],(int)$dados['desconto_centavos'],$dados['reservado_em']]);return (int)$this->db->lastInsertId();}
    public function buscar($id,$lock=false){$s=$this->db->prepare('SELECT * FROM indicacao_credito_reservas WHERE ICRR_ID=?'.$this->forUpdate($lock));$s->execute([(int)$id]);return $s->fetch(PDO::FETCH_ASSOC)?:null;}
    public function listarPorReferencia($tipo,$id,$lock=false){$s=$this->db->prepare('SELECT * FROM indicacao_credito_reservas WHERE ICRR_ReferenciaTipo=? AND ICRR_ReferenciaID=? ORDER BY ICRR_ID'.$this->forUpdate($lock));$s->execute([$tipo,$id]);return $s->fetchAll(PDO::FETCH_ASSOC);}
    public function listarPorCredito($creditoId,$lock=false){$s=$this->db->prepare('SELECT * FROM indicacao_credito_reservas WHERE ICR_ID=? ORDER BY ICRR_ID'.$this->forUpdate($lock));$s->execute([(int)$creditoId]);return $s->fetchAll(PDO::FETCH_ASSOC);}
    public function transicionar($id,$anterior,$novo){$campos=['utilizada'=>'ICRR_UtilizadoEm','liberada'=>'ICRR_LiberadoEm','cancelada'=>'ICRR_CanceladoEm'];$extra=isset($campos[$novo])?",{$campos[$novo]}=CURRENT_TIMESTAMP":'';$s=$this->db->prepare("UPDATE indicacao_credito_reservas SET ICRR_Status=?{$extra} WHERE ICRR_ID=? AND ICRR_Status=?");$s->execute([$novo,(int)$id,$anterior]);return $s->rowCount()===1;}
    public function adquirirLockReferencia($tipo,$id,$timeout=10){if($this->driver()!=='mysql')return true;$chave='indicacao_reserva:'.hash('sha256',$tipo.':'.$id);$s=$this->db->prepare('SELECT GET_LOCK(?,?)');$s->execute([$chave,max(0,(int)$timeout)]);return (int)$s->fetchColumn()===1;}
    public function liberarLockReferencia($tipo,$id){if($this->driver()!=='mysql')return;$chave='indicacao_reserva:'.hash('sha256',$tipo.':'.$id);$s=$this->db->prepare('SELECT RELEASE_LOCK(?)');$s->execute([$chave]);}
    private function forUpdate($lock){return $lock&&$this->driver()==='mysql'?' FOR UPDATE':'';}
    private function driver(){return $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);}
}
