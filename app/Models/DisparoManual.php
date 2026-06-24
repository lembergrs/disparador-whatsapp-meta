<?php

namespace Models;

use Core\Database;
use PDO;

class DisparoManual
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function criarLote($clienteId, $metaId, $templateId, $total)
    {
        $sql = $this->db->prepare("
            INSERT INTO disparo_manual_lotes
            (CLI_ID, MTA_ID, TMP_ID, DML_Total, DML_Status, DML_DataCadastro, DML_DataAtualizacao)
            VALUES (?, ?, ?, ?, 'pendente', NOW(), NOW())
        ");

        $sql->execute([$clienteId, $metaId, $templateId, $total]);

        return (int) $this->db->lastInsertId();
    }

    public function adicionarItem($loteId, $clienteId, $numero, array $variaveis)
    {
        $sql = $this->db->prepare("
            INSERT INTO disparo_manual_itens
            (DML_ID, CLI_ID, DMI_Numero, DMI_VariaveisJson, DMI_Status, DMI_DataCadastro, DMI_DataAtualizacao)
            VALUES (?, ?, ?, ?, 'pendente', NOW(), NOW())
        ");

        return $sql->execute([
            $loteId,
            $clienteId,
            $numero,
            json_encode($variaveis, JSON_UNESCAPED_UNICODE)
        ]);
    }

    public function buscarLoteCliente($loteId, $clienteId)
    {
        $sql = $this->db->prepare("
            SELECT *
            FROM disparo_manual_lotes
            WHERE DML_ID = ?
            AND CLI_ID = ?
            LIMIT 1
        ");

        $sql->execute([$loteId, $clienteId]);

        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function listarItensCliente($loteId, $clienteId)
    {
        $sql = $this->db->prepare("
            SELECT
                DMI_ID,
                DMI_Numero,
                DMI_Status,
                DMI_MessageId,
                DMI_Erro,
                DMI_Retorno,
                DMI_DataEnvio,
                DMI_DataAtualizacao
            FROM disparo_manual_itens
            WHERE DML_ID = ?
            AND CLI_ID = ?
            ORDER BY DMI_ID ASC
        ");

        $sql->execute([$loteId, $clienteId]);

        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    public function recalcularLote($loteId)
    {
        $sql = $this->db->prepare("
            SELECT
                COUNT(*) total,
                SUM(CASE WHEN DMI_Status IN ('aguardando_confirmacao','enviado','entregue','lido') THEN 1 ELSE 0 END) enviados,
                SUM(CASE WHEN DMI_Status = 'erro' THEN 1 ELSE 0 END) erros,
                SUM(CASE WHEN DMI_Status IN ('pendente','processando') THEN 1 ELSE 0 END) pendentes
            FROM disparo_manual_itens
            WHERE DML_ID = ?
        ");

        $sql->execute([$loteId]);
        $dados = $sql->fetch(PDO::FETCH_ASSOC) ?: [];

        $pendentes = (int) ($dados['pendentes'] ?? 0);
        $status = $pendentes > 0 ? 'processando' : 'concluido';

        $update = $this->db->prepare("
            UPDATE disparo_manual_lotes
            SET
                DML_Total = ?,
                DML_TotalEnviados = ?,
                DML_TotalErros = ?,
                DML_Status = ?,
                DML_DataAtualizacao = NOW(),
                DML_DataConclusao = CASE WHEN ? = 'concluido' THEN NOW() ELSE DML_DataConclusao END
            WHERE DML_ID = ?
        ");

        $update->execute([
            (int) ($dados['total'] ?? 0),
            (int) ($dados['enviados'] ?? 0),
            (int) ($dados['erros'] ?? 0),
            $status,
            $status,
            $loteId
        ]);

        return $status;
    }
}
