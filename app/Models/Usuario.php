<?php

namespace Models;

use Core\Database;
use PDO;

class Usuario
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function listarPorCliente($clienteId)
    {
        $sql = $this->db->prepare("
            SELECT
                USU_ID,
                CLI_ID,
                USU_Nome,
                USU_Email,
                USU_Nivel,
                USU_Ativo
            FROM usuarios
            WHERE CLI_ID = ?
            AND USU_Nivel IN ('cliente', 'cliente_admin', 'cliente_usuario')
            ORDER BY USU_Nome ASC
        ");

        $sql->execute([$clienteId]);

        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarPorCliente($id, $clienteId)
    {
        $sql = $this->db->prepare("
            SELECT *
            FROM usuarios
            WHERE USU_ID = ?
            AND CLI_ID = ?
            AND USU_Nivel IN ('cliente', 'cliente_admin', 'cliente_usuario')
            LIMIT 1
        ");

        $sql->execute([$id, $clienteId]);

        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function emailExiste($email, $ignorarId = null)
    {
        $params = [$email];
        $where = "USU_Email = ?";

        if($ignorarId){
            $where .= " AND USU_ID <> ?";
            $params[] = $ignorarId;
        }

        $sql = $this->db->prepare("
            SELECT USU_ID
            FROM usuarios
            WHERE {$where}
            LIMIT 1
        ");

        $sql->execute($params);

        return (bool) $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function criarClienteUsuario($clienteId, $nome, $email, $senha)
    {
        $sql = $this->db->prepare("
            INSERT INTO usuarios
            (CLI_ID, USU_Nome, USU_Email, USU_Senha, USU_Nivel, USU_Ativo)
            VALUES (?, ?, ?, ?, 'cliente_usuario', 'S')
        ");

        return $sql->execute([
            $clienteId,
            $nome,
            $email,
            password_hash($senha, PASSWORD_DEFAULT)
        ]);
    }

    public function atualizar($id, $clienteId, $nome, $email)
    {
        $sql = $this->db->prepare("
            UPDATE usuarios
            SET USU_Nome = ?, USU_Email = ?
            WHERE USU_ID = ?
            AND CLI_ID = ?
            AND USU_Nivel IN ('cliente', 'cliente_admin', 'cliente_usuario')
        ");

        return $sql->execute([$nome, $email, $id, $clienteId]);
    }

    public function alterarSenha($id, $clienteId, $senha)
    {
        $sql = $this->db->prepare("
            UPDATE usuarios
            SET USU_Senha = ?
            WHERE USU_ID = ?
            AND CLI_ID = ?
            AND USU_Nivel IN ('cliente', 'cliente_admin', 'cliente_usuario')
        ");

        return $sql->execute([
            password_hash($senha, PASSWORD_DEFAULT),
            $id,
            $clienteId
        ]);
    }

    public function atualizarStatus($id, $clienteId, $ativo)
    {
        $sql = $this->db->prepare("
            UPDATE usuarios
            SET USU_Ativo = ?
            WHERE USU_ID = ?
            AND CLI_ID = ?
            AND USU_Nivel IN ('cliente', 'cliente_admin', 'cliente_usuario')
        ");

        return $sql->execute([$ativo, $id, $clienteId]);
    }

    public function contarAtivosPorCliente($clienteId)
    {
        $sql = $this->db->prepare("
            SELECT COUNT(*) total
            FROM usuarios
            WHERE CLI_ID = ?
            AND USU_Ativo = 'S'
            AND USU_Nivel IN ('cliente', 'cliente_admin', 'cliente_usuario')
        ");

        $sql->execute([$clienteId]);

        return (int) $sql->fetch(PDO::FETCH_ASSOC)['total'];
    }
}
