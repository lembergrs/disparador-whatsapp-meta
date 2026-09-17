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

    public function adicionarContatosDeOutraLista($listaOrigemId, $listaDestinoId, array $contatoIds)
    {
        $contatoIds = array_values(array_unique(array_filter(array_map('intval', $contatoIds), function($id){
            return $id > 0;
        })));

        if(!$contatoIds){
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($contatoIds), '?'));
        $sql = $this->db->prepare("
            INSERT IGNORE INTO lista_contatos_itens (LST_ID, CON_ID)
            SELECT ?, origem.CON_ID
            FROM lista_contatos_itens origem
            WHERE origem.LST_ID = ?
            AND origem.CON_ID IN ({$placeholders})
        ");

        $parametros = array_merge(
            [(int) $listaDestinoId, (int) $listaOrigemId],
            $contatoIds
        );
        $sql->execute($parametros);

        return $sql->rowCount();
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

    public function removerContato(
        $listaId,
        $contatoId
    )
    {
        $sql = $this->db->prepare("

            DELETE FROM lista_contatos_itens

            WHERE LST_ID = ?
            AND CON_ID = ?

        ");

        return $sql->execute([

            $listaId,
            $contatoId

        ]);
    }

    public function removerContatos($listaId, array $contatoIds)
    {
        $contatoIds = array_values(array_unique(array_filter(array_map('intval', $contatoIds), function($id){
            return $id > 0;
        })));

        if(!$contatoIds){
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($contatoIds), '?'));
        $sql = $this->db->prepare("
            DELETE FROM lista_contatos_itens
            WHERE LST_ID = ?
            AND CON_ID IN ({$placeholders})
        ");

        $parametros = array_merge([(int) $listaId], $contatoIds);
        $sql->execute($parametros);

        return $sql->rowCount();
    }

    public function contatoExisteNaLista(
        $listaId,
        $contatoId
    )
    {
        $sql = $this->db->prepare("

            SELECT 1

            FROM lista_contatos_itens

            WHERE LST_ID = ?
            AND CON_ID = ?

            LIMIT 1

        ");

        $sql->execute([

            $listaId,
            $contatoId

        ]);

        return $sql->fetchColumn();
    }

    public function contatoExisteEmOutraListaDoCliente($clienteId, $contatoId, $listaIgnoradaId = 0)
    {
        $sql = $this->db->prepare("
            SELECT 1
            FROM lista_contatos_itens i
            INNER JOIN listas_contatos l
                ON l.LST_ID = i.LST_ID
            WHERE l.CLI_ID = ?
              AND i.CON_ID = ?
              AND l.LST_ID <> ?
            LIMIT 1
        ");

        $sql->execute([
            (int) $clienteId,
            (int) $contatoId,
            (int) $listaIgnoradaId
        ]);

        return (bool) $sql->fetchColumn();
    }

}
