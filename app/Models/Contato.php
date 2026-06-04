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

        $sql->execute([
            $dados['cliente_id'],
            $dados['nome'],
            $dados['telefone'],
            $dados['dados_json']
        ]);

        return $this->db->lastInsertId();
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

    public function camposJsonPorCliente($clienteId)
    {
        $sql = $this->db->prepare("

            SELECT CON_DadosJson

            FROM contatos

            WHERE CLI_ID = ?
            AND CON_Ativo = 'S'
            AND CON_DadosJson IS NOT NULL

            ORDER BY CON_ID DESC

            LIMIT 1

        ");

        $sql->execute([
            $clienteId
        ]);

        $contato =
            $sql->fetch(PDO::FETCH_ASSOC);

        if(!$contato){
            return [];
        }

        $dados =
            json_decode(
                $contato['CON_DadosJson'],
                true
            );

        if(!is_array($dados)){
            return [];
        }

        return array_keys($dados);
    }

    public function buscarPorTelefone($clienteId, $telefone)
    {
        $sql = $this->db->prepare("
            SELECT *
            FROM contatos
            WHERE CLI_ID = ?
            AND CON_Telefone = ?
            LIMIT 1
        ");

        $sql->execute([
            $clienteId,
            $telefone
        ]);

        return $sql->fetch(PDO::FETCH_ASSOC);
    }

}