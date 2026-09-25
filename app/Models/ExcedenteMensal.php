<?php

namespace Models;

use Core\Database;
use PDO;

class ExcedenteMensal
{
    private $db;

    public function __construct()
    {
        $this->db =
            Database::getInstance();
    }

    public function registrarExcedente(
        $cliId,
        $valorUnitario,
        $dadosCiclo = []
    )
    {
        $anoMes = date('Ym');
        $campos = [
            'CLI_ID',
            'EXC_AnoMes',
            'EXC_Mensagens',
            'EXC_ValorUnitario',
            'EXC_ValorTotal'
        ];
        $placeholders = ['?', '?', '1', '?', '?'];
        $params = [$cliId, $anoMes, $valorUnitario, $valorUnitario];
        // MariaDB avalia as atribuicoes do ON DUPLICATE KEY UPDATE da esquerda
        // para a direita. Recalcula o total antes de incrementar a quantidade.
        $atualizacoes = [
            'EXC_ValorTotal = (EXC_Mensagens + 1) * EXC_ValorUnitario',
            'EXC_Mensagens = EXC_Mensagens + 1'
        ];

        if(
            !empty($dadosCiclo['PLA_ID'])
            && $this->colunaExiste('excedentes_mensais', 'EXC_PLA_ID')
        ){
            $campos[] = 'EXC_PLA_ID';
            $placeholders[] = '?';
            $params[] = $dadosCiclo['PLA_ID'];
            $atualizacoes[] = 'EXC_PLA_ID = COALESCE(EXC_PLA_ID, VALUES(EXC_PLA_ID))';
        }

        if(
            isset($dadosCiclo['PLA_LimiteMensagens'])
            && $this->colunaExiste('excedentes_mensais', 'EXC_LimiteMensagens')
        ){
            $campos[] = 'EXC_LimiteMensagens';
            $placeholders[] = '?';
            $params[] = (int) $dadosCiclo['PLA_LimiteMensagens'];
            $atualizacoes[] = 'EXC_LimiteMensagens = COALESCE(EXC_LimiteMensagens, VALUES(EXC_LimiteMensagens))';
        }

        $sql = $this->db->prepare("
            INSERT INTO excedentes_mensais
            (" . implode(', ', $campos) . ")
            VALUES
            (" . implode(', ', $placeholders) . ")
            ON DUPLICATE KEY UPDATE
            " . implode(', ', $atualizacoes) . "
        ");

        return $sql->execute($params);
    }

    public function buscarMesAtual($cliId)
    {
        $sql =
            $this->db->prepare("
                SELECT *
                FROM excedentes_mensais
                WHERE CLI_ID = ?
                AND EXC_AnoMes = ?
            ");

        $sql->execute([
            $cliId,
            date('Ym')
        ]);

        return $sql->fetch(
            PDO::FETCH_ASSOC
        );
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
