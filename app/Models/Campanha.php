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
                CAM_HeaderMidiaTipo,
                CAM_HeaderMidiaId,
                CAM_HeaderMidiaNome,
                CAM_HeaderMidiaMime,
                CAM_HeaderMidiaTamanho,
                CAM_Nome,
                CAM_Descricao,
                CAM_Status,
                CAM_DataAgendamento,
                CAM_TotalContatos,
                CAM_TotalEnviados,
                CAM_TotalErros,
                CAM_DataCadastro

            ) VALUES (

                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
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
        $dados['midia_header']['tipo'] ?? null,
        $dados['midia_header']['media_id'] ?? null,
        $dados['midia_header']['nome_original'] ?? null,
        $dados['midia_header']['mime'] ?? null,
        $dados['midia_header']['tamanho'] ?? null,
        $dados['nome'],
        $dados['descricao'],
        $dados['data_agendamento']

        ]);

        return $this->db->lastInsertId();
    }

    public function contarPorCliente($clienteId)
    {
        $sql = $this->db->prepare('SELECT COUNT(*) FROM campanhas WHERE CLI_ID = ?');
        $sql->execute([(int) $clienteId]);
        return (int) $sql->fetchColumn();
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
                t.MTA_ID,
                t.TMP_HeaderTipo,
                t.TMP_HeaderMidiaUrlExemplo,
                t.TMP_HeaderDocumentoNome

            FROM campanhas c

            LEFT JOIN templates_meta t
                ON t.TMP_ID = c.TMP_ID

            WHERE c.CAM_ID = ?

        ");

        $sql->execute([$id]);

        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function buscarPorCliente($id, $clienteId)
    {
        $sql = $this->db->prepare("
            SELECT
                c.*,
                t.TMP_Nome,
                t.TMP_Componentes,
                t.TMP_Idioma,
                t.MTA_ID,
                t.TMP_HeaderTipo,
                t.TMP_HeaderMidiaUrlExemplo,
                t.TMP_HeaderDocumentoNome
            FROM campanhas c
            LEFT JOIN templates_meta t
                ON t.TMP_ID = c.TMP_ID
            WHERE c.CAM_ID = ?
            AND c.CLI_ID = ?
            LIMIT 1
        "
        );

        $sql->execute([
            $id,
            $clienteId
        ]);

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

    public function listarFilaPorCliente($campanhaId, $clienteId)
    {
        $sql = $this->db->prepare("
            SELECT
                f.*,
                ct.CON_Nome,
                ct.CON_Telefone
            FROM fila_envio f
            INNER JOIN campanhas cp
                ON cp.CAM_ID = f.CAM_ID
            INNER JOIN contatos ct
                ON ct.CON_ID = f.CON_ID
                AND ct.CLI_ID = cp.CLI_ID
            WHERE f.CAM_ID = ?
            AND cp.CLI_ID = ?
            ORDER BY f.FIL_ID ASC
        "
        );

        $sql->execute([
            $campanhaId,
            $clienteId
        ]);

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

    public function buscarContatoExemploPorCliente($campanhaId, $clienteId)
    {
        $sql = $this->db->prepare("
            SELECT c.*
            FROM fila_envio f
            INNER JOIN campanhas cp
                ON cp.CAM_ID = f.CAM_ID
            INNER JOIN contatos c
                ON c.CON_ID = f.CON_ID
                AND c.CLI_ID = cp.CLI_ID
            WHERE f.CAM_ID = ?
            AND cp.CLI_ID = ?
            LIMIT 1
        "
        );

        $sql->execute([
            $campanhaId,
            $clienteId
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
                FIL_MessageId = NULL,
                FIL_DataEnvio = NULL,
                FIL_WorkerId = NULL,
                FIL_DataReserva = NULL,
                FIL_ProximaTentativa = NULL,
                FIL_UltimoErroTipo = NULL,
                FIL_UltimoErroCodigo = NULL,
                FIL_Erro = NULL,
                FIL_Retorno = NULL,
                FIL_Tentativas = 0

            WHERE CAM_ID = ?

        ");

        return $sql->execute([
            $campanhaId
        ]);
    }

    public function resetarFilaPorCliente($campanhaId, $clienteId)
    {
        $sql = $this->db->prepare("
            UPDATE fila_envio f
            INNER JOIN campanhas c
                ON c.CAM_ID = f.CAM_ID
            SET
                f.FIL_Status = 'pendente',
                f.FIL_MessageId = NULL,
                f.FIL_DataEnvio = NULL,
                f.FIL_WorkerId = NULL,
                f.FIL_DataReserva = NULL,
                f.FIL_ProximaTentativa = NULL,
                f.FIL_UltimoErroTipo = NULL,
                f.FIL_UltimoErroCodigo = NULL,
                f.FIL_Erro = NULL,
                f.FIL_Retorno = NULL,
                f.FIL_Tentativas = 0
            WHERE f.CAM_ID = ?
            AND c.CLI_ID = ?
        "
        );

        return $sql->execute([
            $campanhaId,
            $clienteId
        ]);
    }

}
