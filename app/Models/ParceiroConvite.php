<?php
namespace Models;

use Core\Database;
use PDO;

class ParceiroConvite
{
    private $db;
    public function __construct($db=null){ $this->db=$db ?: Database::getInstance(); }

    public function parceiroPorCliente($clienteId)
    {
        $q=$this->db->prepare("SELECT p.* FROM parceiros_api p INNER JOIN clientes c ON c.CLI_ID=p.CLI_ID WHERE p.CLI_ID=? AND p.PAR_Ativo='S' AND p.PAR_StatusCadastro='aprovado' AND c.CLI_TipoConta='cliente_partner' LIMIT 1");
        $q->execute([(int)$clienteId]);
        return $q->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function gerar($parceiroId,$nomeReferencia='')
    {
        $token=bin2hex(random_bytes(32));
        $hash=hash('sha256',$token);
        $q=$this->db->prepare("INSERT INTO parceiro_convites (PAR_ID,PCI_NomeReferencia,PCI_TokenHash,PCI_ExpiraEm) VALUES (?,?,?,DATE_ADD(NOW(),INTERVAL 30 DAY))");
        $q->execute([(int)$parceiroId,trim((string)$nomeReferencia) ?: null,$hash]);
        return $token;
    }

    public function listar($parceiroId)
    {
        $q=$this->db->prepare("SELECT i.*,c.CLI_Nome,c.CLI_Email FROM parceiro_convites i LEFT JOIN clientes c ON c.CLI_ID=i.CLI_ID WHERE i.PAR_ID=? ORDER BY i.PCI_ID DESC");
        $q->execute([(int)$parceiroId]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarClientes($parceiroId)
    {
        $q=$this->db->prepare("SELECT DISTINCT c.CLI_ID,c.CLI_Nome,c.CLI_Email,i.PCI_AceitoEm,
            (SELECT COUNT(*) FROM parceiro_clientes pc WHERE pc.PAR_ID=i.PAR_ID AND pc.CLI_ID=c.CLI_ID AND pc.PAC_Ativo='S') total_canais
            FROM parceiro_convites i INNER JOIN clientes c ON c.CLI_ID=i.CLI_ID
            WHERE i.PAR_ID=? AND i.PCI_Status='aceito' ORDER BY c.CLI_Nome");
        $q->execute([(int)$parceiroId]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarClienteGerenciavel($parceiroId,$clienteId)
    {
        $q=$this->db->prepare("SELECT c.CLI_ID,c.CLI_Nome,c.CLI_Email FROM parceiro_convites i INNER JOIN clientes c ON c.CLI_ID=i.CLI_ID WHERE i.PAR_ID=? AND i.CLI_ID=? AND i.PCI_Status='aceito' AND c.CLI_TipoConta='cliente_partner_vinculado' AND c.CLI_Ativo='S' AND c.CLI_StatusCadastro='ativo' LIMIT 1");
        $q->execute([(int)$parceiroId,(int)$clienteId]);
        return $q->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function listarCanaisGerenciaveis($parceiroId,$clienteId)
    {
        $q=$this->db->prepare("SELECT DISTINCT m.MTA_ID,m.MTA_Nome,m.MTA_NumeroTelefone,m.MTA_Status FROM parceiro_clientes pc INNER JOIN meta_contas m ON m.MTA_ID=pc.MTA_ID AND m.CLI_ID=pc.CLI_ID WHERE pc.PAR_ID=? AND pc.CLI_ID=? AND pc.PAC_Ativo='S' AND m.MTA_Ativo='S' ORDER BY m.MTA_ID DESC");
        $q->execute([(int)$parceiroId,(int)$clienteId]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarCanalGerenciavel($parceiroId,$clienteId,$metaId)
    {
        $q=$this->db->prepare("SELECT m.* FROM parceiro_clientes pc INNER JOIN meta_contas m ON m.MTA_ID=pc.MTA_ID AND m.CLI_ID=pc.CLI_ID WHERE pc.PAR_ID=? AND pc.CLI_ID=? AND pc.MTA_ID=? AND pc.PAC_Ativo='S' AND m.MTA_Ativo='S' LIMIT 1");
        $q->execute([(int)$parceiroId,(int)$clienteId,(int)$metaId]);
        return $q->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function buscarPorToken($token)
    {
        $hash=hash('sha256',(string)$token);
        $q=$this->db->prepare("SELECT i.*,p.PAR_Nome,p.PAR_Ativo,p.PAR_StatusCadastro FROM parceiro_convites i INNER JOIN parceiros_api p ON p.PAR_ID=i.PAR_ID WHERE i.PCI_TokenHash=? LIMIT 1");
        $q->execute([$hash]);
        $r=$q->fetch(PDO::FETCH_ASSOC);
        if(!$r || $r['PCI_Status']!=='pendente' || $r['PAR_Ativo']!=='S' || $r['PAR_StatusCadastro']!=='aprovado' || strtotime($r['PCI_ExpiraEm'])<time()){ return null; }
        return $r;
    }

    public function aceitar($conviteId,$clienteId)
    {
        $q=$this->db->prepare("UPDATE parceiro_convites SET CLI_ID=?,PCI_Status='aceito',PCI_AceitoEm=NOW() WHERE PCI_ID=? AND PCI_Status='pendente' AND PCI_ExpiraEm>NOW()");
        $q->execute([(int)$clienteId,(int)$conviteId]);
        if($q->rowCount()!==1){ throw new \RuntimeException('Convite inválido, expirado ou já utilizado.'); }
    }

    public function vincularContaDoCliente($clienteId,$metaId,$status='onboarding')
    {
        $q=$this->db->prepare("SELECT PAR_ID FROM parceiro_convites WHERE CLI_ID=? AND PCI_Status='aceito' ORDER BY PCI_ID DESC LIMIT 1");
        $q->execute([(int)$clienteId]); $parceiroId=(int)$q->fetchColumn();
        if(!$parceiroId){ return false; }
        $faturavel=$status==='ativo' ? 'NOW()' : 'NULL';
        $sql="INSERT INTO parceiro_clientes (PAR_ID,CLI_ID,MTA_ID,PAC_Ativo,PAC_Status,PAC_FaturavelDesde) VALUES (?,?,?,'S',?,".$faturavel.")
              ON DUPLICATE KEY UPDATE PAC_Ativo='S',PAC_Status=VALUES(PAC_Status),PAC_FaturavelDesde=IF(PAC_FaturavelDesde IS NULL,VALUES(PAC_FaturavelDesde),PAC_FaturavelDesde),PAC_FaturavelAte=NULL";
        $this->db->prepare($sql)->execute([$parceiroId,(int)$clienteId,(int)$metaId,$status]);
        return true;
    }
}
