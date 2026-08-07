<?php
namespace Models;
use Core\Database; use PDO;
class IndicacaoAuditoria
{
 private $db; public function __construct($db=null){$this->db=$db?:Database::getInstance();}
 public function registrar(array $d): int {$sql=$this->db->prepare('INSERT INTO indicacao_auditoria (IAU_Entidade,IAU_EntidadeID,IAU_Acao,IAU_StatusAnterior,IAU_StatusNovo,IAU_Motivo,USU_ID,IAU_Correlacao,IAU_Dados) VALUES (?,?,?,?,?,?,?,?,?)');$sql->execute([$d['entidade'],(int)$d['entidade_id'],$d['acao'],$d['status_anterior']??null,$d['status_novo']??null,$d['motivo']??null,$d['usuario_id']??null,$d['correlacao']??null,$d['dados']??null]);return (int)$this->db->lastInsertId();}
 public function listar($entidade,$id): array {$s=$this->db->prepare('SELECT * FROM indicacao_auditoria WHERE IAU_Entidade=? AND IAU_EntidadeID=? ORDER BY IAU_ID');$s->execute([$entidade,(int)$id]);return $s->fetchAll(PDO::FETCH_ASSOC);}
}
