<?php

namespace Models;

use Core\Database;
use PDO;

class RecuperacaoSenha
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?: Database::getInstance();
    }

    public function buscarUsuarioRecuperavelPorEmail($email)
    {
        $sql = $this->db->prepare("\n            SELECT u.USU_ID, u.CLI_ID, u.USU_Nome, u.USU_Email, u.USU_Senha, u.USU_Nivel, u.USU_Ativo\n            FROM usuarios u\n            WHERE u.USU_Email = ?\n            AND u.USU_Ativo = 'S'\n            LIMIT 1\n        ");
        $sql->execute([trim((string) $email)]);
        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function contarRecentesPorIp($ip, $minutos = 5)
    {
        if(trim((string) $ip) === ''){
            return 0;
        }

        $sql = $this->db->prepare("\n            SELECT COUNT(*) total\n            FROM recuperacoes_senha\n            WHERE RSE_IP = ?\n            AND RSE_CriadoEm >= DATE_SUB(NOW(), INTERVAL ? MINUTE)\n        ");
        $sql->execute([(string) $ip, (int) $minutos]);
        $row = $sql->fetch(PDO::FETCH_ASSOC);
        return (int) ($row['total'] ?? 0);
    }


    public function contarRecentesPorUsuario($usuarioId, $minutos = 3)
    {
        $sql = $this->db->prepare("
            SELECT COUNT(*) total
            FROM recuperacoes_senha
            WHERE RSE_USU_ID = ?
            AND RSE_CriadoEm >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ");
        $sql->execute([(int) $usuarioId, (int) $minutos]);
        $row = $sql->fetch(PDO::FETCH_ASSOC);
        return (int) ($row['total'] ?? 0);
    }

    public function invalidarPendentesUsuario($usuarioId)
    {
        $sql = $this->db->prepare("\n            UPDATE recuperacoes_senha\n            SET RSE_InvalidadoEm = NOW()\n            WHERE RSE_USU_ID = ?\n            AND RSE_UtilizadoEm IS NULL\n            AND RSE_InvalidadoEm IS NULL\n        ");
        return $sql->execute([(int) $usuarioId]);
    }

    public function criar($usuarioId, $tokenHash, $expiraEm, $ip = null, $userAgent = null)
    {
        $sql = $this->db->prepare("\n            INSERT INTO recuperacoes_senha (\n                RSE_USU_ID, RSE_TokenHash, RSE_ExpiraEm, RSE_IP, RSE_UserAgent, RSE_CriadoEm\n            ) VALUES (\n                :usuario, :hash, :expira, :ip, :user_agent, NOW()\n            )\n        ");
        $sql->execute([
            ':usuario' => (int) $usuarioId,
            ':hash' => (string) $tokenHash,
            ':expira' => (string) $expiraEm,
            ':ip' => $ip ? substr((string) $ip, 0, 45) : null,
            ':user_agent' => $userAgent ? substr((string) $userAgent, 0, 255) : null
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function buscarPorHash($tokenHash, $bloquear = false)
    {
        $sql = $this->db->prepare("\n            SELECT r.*, u.USU_ID, u.CLI_ID, u.USU_Nome, u.USU_Email, u.USU_Senha, u.USU_Ativo\n            FROM recuperacoes_senha r\n            INNER JOIN usuarios u ON u.USU_ID = r.RSE_USU_ID\n            WHERE r.RSE_TokenHash = ?\n            LIMIT 1\n            " . ($bloquear ? 'FOR UPDATE' : '') . "\n        ");
        $sql->execute([(string) $tokenHash]);
        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function atualizarSenhaUsuario($usuarioId, $senhaHash)
    {
        $sql = $this->db->prepare("UPDATE usuarios SET USU_Senha = ? WHERE USU_ID = ? AND USU_Ativo = 'S'");
        $sql->execute([(string) $senhaHash, (int) $usuarioId]);
        return $sql->rowCount() === 1;
    }

    public function marcarUtilizado($recuperacaoId)
    {
        $sql = $this->db->prepare("\n            UPDATE recuperacoes_senha\n            SET RSE_UtilizadoEm = NOW()\n            WHERE RSE_ID = ?\n            AND RSE_UtilizadoEm IS NULL\n            AND RSE_InvalidadoEm IS NULL\n            AND RSE_ExpiraEm >= NOW()\n        ");
        $sql->execute([(int) $recuperacaoId]);
        return $sql->rowCount() === 1;
    }

    public function invalidarOutrosPendentes($usuarioId, $excetoId)
    {
        $sql = $this->db->prepare("\n            UPDATE recuperacoes_senha\n            SET RSE_InvalidadoEm = NOW()\n            WHERE RSE_USU_ID = ?\n            AND RSE_ID <> ?\n            AND RSE_UtilizadoEm IS NULL\n            AND RSE_InvalidadoEm IS NULL\n        ");
        return $sql->execute([(int) $usuarioId, (int) $excetoId]);
    }
}
