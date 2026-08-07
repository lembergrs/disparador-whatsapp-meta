<?php
namespace Models;
use Core\Database;
use PDO;
class IndicacaoAuditoria{
    private $db; public function __construct(){ $this->db=Database::getInstance(); }
    public function registrar(array $d){$s=$this->db->prepare('INSERT INTO indicacao_auditoria (IAU_Entidade,IAU_EntidadeID,IAU_Acao,IAU_StatusAnterior,IAU_StatusNovo,IAU_Motivo,USU_ID,IAU_Correlacao,IAU_Dados) VALUES (?,?,?,?,?,?,?,?,?)');return $s->execute([$d['entidade'],$d['entidade_id'],$d['acao'],$d['status_anterior']??null,$d['status_novo']??null,$d['motivo']??null,$d['usuario_id']??null,$d['correlacao']??null,isset($d['dados'])?json_encode($d['dados'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):null]);}
    public function listar($entidade,$id){$s=$this->db->prepare('SELECT * FROM indicacao_auditoria WHERE IAU_Entidade=? AND IAU_EntidadeID=? ORDER BY IAU_ID ASC');$s->execute([$entidade,$id]);return $s->fetchAll(PDO::FETCH_ASSOC);}
}
