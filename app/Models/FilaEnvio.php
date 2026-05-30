<?php

namespace Models;

use Core\Database;

class FilaEnvio
{
    private $db;

    public function __construct()
    {
        $this->db =
            Database::getInstance();
    }

    public function adicionar(
        $campanhaId,
        $contatoId
    )
    {
        $sql = $this->db->prepare("

            INSERT INTO fila_envio (

                CAM_ID,
                CON_ID

            ) VALUES (

                ?, ?

            )

        ");

        return $sql->execute([

            $campanhaId,
            $contatoId

        ]);
    }
}