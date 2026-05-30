<?php

namespace Models;

use Core\Database;
use PDO;

class MetaConta
{
    private $db;

    public function __construct()
    {
        $this->db =
            Database::getInstance();
    }





    public function listar()
    {
        $sql = $this->db->query("

            SELECT
                m.*,
                c.CLI_Nome

            FROM meta_contas m

            INNER JOIN clientes c
            ON c.CLI_ID = m.CLI_ID

            WHERE m.MTA_Ativo = 'S'

            ORDER BY m.MTA_ID DESC

        ");

        return $sql->fetchAll(
            PDO::FETCH_ASSOC
        );
    }





    public function salvar($dados)
    {
        $sql = $this->db->prepare("

            INSERT INTO meta_contas
            (

                CLI_ID,
                MTA_Nome,
                MTA_PhoneNumberId,
                MTA_WabaId,
                MTA_Token,
                MTA_UrlBase,
                MTA_NumeroTelefone,
                MTA_Status,
                MTA_Ativo

            )

            VALUES
            (

                ?, ?, ?, ?, ?, ?, ?, 'desconectado', 'S'

            )

        ");





        return $sql->execute([

            $dados['cliente'],

            $dados['nome'],

            $dados['phone_number_id'],

            $dados['waba_id'],

            $dados['token'],

            $dados['url_base'],

            $dados['numero']

        ]);
    }





    public function inativar($id)
    {
        $sql = $this->db->prepare("

            UPDATE meta_contas

            SET MTA_Ativo = 'N'

            WHERE MTA_ID = ?

        ");

        return $sql->execute([$id]);
    }

    public function atualizar($id, $dados)
    {
        $sql = $this->db->prepare("

            UPDATE meta_contas SET

                CLI_ID = ?,

                MTA_Nome = ?,

                MTA_PhoneNumberId = ?,

                MTA_WabaId = ?,

                MTA_Token = ?,

                MTA_UrlBase = ?,

                MTA_NumeroTelefone = ?

            WHERE MTA_ID = ?

        ");





        return $sql->execute([

            $dados['cliente'],

            $dados['nome'],

            $dados['phone_number_id'],

            $dados['waba_id'],

            $dados['token'],

            $dados['url_base'],

            $dados['numero'],

            $id

        ]);
    }

    public function listarPorCliente($clienteId)
    {
        $sql = $this->db->prepare("

            SELECT *

            FROM meta_contas

            WHERE CLI_ID = ?
            AND MTA_Ativo = 'S'

            ORDER BY MTA_ID DESC

        ");





        $sql->execute([
            $clienteId
        ]);





        return $sql->fetchAll(
            PDO::FETCH_ASSOC
        );
    }

}