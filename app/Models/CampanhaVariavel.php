<?php

namespace Models;

use Core\Database;
use PDO;

class CampanhaVariavel
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function salvar($campanhaId, $variavel, $campo)
    {
        $sql = $this->db->prepare("
            INSERT INTO campanha_variaveis
            (
                CAM_ID,
                CPV_Variavel,
                CPV_Campo
            )
            VALUES
            (
                ?, ?, ?
            )
        ");

        return $sql->execute([
            $campanhaId,
            $variavel,
            $campo
        ]);
    }

    public function listarPorCampanha($campanhaId)
    {
        $sql = $this->db->prepare("
            SELECT *
            FROM campanha_variaveis
            WHERE CAM_ID = ?
            ORDER BY CPV_Variavel ASC
        ");

        $sql->execute([$campanhaId]);

        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }
}