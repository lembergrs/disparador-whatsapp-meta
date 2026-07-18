<?php

namespace Models;

use Core\Database;
use PDO;
use Services\TelefoneService;

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
            TelefoneService::normalizar($dados['numero']),
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

    public function buscarPorMessageIds($clienteId, array $messageIds)
    {
        $messageIds = array_values(
            array_filter(
                array_unique($messageIds),
                function($messageId){
                    return is_string($messageId) && trim($messageId) !== '';
                }
            )
        );

        if(empty($messageIds)){
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($messageIds), '?'));

        $sql = $this->db->prepare("
            SELECT
                DSP_MessageId,
                DSP_Status,
                DSP_Retorno
            FROM disparos
            WHERE CLI_ID = ?
            AND DSP_MessageId IN ({$placeholders})
        ");

        $sql->execute(
            array_merge(
                [$clienteId],
                $messageIds
            )
        );

        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }
}
