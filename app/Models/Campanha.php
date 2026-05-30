<?php

namespace Models;

use Core\Database;
use PDO;

class Campanha
{
    private $db;

    public function __construct()
    {
        $this->db =
            Database::getInstance();
    }

    public function listarPorCliente($clienteId)
    {
        $sql = $this->db->prepare("

            SELECT *

            FROM campanhas

            WHERE CLI_ID = ?

            ORDER BY CAM_ID DESC

        ");

        $sql->execute([
            $clienteId
        ]);

        return $sql->fetchAll(
            PDO::FETCH_ASSOC
        );
    }

    public function salvar($dados)
    {
        $sql = $this->db->prepare("

            INSERT INTO campanhas (

                CLI_ID,
                TMP_ID,
                CAM_Nome,
                CAM_Descricao,
                CAM_Status,
                CAM_TotalContatos,
                CAM_TotalEnviados,
                CAM_TotalErros,
                CAM_DataCadastro

            ) VALUES (

                ?, ?, ?, ?,
                'rascunho',
                0,0,0,
                NOW()

            )

        ");

        $sql->execute([

            $dados['cliente_id'],
            $dados['template_id'],
            $dados['nome'],
            $dados['descricao']

        ]);

        return $this->db->lastInsertId();
    }

    public function atualizarTotalContatos(
        $campanhaId,
        $total
    )
    {
        $sql = $this->db->prepare("

            UPDATE campanhas

            SET CAM_TotalContatos = ?

            WHERE CAM_ID = ?

        ");

        return $sql->execute([

            $total,
            $campanhaId

        ]);
    }

}