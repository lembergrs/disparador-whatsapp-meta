<?php

namespace Models;

use Core\Database;
use PDO;

class DepoimentoCliente
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?: Database::getInstance();
    }

    public function criarPendente($clienteId, array $dados)
    {
        $sql = "INSERT INTO depoimentos_clientes
            (CLI_ID, DEP_NomeExibido, DEP_Empresa, DEP_Cargo, DEP_Depoimento, DEP_Autorizado, DEP_Status, DEP_Ativo)
            VALUES (?, ?, ?, ?, ?, 'S', 'pendente', 'S')";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            (int) $clienteId,
            $dados['nome'],
            $dados['empresa'],
            $dados['cargo'] ?: null,
            $dados['depoimento']
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function listarDoCliente($clienteId)
    {
        $stmt = $this->db->prepare('SELECT * FROM depoimentos_clientes WHERE CLI_ID = ? ORDER BY DEP_EnviadoEm DESC, DEP_ID DESC');
        $stmt->execute([(int) $clienteId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarParaAdministracao()
    {
        return $this->db->query("SELECT d.*, c.CLI_Nome AS ClienteNome, c.CLI_Email AS ClienteEmail
            FROM depoimentos_clientes d
            INNER JOIN clientes c ON c.CLI_ID = d.CLI_ID
            ORDER BY FIELD(d.DEP_Status, 'pendente', 'aprovado', 'rejeitado'), d.DEP_EnviadoEm DESC, d.DEP_ID DESC")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarPublicados($limite = 6)
    {
        $limite = max(1, min(6, (int) $limite));
        return $this->db->query("SELECT DEP_NomeExibido, DEP_Empresa, DEP_Cargo, DEP_Depoimento
            FROM depoimentos_clientes
            WHERE DEP_Status = 'aprovado' AND DEP_Ativo = 'S' AND DEP_Autorizado = 'S'
            ORDER BY DEP_DecididoEm DESC, DEP_ID DESC LIMIT {$limite}")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function decidir($id, $status, $adminId)
    {
        if(!in_array($status, ['aprovado', 'rejeitado'], true)){
            throw new \InvalidArgumentException('Decisão inválida.');
        }
        $stmt = $this->db->prepare("UPDATE depoimentos_clientes
            SET DEP_Status = ?, DEP_DecididoEm = CURRENT_TIMESTAMP, DEP_DecididoPor_USU_ID = ?,
                DEP_Ativo = CASE WHEN ? = 'aprovado' THEN 'S' ELSE 'N' END
            WHERE DEP_ID = ? AND DEP_Status = 'pendente'");
        $stmt->execute([$status, (int) $adminId, $status, (int) $id]);
        return $stmt->rowCount() === 1;
    }

    public function desativar($id, $adminId)
    {
        $stmt = $this->db->prepare("UPDATE depoimentos_clientes
            SET DEP_Ativo = 'N', DEP_DecididoEm = CURRENT_TIMESTAMP, DEP_DecididoPor_USU_ID = ?
            WHERE DEP_ID = ? AND DEP_Status = 'aprovado' AND DEP_Ativo = 'S'");
        $stmt->execute([(int) $adminId, (int) $id]);
        return $stmt->rowCount() === 1;
    }
}
