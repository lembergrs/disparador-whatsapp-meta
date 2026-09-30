<?php

namespace Models;

use Core\Database;
use PDO;

class ParceiroApi
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?: Database::getInstance();
    }

    public function listarAdmin()
    {
        $sql = $this->db->query("
            SELECT p.*, c.CLI_Nome,
                (SELECT COUNT(*) FROM parceiro_clientes pc WHERE pc.PAR_ID=p.PAR_ID AND pc.PAC_Ativo='S') AS total_canais,
                (SELECT COUNT(*) FROM parceiro_api_keys k WHERE k.PAR_ID=p.PAR_ID AND k.PAK_Ativo='S' AND k.PAK_RevogadaEm IS NULL) AS total_chaves
            FROM parceiros_api p
            INNER JOIN clientes c ON c.CLI_ID=p.CLI_ID
            ORDER BY p.PAR_ID DESC
        ");
        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarAdmin($id)
    {
        $sql = $this->db->prepare("SELECT p.*, c.CLI_Nome FROM parceiros_api p INNER JOIN clientes c ON c.CLI_ID=p.CLI_ID WHERE p.PAR_ID=? LIMIT 1");
        $sql->execute([(int)$id]);
        return $sql->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function listarClientesDisponiveisAdmin()
    {
        return $this->db->query("SELECT CLI_ID,CLI_Nome,CLI_TipoConta FROM clientes WHERE CLI_Ativo='S' AND CLI_TipoConta IN ('cliente_partner','cliente_partner_vinculado') ORDER BY CLI_Nome")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarClientesAutorizaveisAdmin()
    {
        return $this->db->query("
            SELECT CLI_ID,CLI_Nome,CLI_TipoConta
            FROM clientes
            WHERE CLI_Ativo='S'
              AND CLI_TipoConta IN ('cliente','cliente_partner_vinculado')
            ORDER BY CLI_Nome
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarContasClienteAdmin($clienteId)
    {
        $sql=$this->db->prepare("SELECT MTA_ID,MTA_Nome,MTA_NumeroTelefone,MTA_Status FROM meta_contas WHERE CLI_ID=? AND MTA_Ativo='S' ORDER BY MTA_ID DESC");
        $sql->execute([(int)$clienteId]);
        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    public function salvarParceiroAdmin($clienteId, $nome, $identificador, $webhookUrl = null)
    {
        $sql=$this->db->prepare("INSERT INTO parceiros_api (CLI_ID,PAR_Nome,PAR_Identificador,PAR_WebhookUrl,PAR_Ativo) VALUES (?,?,?,?, 'S')");
        $sql->execute([(int)$clienteId,trim($nome),trim($identificador),$webhookUrl ?: null]);
        return (int)$this->db->lastInsertId();
    }

    public function vincularCanalAdmin($parceiroId, $clienteId, $metaId, $identificadorExterno = null)
    {
        $sql=$this->db->prepare("
            INSERT INTO parceiro_clientes (PAR_ID,CLI_ID,MTA_ID,PAC_IdentificadorExterno,PAC_Ativo)
            SELECT ?,c.CLI_ID,m.MTA_ID,?,'S'
            FROM clientes c
            INNER JOIN meta_contas m ON m.CLI_ID=c.CLI_ID AND m.MTA_ID=? AND m.MTA_Ativo='S'
            WHERE c.CLI_ID=?
              AND c.CLI_TipoConta IN ('cliente','cliente_partner_vinculado')
              AND c.CLI_Ativo='S'
            ON DUPLICATE KEY UPDATE
                PAC_IdentificadorExterno=VALUES(PAC_IdentificadorExterno),
                PAC_Ativo='S'
        ");
        $sql->execute([(int)$parceiroId,$identificadorExterno ?: null,(int)$metaId,(int)$clienteId]);

        // Cliente normal só pode ser autorizado pelo admin para homologação.
        // Ele não vira cliente Partner, não entra no faturamento e mantém seu acesso original.
        $this->db->prepare("
            UPDATE parceiro_clientes pc
            INNER JOIN clientes c ON c.CLI_ID=pc.CLI_ID
            SET pc.PAC_Status='ativo',
                pc.PAC_FaturavelDesde=NULL,
                pc.PAC_FaturavelAte=NULL
            WHERE pc.PAR_ID=? AND pc.CLI_ID=? AND pc.MTA_ID=?
              AND c.CLI_TipoConta='cliente'
        ")->execute([(int)$parceiroId,(int)$clienteId,(int)$metaId]);
        if($sql->rowCount() < 1){ throw new \RuntimeException('Cliente/número inválido para vínculo partner.'); }
    }

    public function inativarVinculoAdmin($parceiroId, $vinculoId)
    {
        $sql=$this->db->prepare("UPDATE parceiro_clientes SET PAC_Ativo='N' WHERE PAC_ID=? AND PAR_ID=?");
        return $sql->execute([(int)$vinculoId,(int)$parceiroId]);
    }

    public function listarChavesAdmin($parceiroId)
    {
        $sql=$this->db->prepare("SELECT PAK_ID,PAK_Nome,PAK_Prefixo,PAK_UltimoUsoEm,PAK_ExpiraEm,PAK_RevogadaEm,PAK_Ativo,PAK_CriadoEm FROM parceiro_api_keys WHERE PAR_ID=? ORDER BY PAK_ID DESC");
        $sql->execute([(int)$parceiroId]);
        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    public function aprovarAdmin($parceiroId)
    {
        $sql=$this->db->prepare("UPDATE parceiros_api p INNER JOIN clientes c ON c.CLI_ID=p.CLI_ID SET p.PAR_StatusCadastro='aprovado',c.CLI_StatusCadastro='ativo',c.CLI_Ativo='S' WHERE p.PAR_ID=? AND p.PAR_Ativo='S' AND c.CLI_TipoConta='cliente_partner'");
        return $sql->execute([(int)$parceiroId]);
    }

    public function gerarChaveAdmin($parceiroId, $nome)
    {
        $check=$this->db->prepare("SELECT PAR_ID FROM parceiros_api WHERE PAR_ID=? AND PAR_Ativo='S' AND PAR_StatusCadastro='aprovado' LIMIT 1");
        $check->execute([(int)$parceiroId]);
        if(!$check->fetchColumn()){ throw new \DomainException('Aprove o cadastro do parceiro antes de gerar uma API key.'); }
        $segredo='dsp_live_' . bin2hex(random_bytes(32));
        $prefixo=substr($segredo,0,18);
        $hash=hash('sha256',$segredo);
        $sql=$this->db->prepare("INSERT INTO parceiro_api_keys (PAR_ID,PAK_Nome,PAK_Prefixo,PAK_Hash,PAK_Ativo) VALUES (?,?,?,?, 'S')");
        $sql->execute([(int)$parceiroId,trim($nome),$prefixo,$hash]);
        return $segredo;
    }

    public function revogarChaveAdmin($parceiroId, $chaveId)
    {
        $sql=$this->db->prepare("UPDATE parceiro_api_keys SET PAK_Ativo='N',PAK_RevogadaEm=NOW() WHERE PAK_ID=? AND PAR_ID=? AND PAK_RevogadaEm IS NULL");
        return $sql->execute([(int)$chaveId,(int)$parceiroId]);
    }

    public function autenticarPorToken($token)
    {
        $hash = hash('sha256', (string) $token);

        $sql = $this->db->prepare("
            SELECT
                k.PAK_ID,
                k.PAR_ID,
                k.PAK_Nome,
                p.PAR_Nome,
                p.PAR_Identificador,
                p.PAR_WebhookUrl
            FROM parceiro_api_keys k
            INNER JOIN parceiros_api p ON p.PAR_ID = k.PAR_ID
            INNER JOIN clientes cp
                ON cp.CLI_ID = p.CLI_ID
               AND cp.CLI_TipoConta = 'cliente_partner'
               AND cp.CLI_Ativo = 'S'
            WHERE k.PAK_Hash = ?
              AND k.PAK_Ativo = 'S'
              AND k.PAK_RevogadaEm IS NULL
              AND (k.PAK_ExpiraEm IS NULL OR k.PAK_ExpiraEm > NOW())
              AND p.PAR_Ativo = 'S'
              AND p.PAR_StatusCadastro = 'aprovado'
            LIMIT 1
        ");
        $sql->execute([$hash]);
        $parceiro = $sql->fetch(PDO::FETCH_ASSOC);

        if(!$parceiro){
            return null;
        }

        $this->db->prepare("UPDATE parceiro_api_keys SET PAK_UltimoUsoEm=NOW() WHERE PAK_ID=?")
            ->execute([(int) $parceiro['PAK_ID']]);

        return $parceiro;
    }

    public function listarCanaisAutorizados($parceiroId)
    {
        $sql = $this->db->prepare("
            SELECT
                pc.PAC_ID,
                pc.CLI_ID,
                pc.MTA_ID,
                pc.PAC_IdentificadorExterno,
                c.CLI_TipoConta,
                m.MTA_Nome,
                m.MTA_NumeroTelefone,
                m.MTA_Status,
                m.MTA_OnboardingType
            FROM parceiro_clientes pc
            INNER JOIN clientes c
                ON c.CLI_ID = pc.CLI_ID
               AND c.CLI_TipoConta IN ('cliente','cliente_partner_vinculado')
               AND c.CLI_Ativo = 'S'
            INNER JOIN meta_contas m
                ON m.MTA_ID = pc.MTA_ID
               AND m.CLI_ID = pc.CLI_ID
            WHERE pc.PAR_ID = ?
              AND pc.PAC_Ativo = 'S'
              AND m.MTA_Ativo = 'S'
            ORDER BY pc.PAC_ID ASC
        ");
        $sql->execute([(int) $parceiroId]);
        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarCanalAutorizado($parceiroId, $clienteId, $metaId)
    {
        $sql = $this->db->prepare("
            SELECT pc.*, m.MTA_Status, m.MTA_NumeroTelefone
            FROM parceiro_clientes pc
            INNER JOIN clientes c
                ON c.CLI_ID = pc.CLI_ID
               AND c.CLI_TipoConta IN ('cliente','cliente_partner_vinculado')
               AND c.CLI_Ativo = 'S'
            INNER JOIN meta_contas m
                ON m.MTA_ID = pc.MTA_ID
               AND m.CLI_ID = pc.CLI_ID
            WHERE pc.PAR_ID = ?
              AND pc.CLI_ID = ?
              AND pc.MTA_ID = ?
              AND pc.PAC_Ativo = 'S'
              AND pc.PAC_Status = 'ativo'
              AND (
                    c.CLI_TipoConta = 'cliente'
                    OR (
                        c.CLI_TipoConta = 'cliente_partner_vinculado'
                        AND pc.PAC_FaturavelDesde IS NOT NULL
                        AND (pc.PAC_FaturavelAte IS NULL OR pc.PAC_FaturavelAte > NOW())
                    )
                  )
              AND m.MTA_Ativo = 'S'
            LIMIT 1
        ");
        $sql->execute([(int)$parceiroId, (int)$clienteId, (int)$metaId]);
        return $sql->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
