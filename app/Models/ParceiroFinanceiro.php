<?php
namespace Models;

use Core\Database;
use PDO;

class ParceiroFinanceiro
{
    private $db;
    public function __construct($db=null){ $this->db=$db ?: Database::getInstance(); }

    public function contarClientesFaturaveis($parceiroId, $dataReferencia=null)
    {
        $data=$dataReferencia ?: date('Y-m-d H:i:s');
        $sql=$this->db->prepare("SELECT COUNT(DISTINCT CLI_ID) FROM parceiro_clientes WHERE PAR_ID=? AND PAC_Status='ativo' AND PAC_Ativo='S' AND PAC_FaturavelDesde IS NOT NULL AND PAC_FaturavelDesde<=? AND (PAC_FaturavelAte IS NULL OR PAC_FaturavelAte>?)");
        $sql->execute([(int)$parceiroId,$data,$data]);
        return (int)$sql->fetchColumn();
    }

    public function planoPorQuantidade($quantidade)
    {
        $sql=$this->db->prepare("SELECT * FROM parceiro_planos WHERE PPL_Ativo='S' AND PPL_MinClientes<=? AND (PPL_MaxClientes IS NULL OR PPL_MaxClientes>=?) ORDER BY PPL_MinClientes DESC LIMIT 1");
        $sql->execute([(int)$quantidade,(int)$quantidade]);
        return $sql->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function assinaturaAtiva($parceiroId)
    {
        $sql=$this->db->prepare("SELECT * FROM parceiro_assinaturas WHERE PAR_ID=? AND PAS_Status IN ('ativa','pendente') ORDER BY CASE PAS_Status WHEN 'ativa' THEN 1 ELSE 2 END,PAS_ID DESC LIMIT 1");
        $sql->execute([(int)$parceiroId]);
        return $sql->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function registrarCompetencia($assinaturaId,$parceiroId,$competencia,$quantidade,array $plano)
    {
        $sql=$this->db->prepare("INSERT INTO parceiro_faturamento_competencias (PAS_ID,PAR_ID,PFC_Competencia,PFC_ClientesFaturaveis,PPL_ID,PFC_Valor,PFC_Status) VALUES (?,?,?,?,?,?,'calculada') ON DUPLICATE KEY UPDATE PFC_ID=LAST_INSERT_ID(PFC_ID)");
        $sql->execute([(int)$assinaturaId,(int)$parceiroId,$competencia,(int)$quantidade,(int)$plano['PPL_ID'],$plano['PPL_Valor']]);
        return (int)$this->db->lastInsertId();
    }

    public function buscarCompetencia($id)
    {
        $sql=$this->db->prepare("SELECT * FROM parceiro_faturamento_competencias WHERE PFC_ID=? LIMIT 1");
        $sql->execute([(int)$id]);
        return $sql->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function vincularCobrancaCompetencia($competenciaId,$cobrancaId)
    {
        return $this->db->prepare("UPDATE parceiro_faturamento_competencias SET COB_ID=?,PFC_Status='cobrada' WHERE PFC_ID=? AND COB_ID IS NULL")->execute([(int)$cobrancaId,(int)$competenciaId]);
    }

    public function marcarClienteFaturavel($parceiroId,$vinculoId)
    {
        return $this->db->prepare("UPDATE parceiro_clientes SET PAC_Status='ativo',PAC_FaturavelDesde=COALESCE(PAC_FaturavelDesde,NOW()),PAC_FaturavelAte=NULL WHERE PAR_ID=? AND PAC_ID=? AND PAC_Ativo='S'")->execute([(int)$parceiroId,(int)$vinculoId]);
    }

    public function encerrarClienteFaturavel($parceiroId,$vinculoId,$status='cancelado')
    {
        if(!in_array($status,['suspenso','cancelado'],true)){ $status='cancelado'; }
        return $this->db->prepare("UPDATE parceiro_clientes SET PAC_Status=?,PAC_FaturavelAte=COALESCE(PAC_FaturavelAte,NOW()) WHERE PAR_ID=? AND PAC_ID=?")->execute([$status,(int)$parceiroId,(int)$vinculoId]);
    }
}
