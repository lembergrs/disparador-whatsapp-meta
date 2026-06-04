<?php

namespace Models;

use Core\Database;
use PDO;

class ListaContatoItem
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function adicionar($listaId, $contatoId)
    {
        $sql = $this->db->prepare("
            INSERT IGNORE INTO lista_contatos_itens
            (
                LST_ID,
                CON_ID
            )
            VALUES
            (
                ?, ?
            )
        ");

        return $sql->execute([
            $listaId,
            $contatoId
        ]);
    }

    public function listarContatos($listaId)
    {
        $sql = $this->db->prepare("
            SELECT c.*
            FROM lista_contatos_itens i
            INNER JOIN contatos c
                ON c.CON_ID = i.CON_ID
            WHERE i.LST_ID = ?
            AND c.CON_Ativo = 'S'
            ORDER BY c.CON_Nome ASC
        ");

        $sql->execute([$listaId]);

        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarIdsDaLista($listaId)
    {
        $sql = $this->db->prepare("

            SELECT CON_ID

            FROM lista_contatos_itens

            WHERE LST_ID = ?

        ");

        $sql->execute([
            $listaId
        ]);

        return $sql->fetchAll(
            PDO::FETCH_ASSOC
        );
    }

}