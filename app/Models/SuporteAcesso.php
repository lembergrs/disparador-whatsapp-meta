<?php

namespace Models;

use Core\Database;
use PDO;

class SuporteAcesso
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?: Database::getInstance();
    }

    public function buscarIdentidadePrincipalCliente($clienteId)
    {
        $sql = $this->db->prepare("
            SELECT
                u.USU_ID,
                u.USU_Nome,
                u.USU_Nivel,
                u.CLI_ID,
                c.CLI_Nome,
                c.CLI_StatusPagamento,
                c.CLI_StatusCadastro,
                c.CLI_DataLiberacao,
                c.CLI_DataCadastro,
                c.CLI_Plano_DR,
                COALESCE(cm.CMS_Mensagens, 0) AS CMS_MensagensMesAtual
            FROM clientes c
            INNER JOIN usuarios u
                ON u.CLI_ID = c.CLI_ID
                AND u.USU_Ativo = 'S'
                AND u.USU_Nivel IN ('cliente_admin', 'cliente')
            LEFT JOIN consumo_mensal cm
                ON cm.CLI_ID = c.CLI_ID
                AND cm.CMS_AnoMes = ?
            WHERE c.CLI_ID = ?
            AND c.CLI_Ativo = 'S'
            AND c.CLI_StatusCadastro = 'ativo'
            ORDER BY FIELD(u.USU_Nivel, 'cliente_admin', 'cliente'), u.USU_ID
            LIMIT 1
        ");
        $sql->execute([date('Ym'), (int) $clienteId]);

        return $sql->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function iniciar($adminId, $clienteId, $usuarioClienteId, $ip, $userAgent)
    {
        $sql = $this->db->prepare("
            INSERT INTO suporte_acessos
                (USU_Admin_ID, CLI_ID, USU_Cliente_ID, SUA_DataInicio, SUA_IP, SUA_UserAgent)
            VALUES (?, ?, ?, NOW(), ?, ?)
        ");
        $sql->execute([
            (int) $adminId,
            (int) $clienteId,
            (int) $usuarioClienteId,
            mb_substr((string) $ip, 0, 45),
            mb_substr((string) $userAgent, 0, 500)
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function encerrar($acessoId, $motivo)
    {
        $motivos = ['retorno_normal', 'logout', 'sessao_expirada', 'outro'];
        if(!in_array($motivo, $motivos, true)){
            $motivo = 'outro';
        }

        $sql = $this->db->prepare("
            UPDATE suporte_acessos
            SET SUA_DataFim = COALESCE(SUA_DataFim, NOW()),
                SUA_MotivoEncerramento = COALESCE(SUA_MotivoEncerramento, ?)
            WHERE SUA_ID = ?
            AND SUA_DataFim IS NULL
        ");

        return $sql->execute([$motivo, (int) $acessoId]);
    }
}
