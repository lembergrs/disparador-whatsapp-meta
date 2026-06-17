<?php

namespace Models;

use Core\Database;
use PDO;

class Assinatura
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function buscarPorId($id)
    {
        $sql = $this->db->prepare("\n            SELECT a.*, c.CLI_Nome, p.PLA_Nome\n            FROM assinaturas a\n            INNER JOIN clientes c ON c.CLI_ID = a.CLI_ID\n            INNER JOIN planos p ON p.PLA_ID = a.PLA_ID\n            WHERE a.ASS_ID = ?\n        ");

        $sql->execute([$id]);

        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function buscarAtualPorCliente($clienteId)
    {
        $sql = $this->db->prepare("\n            SELECT a.*, p.PLA_Nome\n            FROM assinaturas a\n            INNER JOIN planos p ON p.PLA_ID = a.PLA_ID\n            WHERE a.CLI_ID = ?\n            AND a.ASS_Status IN ('ativa','pendente','vencida')\n            ORDER BY\n                CASE a.ASS_Status\n                    WHEN 'ativa' THEN 1\n                    WHEN 'pendente' THEN 2\n                    WHEN 'vencida' THEN 3\n                    ELSE 4\n                END,\n                a.ASS_ID DESC\n            LIMIT 1\n        ");

        $sql->execute([$clienteId]);

        return $sql->fetch(PDO::FETCH_ASSOC);
    }


    public function buscarParaPagamento($clienteId, $planoId)
    {
        $sql = $this->db->prepare("
            SELECT a.*, p.PLA_Nome
            FROM assinaturas a
            INNER JOIN planos p ON p.PLA_ID = a.PLA_ID
            WHERE a.CLI_ID = ?
            AND a.PLA_ID = ?
            AND a.ASS_Status IN ('ativa','pendente','vencida')
            ORDER BY
                CASE a.ASS_Status
                    WHEN 'pendente' THEN 1
                    WHEN 'vencida' THEN 2
                    WHEN 'ativa' THEN 3
                    ELSE 4
                END,
                a.ASS_ID DESC
            LIMIT 1
        ");

        $sql->execute([
            $clienteId,
            $planoId
        ]);

        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function listar()
    {
        $sql = $this->db->query("\n            SELECT a.*, c.CLI_Nome, p.PLA_Nome\n            FROM assinaturas a\n            INNER JOIN clientes c ON c.CLI_ID = a.CLI_ID\n            INNER JOIN planos p ON p.PLA_ID = a.PLA_ID\n            ORDER BY a.ASS_ID DESC\n        ");

        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    public function criar($dados)
    {
        $this->encerrarVigentesDoCliente($dados['cliente']);

        $sql = $this->db->prepare("\n            INSERT INTO assinaturas\n            (\n                CLI_ID, PLA_ID, ASS_Ciclo, ASS_Status, ASS_Valor,\n                ASS_DiaVencimento, ASS_DataInicio, ASS_DataProximaCobranca,\n                ASS_DataCadastro, ASS_DataAtualizacao\n            )\n            VALUES\n            (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())\n        ");

        return $sql->execute([
            $dados['cliente'],
            $dados['plano'],
            $dados['ciclo'],
            $dados['status'] ?? 'pendente',
            $dados['valor'],
            $dados['dia_vencimento'],
            $dados['data_inicio'],
            $dados['proxima_cobranca']
        ]);
    }

    public function atualizar($id, $dados)
    {
        $assinatura = $this->buscarPorId($id);

        if(!$assinatura){
            return false;
        }

        if(($dados['status'] ?? $assinatura['ASS_Status']) == 'ativa'){
            $this->encerrarVigentesDoCliente($assinatura['CLI_ID'], $id);
        }

        $sql = $this->db->prepare("\n            UPDATE assinaturas\n            SET\n                PLA_ID = ?,\n                ASS_Ciclo = ?,\n                ASS_Status = ?,\n                ASS_Valor = ?,\n                ASS_DiaVencimento = ?,\n                ASS_DataInicio = ?,\n                ASS_DataFim = ?,\n                ASS_DataProximaCobranca = ?,\n                ASS_DataAtualizacao = NOW()\n            WHERE ASS_ID = ?\n        ");

        return $sql->execute([
            $dados['plano'],
            $dados['ciclo'],
            $dados['status'],
            $dados['valor'],
            $dados['dia_vencimento'],
            $dados['data_inicio'],
            $dados['data_fim'] ?? null,
            $dados['proxima_cobranca'],
            $id
        ]);
    }

    public function cancelar($id)
    {
        return $this->alterarStatus($id, 'cancelada', true);
    }

    public function ativar($id)
    {
        $assinatura = $this->buscarPorId($id);

        if($assinatura){
            $this->encerrarVigentesDoCliente($assinatura['CLI_ID'], $id);
        }

        return $this->alterarStatus($id, 'ativa');
    }

    public function marcarVencida($id)
    {
        return $this->alterarStatus($id, 'vencida');
    }

    public function criarOuAtualizarPorCliente($clienteId, $plano, $status = 'pendente', $opcoes = [])
    {
        $assinatura = $this->buscarAtualPorCliente($clienteId);
        $dados = [
            'cliente' => $clienteId,
            'plano' => $plano['PLA_ID'],
            'ciclo' => $opcoes['ciclo'] ?? $plano['PLA_Periodicidade'],
            'status' => $status,
            'valor' => $opcoes['valor'] ?? $plano['PLA_Valor'],
            'dia_vencimento' => (int) date('d'),
            'data_inicio' => date('Y-m-d'),
            'data_fim' => null,
            'proxima_cobranca' => $opcoes['proxima_cobranca'] ?? date('Y-m-d', strtotime('+3 days'))
        ];

        if(!$assinatura){
            return $this->criar($dados);
        }

        $mesmoPlano =
            (int) $assinatura['PLA_ID']
            ===
            (int) $plano['PLA_ID'];

        $mesmoCiclo =
            (string) $assinatura['ASS_Ciclo']
            ===
            (string) $dados['ciclo'];

        $assinaturaPodeSerAtualizada = in_array(
            $assinatura['ASS_Status'],
            ['ativa', 'pendente'],
            true
        );

        if($mesmoPlano && $mesmoCiclo && $assinaturaPodeSerAtualizada){
            return $this->atualizar($assinatura['ASS_ID'], $dados);
        }

        $this->encerrarVigentesDoCliente($clienteId);

        return $this->criar($dados);
    }

    private function alterarStatus($id, $status, $definirFim = false)
    {
        $sql = $this->db->prepare("\n            UPDATE assinaturas\n            SET ASS_Status = ?,\n                ASS_DataFim = CASE WHEN ? = 1 THEN CURDATE() WHEN ? = 'ativa' THEN NULL ELSE ASS_DataFim END,\n                ASS_DataAtualizacao = NOW()\n            WHERE ASS_ID = ?\n        ");

        return $sql->execute([
            $status,
            $definirFim ? 1 : 0,
            $status,
            $id
        ]);
    }

    private function encerrarVigentesDoCliente($clienteId, $ignorarId = null)
    {
        $params = [$clienteId];
        $filtroIgnorar = '';

        if($ignorarId){
            $filtroIgnorar = ' AND ASS_ID <> ?';
            $params[] = $ignorarId;
        }

        $sql = $this->db->prepare("\n            UPDATE assinaturas\n            SET ASS_Status = 'cancelada',\n                ASS_DataFim = COALESCE(ASS_DataFim, CURDATE()),\n                ASS_DataAtualizacao = NOW()\n            WHERE CLI_ID = ?\n            AND ASS_Status IN ('ativa','pendente')\n            {$filtroIgnorar}\n        ");

        return $sql->execute($params);
    }
}
