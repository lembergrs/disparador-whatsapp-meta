<?php

namespace Models;

use Core\Database;
use PDO;

class Contato
{
    private $db;

    public function __construct()
    {
        $this->db =
            Database::getInstance();
    }

    public function listarPorCliente($clienteID)
    {
        $sql = $this->db->prepare("
            SELECT *
            FROM contatos
            WHERE CLI_ID = ?
            ORDER BY CON_ID DESC
        ");

        $sql->execute([$clienteID]);

        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    public function salvar($dados)
    {
        $sql = $this->db->prepare("
            INSERT INTO contatos (

                CLI_ID,
                CON_Nome,
                CON_Telefone,
                CON_DadosJson

            ) VALUES (

                ?, ?, ?, ?

            )
        ");

        return $sql->execute([

            $dados['cliente_id'],
            $dados['nome'],
            $dados['telefone'],
            $dados['dados_json']

        ]);
    }

    public function telefoneExiste(
        $clienteID,
        $telefone
    ){

        $sql = $this->db->prepare("
            SELECT CON_ID
            FROM contatos
            WHERE CLI_ID = ?
            AND CON_Telefone = ?
            LIMIT 1
        ");

        $sql->execute([
            $clienteID,
            $telefone
        ]);

        return $sql->fetch(PDO::FETCH_ASSOC);
    }


    public function listarIdsPorCliente($clienteId)
    {
        $sql = $this->db->prepare("

            SELECT CON_ID

            FROM contatos

            WHERE CLI_ID = ?
            AND CON_Ativo = 'S'

        ");

        $sql->execute([
            $clienteId
        ]);

        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }

}