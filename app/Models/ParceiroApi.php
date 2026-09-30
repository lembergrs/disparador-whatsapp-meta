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
                m.MTA_Nome,
                m.MTA_NumeroTelefone,
                m.MTA_Status,
                m.MTA_OnboardingType
            FROM parceiro_clientes pc
            INNER JOIN clientes c
                ON c.CLI_ID = pc.CLI_ID
               AND c.CLI_TipoConta = 'cliente_partner_vinculado'
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
               AND c.CLI_TipoConta = 'cliente_partner_vinculado'
               AND c.CLI_Ativo = 'S'
            INNER JOIN meta_contas m
                ON m.MTA_ID = pc.MTA_ID
               AND m.CLI_ID = pc.CLI_ID
            WHERE pc.PAR_ID = ?
              AND pc.CLI_ID = ?
              AND pc.MTA_ID = ?
              AND pc.PAC_Ativo = 'S'
              AND m.MTA_Ativo = 'S'
            LIMIT 1
        ");
        $sql->execute([(int)$parceiroId, (int)$clienteId, (int)$metaId]);
        return $sql->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
