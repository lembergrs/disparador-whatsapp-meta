<?php

namespace Models;

use Core\Database;
use PDO;

class ListaContato
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
                l.*,
                (
                    SELECT COUNT(*)
                    FROM lista_contatos_itens i
                    WHERE i.LST_ID = l.LST_ID
                ) AS total_contatos
            FROM listas_contatos l
            WHERE l.CLI_ID = ?
            AND l.LST_Ativo = 'S'
            ORDER BY l.LST_ID DESC
        ");

        $sql->execute([$clienteId]);

        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    public function criar($clienteId, $nome, $descricao = null)
    {
        $sql = $this->db->prepare("
            INSERT INTO listas_contatos
            (
                CLI_ID,
                LST_Nome,
                LST_Descricao
            )
            VALUES
            (
                ?, ?, ?
            )
        ");

        $sql->execute([
            $clienteId,
            $nome,
            $descricao
        ]);

        return $this->db->lastInsertId();
    }

    public function buscar($id, $clienteId)
    {
        $sql = $this->db->prepare("
            SELECT *
            FROM listas_contatos
            WHERE LST_ID = ?
            AND CLI_ID = ?
            LIMIT 1
        ");

        $sql->execute([
            $id,
            $clienteId
        ]);

        return $sql->fetch(PDO::FETCH_ASSOC);
    }
}