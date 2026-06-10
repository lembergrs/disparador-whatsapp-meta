<?php

namespace Models;

use Core\Database;
use PDO;

class ConsumoMensal
{
    private $db;

    public function __construct()
    {
        $this->db =
            Database::getInstance();
    }

    public function registrarMensagem($cliId)
    {
        $anoMes =
            date('Ym');

        $sql =
            $this->db->prepare("
                SELECT CMS_ID
                FROM consumo_mensal
                WHERE CLI_ID = ?
                AND CMS_AnoMes = ?
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

            $sql =
                $this->db->prepare("
                    UPDATE consumo_mensal
                    SET
                        CMS_Mensagens =
                            CMS_Mensagens + 1,
                        CMS_AtualizadoEm =
                            NOW()
                    WHERE CMS_ID = ?
                ");

            return $sql->execute([
                $registro['CMS_ID']
            ]);
        }

        $sql =
            $this->db->prepare("
                INSERT INTO consumo_mensal
                (
                    CLI_ID,
                    CMS_AnoMes,
                    CMS_Mensagens
                )
                VALUES
                (
                    ?, ?, 1
                )
            ");

        return $sql->execute([
            $cliId,
            $anoMes
        ]);
    }

    public function buscarMesAtual($cliId)
    {
        $anoMes =
            date('Ym');

        $sql =
            $this->db->prepare("
                SELECT *
                FROM consumo_mensal
                WHERE CLI_ID = ?
                AND CMS_AnoMes = ?
            ");

        $sql->execute([
            $cliId,
            $anoMes
        ]);

        return $sql->fetch(
            PDO::FETCH_ASSOC
        );
    }
}