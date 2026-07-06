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

    public function criarLote($clienteId, $metaId, $templateId, $total, array $midiaHeader = [])
    {
        $sql = $this->db->prepare("
            INSERT INTO disparo_manual_lotes
            (
                CLI_ID, MTA_ID, TMP_ID,
                DML_HeaderMidiaTipo, DML_HeaderMidiaId, DML_HeaderMidiaNome, DML_HeaderMidiaMime, DML_HeaderMidiaTamanho,
                DML_Total, DML_Status, DML_DataCadastro, DML_DataAtualizacao
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendente', NOW(), NOW())
        ");

        $sql->execute([
            $clienteId,
            $metaId,
            $templateId,
            $midiaHeader['tipo'] ?? null,
            $midiaHeader['media_id'] ?? null,
            $midiaHeader['nome_original'] ?? null,
            $midiaHeader['mime'] ?? null,
            $midiaHeader['tamanho'] ?? null,
            $total
        ]);

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
                DMI_DataCadastro,
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

    public function contarLotesCliente($clienteId, array $filtros = [])
    {
        [$where, $params] = $this->montarFiltrosLotes($clienteId, $filtros);

        $sql = $this->db->prepare("
            SELECT COUNT(*)
            FROM disparo_manual_lotes l
            WHERE {$where}
        ");

        $sql->execute($params);

        return (int) $sql->fetchColumn();
    }

    public function listarLotesClientePaginado($clienteId, array $filtros, $limit, $offset)
    {
        $limit = max(10, min(50, (int) $limit));
        $offset = max(0, (int) $offset);
        [$where, $params] = $this->montarFiltrosLotes($clienteId, $filtros);

        $sql = $this->db->prepare("
            SELECT
                l.*,
                m.MTA_Nome,
                t.TMP_Nome,
                COALESCE(a.total_itens, 0) AS total_itens,
                COALESCE(a.total_pendente, 0) AS total_pendente,
                COALESCE(a.total_processando, 0) AS total_processando,
                COALESCE(a.total_aguardando_confirmacao, 0) AS total_aguardando_confirmacao,
                COALESCE(a.total_enviado, 0) AS total_enviado,
                COALESCE(a.total_delivered, 0) AS total_delivered,
                COALESCE(a.total_read, 0) AS total_read,
                COALESCE(a.total_erro, 0) AS total_erro
            FROM disparo_manual_lotes l
            LEFT JOIN meta_contas m ON m.MTA_ID = l.MTA_ID
            LEFT JOIN templates_meta t ON t.TMP_ID = l.TMP_ID
            LEFT JOIN (
                SELECT
                    DML_ID,
                    CLI_ID,
                    COUNT(DMI_ID) AS total_itens,
                    SUM(CASE WHEN DMI_Status = 'pendente' THEN 1 ELSE 0 END) AS total_pendente,
                    SUM(CASE WHEN DMI_Status = 'processando' THEN 1 ELSE 0 END) AS total_processando,
                    SUM(CASE WHEN DMI_Status = 'aguardando_confirmacao' THEN 1 ELSE 0 END) AS total_aguardando_confirmacao,
                    SUM(CASE WHEN DMI_Status IN ('enviado','sent') THEN 1 ELSE 0 END) AS total_enviado,
                    SUM(CASE WHEN DMI_Status IN ('delivered','entregue') THEN 1 ELSE 0 END) AS total_delivered,
                    SUM(CASE WHEN DMI_Status IN ('read','lido') THEN 1 ELSE 0 END) AS total_read,
                    SUM(CASE WHEN DMI_Status IN ('erro','failed') THEN 1 ELSE 0 END) AS total_erro
                FROM disparo_manual_itens
                GROUP BY DML_ID, CLI_ID
            ) a ON a.DML_ID = l.DML_ID AND a.CLI_ID = l.CLI_ID
            WHERE {$where}
            ORDER BY l.DML_ID DESC
            LIMIT {$limit} OFFSET {$offset}
        ");

        $sql->execute($params);

        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarLoteDetalhadoCliente($loteId, $clienteId)
    {
        $sql = $this->db->prepare("
            SELECT
                l.*,
                m.MTA_Nome,
                t.TMP_Nome
            FROM disparo_manual_lotes l
            LEFT JOIN meta_contas m ON m.MTA_ID = l.MTA_ID
            LEFT JOIN templates_meta t ON t.TMP_ID = l.TMP_ID
            WHERE l.DML_ID = ?
            AND l.CLI_ID = ?
            LIMIT 1
        ");

        $sql->execute([$loteId, $clienteId]);

        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    private function montarFiltrosLotes($clienteId, array $filtros)
    {
        $where = ['l.CLI_ID = :cliente'];
        $params = [':cliente' => (int) $clienteId];

        if(!empty($filtros['data_inicial'])){
            $where[] = 'l.DML_DataCadastro >= :data_inicial';
            $params[':data_inicial'] = $filtros['data_inicial'] . ' 00:00:00';
        }

        if(!empty($filtros['data_final'])){
            $where[] = 'l.DML_DataCadastro <= :data_final';
            $params[':data_final'] = $filtros['data_final'] . ' 23:59:59';
        }

        if(!empty($filtros['status'])){
            $where[] = 'l.DML_Status = :status';
            $params[':status'] = $filtros['status'];
        }

        if(!empty($filtros['template'])){
            $where[] = 'l.TMP_ID = :template';
            $params[':template'] = (int) $filtros['template'];
        }

        if(!empty($filtros['numero'])){
            $where[] = "EXISTS (
                SELECT 1
                FROM disparo_manual_itens ix
                WHERE ix.DML_ID = l.DML_ID
                AND ix.CLI_ID = l.CLI_ID
                AND ix.DMI_Numero LIKE :numero
            )";
            $params[':numero'] = '%' . preg_replace('/\D/', '', (string) $filtros['numero']) . '%';
        }

        return [implode(' AND ', $where), $params];
    }


    public function recalcularLote($loteId)
    {
        $sql = $this->db->prepare("
            SELECT
                COUNT(*) total,
                SUM(CASE WHEN DMI_Status IN ('aguardando_confirmacao','enviado','entregue','lido') THEN 1 ELSE 0 END) enviados,
                SUM(CASE WHEN DMI_Status IN ('erro','failed') THEN 1 ELSE 0 END) erros,
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
