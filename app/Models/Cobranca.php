<?php

namespace Models;

use Core\Database;
use PDO;

class Cobranca
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function buscarPendentePorCliente($clienteId)
    {
        $sql = $this->db->prepare("
            SELECT c.*, p.PLA_Nome
            FROM cobrancas c
            LEFT JOIN planos p ON p.PLA_ID = c.PLA_ID
            WHERE c.CLI_ID = ?
            AND c.COB_Status = 'pendente'
            ORDER BY c.COB_ID DESC
            LIMIT 1
        ");

        $sql->execute([$clienteId]);

        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function criar($dados)
    {
        $campos = [
            'CLI_ID',
            'PLA_ID',
            'COB_Valor',
            'COB_Status',
            'COB_Forma',
            'COB_DataVencimento'
        ];
        $valores = [
            ':cliente',
            ':plano',
            ':valor',
            "'pendente'",
            "'bolepix'",
            ':vencimento'
        ];
        $params = [
            ':cliente' => $dados['cliente'],
            ':plano' => $dados['plano'],
            ':valor' => $dados['valor'],
            ':vencimento' => $dados['vencimento']
        ];

        if($this->colunaExiste('cobrancas', 'COB_Tipo')){
            $campos[] = 'COB_Tipo';
            $valores[] = ':tipo';
            $params[':tipo'] = $dados['tipo'] ?? 'mensalidade';
        }

        $sql = $this->db->prepare("
            INSERT INTO cobrancas (
                " . implode(', ', $campos) . "
            ) VALUES (
                " . implode(', ', $valores) . "
            )
        ");

        $sql->execute($params);

        return $this->db->lastInsertId();
    }

    public function marcarPago($id)
    {
        $sql = $this->db->prepare("
            UPDATE cobrancas
            SET
                COB_Status = 'pago',
                COB_DataPagamento = NOW()
            WHERE COB_ID = ?
        ");

        return $sql->execute([$id]);
    }

    public function listar()
    {
        $sql = $this->db->query("
            SELECT
                c.*,
                cli.CLI_Nome,
                p.PLA_Nome
            FROM cobrancas c
            LEFT JOIN clientes cli
                ON cli.CLI_ID = c.CLI_ID
            LEFT JOIN planos p
                ON p.PLA_ID = c.PLA_ID
            ORDER BY c.COB_ID DESC
        ");

        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscar($id)
    {
        $sql = $this->db->prepare("
            SELECT *
            FROM cobrancas
            WHERE COB_ID = ?
        ");

        $sql->execute([$id]);

        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function cancelar($id)
    {
        $sql = $this->db->prepare("
            UPDATE cobrancas
            SET COB_Status = 'cancelado'
            WHERE COB_ID = ?
        ");

        return $sql->execute([$id]);
    }



    private function colunaExiste($tabela, $coluna)
    {
        static $cache = [];

        $chave = $tabela . '.' . $coluna;

        if(array_key_exists($chave, $cache)){
            return $cache[$chave];
        }

        $sql = $this->db->prepare("
            SHOW COLUMNS FROM {$tabela} LIKE ?
        ");

        $sql->execute([$coluna]);

        $cache[$chave] = (bool) $sql->fetch(PDO::FETCH_ASSOC);

        return $cache[$chave];
    }
}
