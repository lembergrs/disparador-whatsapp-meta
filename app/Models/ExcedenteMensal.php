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
        $anoMes =
            date('Ym');

        $sql =
            $this->db->prepare("
                SELECT *
                FROM excedentes_mensais
                WHERE CLI_ID = ?
                AND EXC_AnoMes = ?
            ");

        $sql->execute([
            $cliId,
            $anoMes
        ]);

        $registro =
            $sql->fetch(
                PDO::FETCH_ASSOC
            );

        if($registro){

            $novoTotal =
                $registro['EXC_Mensagens']
                + 1;

            $valorUnitarioCiclo =
                (float)(
                    $registro['EXC_ValorUnitario']
                    ?? $valorUnitario
                );

            $valorTotal =
                $novoTotal
                * $valorUnitarioCiclo;

            $camposAtualizacao = [
                'EXC_Mensagens = ?',
                'EXC_ValorTotal = ?'
            ];
            $paramsAtualizacao = [
                $novoTotal,
                $valorTotal
            ];

            if(
                !empty($dadosCiclo['PLA_ID'])
                && $this->colunaExiste('excedentes_mensais', 'EXC_PLA_ID')
            ){
                $camposAtualizacao[] = 'EXC_PLA_ID = COALESCE(EXC_PLA_ID, ?)';
                $paramsAtualizacao[] = $dadosCiclo['PLA_ID'];
            }

            if(
                isset($dadosCiclo['PLA_LimiteMensagens'])
                && $this->colunaExiste('excedentes_mensais', 'EXC_LimiteMensagens')
            ){
                $camposAtualizacao[] = 'EXC_LimiteMensagens = COALESCE(EXC_LimiteMensagens, ?)';
                $paramsAtualizacao[] = (int) $dadosCiclo['PLA_LimiteMensagens'];
            }

            $paramsAtualizacao[] = $registro['EXC_ID'];

            $sql =
                $this->db->prepare("
                    UPDATE excedentes_mensais
                    SET " . implode(', ', $camposAtualizacao) . "
                    WHERE EXC_ID = ?
                ");

            return $sql->execute($paramsAtualizacao);
        }

        $campos = [
            'CLI_ID',
            'EXC_AnoMes',
            'EXC_Mensagens',
            'EXC_ValorUnitario',
            'EXC_ValorTotal'
        ];
        $placeholders = ['?', '?', '1', '?', '?'];
        $params = [$cliId, $anoMes, $valorUnitario, $valorUnitario];

        if(
            !empty($dadosCiclo['PLA_ID'])
            && $this->colunaExiste('excedentes_mensais', 'EXC_PLA_ID')
        ){
            $campos[] = 'EXC_PLA_ID';
            $placeholders[] = '?';
            $params[] = $dadosCiclo['PLA_ID'];
        }

        if(
            isset($dadosCiclo['PLA_LimiteMensagens'])
            && $this->colunaExiste('excedentes_mensais', 'EXC_LimiteMensagens')
        ){
            $campos[] = 'EXC_LimiteMensagens';
            $placeholders[] = '?';
            $params[] = (int) $dadosCiclo['PLA_LimiteMensagens'];
        }

        $sql =
            $this->db->prepare("
                INSERT INTO excedentes_mensais
                (" . implode(', ', $campos) . ")
                VALUES
                (" . implode(', ', $placeholders) . ")
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
