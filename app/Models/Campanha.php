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
                LST_ID,
                CAM_Nome,
                CAM_Descricao,
                CAM_Status,
                CAM_DataAgendamento,
                CAM_TotalContatos,
                CAM_TotalEnviados,
                CAM_TotalErros,
                CAM_DataCadastro

            ) VALUES (

                ?, ?, ?, ?, ?,
                'agendada',
                ?,
                0,0,0,
                NOW()

            )

        ");

        $sql->execute([

        $dados['cliente_id'],
        $dados['template_id'],
        $dados['lista_id'],
        $dados['nome'],
        $dados['descricao'],
        $dados['data_agendamento']

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

    public function buscar($id)
    {
        $sql = $this->db->prepare("

            SELECT
                c.*,
                t.TMP_Nome,
                t.TMP_Componentes,
                t.TMP_Idioma,
                t.MTA_ID

            FROM campanhas c

            LEFT JOIN templates_meta t
                ON t.TMP_ID = c.TMP_ID

            WHERE c.CAM_ID = ?

        ");

        $sql->execute([$id]);

        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function listarFila($campanhaId)
    {
        $sql = $this->db->prepare("

            SELECT
                f.*,
                c.CON_Nome,
                c.CON_Telefone

            FROM fila_envio f

            INNER JOIN contatos c
            ON c.CON_ID = f.CON_ID

            WHERE f.CAM_ID = ?

            ORDER BY f.FIL_ID ASC

        ");

        $sql->execute([$campanhaId]);

        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function cancelar($id, $clienteId)
    {
        $sql = $this->db->prepare("

            UPDATE campanhas

            SET CAM_Status = 'cancelada'

            WHERE CAM_ID = ?
            AND CLI_ID = ?
            AND CAM_Status IN ('rascunho','agendada','processando')

        ");

        return $sql->execute([
            $id,
            $clienteId
        ]);
    }

    public function buscarContatoExemplo($campanhaId)
    {
        $sql = $this->db->prepare("

            SELECT
                c.*

            FROM fila_envio f

            INNER JOIN contatos c
                ON c.CON_ID = f.CON_ID

            WHERE f.CAM_ID = ?

            LIMIT 1

        ");

        $sql->execute([
            $campanhaId
        ]);

        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function reagendar($id, $clienteId, $dataAgendamento)
    {
        $sql = $this->db->prepare("

            UPDATE campanhas

            SET
                CAM_Status = 'agendada',
                CAM_DataAgendamento = ?,
                CAM_TotalEnviados = 0,
                CAM_TotalErros = 0

            WHERE CAM_ID = ?
            AND CLI_ID = ?

        ");

        return $sql->execute([

            $dataAgendamento,
            $id,
            $clienteId

        ]);
    }





    public function resetarFila($campanhaId)
    {
        $sql = $this->db->prepare("

            UPDATE fila_envio

            SET
                FIL_Status = 'pendente',
                FIL_Tentativas = 0,
                FIL_Erro = NULL,
                FIL_DataEnvio = NULL

            WHERE CAM_ID = ?

        ");

        return $sql->execute([
            $campanhaId
        ]);
    }

}