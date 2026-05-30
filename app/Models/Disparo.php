<?php

namespace Models;

use Core\Database;
use PDO;

class Disparo
{
    private $db;





    public function __construct()
    {
        $this->db =
            Database::getInstance();
    }






    public function salvar($dados)
    {
        $sql = $this->db->prepare("

            INSERT INTO disparos (

                CLI_ID,
                MTA_ID,
                TMP_ID,
                DSP_Numero,
                DSP_Template,
                DSP_Variaveis,
                DSP_MessageId,
                DSP_Status,
                DSP_Retorno

            ) VALUES (

                ?, ?, ?, ?, ?, ?, ?, ?, ?

            )

        ");





        return $sql->execute([

            $dados['cliente'],
            $dados['meta'],
            $dados['template_id'],
            $dados['numero'],
            $dados['template'],
            json_encode(
                $dados['variaveis']
            ),
            $dados['message_id'],
            $dados['status'],
            json_encode(
                $dados['retorno']
            )

        ]);
    }
}