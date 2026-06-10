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
        $valorUnitario
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

            $valorTotal =
                $novoTotal
                * $valorUnitario;

            $sql =
                $this->db->prepare("
                    UPDATE excedentes_mensais
                    SET
                        EXC_Mensagens = ?,
                        EXC_ValorTotal = ?,
                        EXC_ValorUnitario = ?
                    WHERE EXC_ID = ?
                ");

            return $sql->execute([
                $novoTotal,
                $valorTotal,
                $valorUnitario,
                $registro['EXC_ID']
            ]);
        }

        $sql =
            $this->db->prepare("
                INSERT INTO excedentes_mensais
                (
                    CLI_ID,
                    EXC_AnoMes,
                    EXC_Mensagens,
                    EXC_ValorUnitario,
                    EXC_ValorTotal
                )
                VALUES
                (
                    ?, ?, 1, ?, ?
                )
            ");

        return $sql->execute([
            $cliId,
            $anoMes,
            $valorUnitario,
            $valorUnitario
        ]);
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
}