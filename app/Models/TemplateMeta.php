<?php

namespace Models;

use Core\Database;
use PDO;

class TemplateMeta
{
    private $db;





    public function __construct()
    {
        $this->db =
            Database::getInstance();
    }





    public function salvarOuAtualizar(
        $metaId,
        $template
    )
    {
        $sql = $this->db->prepare("

            SELECT TMP_ID
            FROM templates_meta
            WHERE TMP_MetaId = ?
            AND MTA_ID = ?

        ");

        $sql->execute([

            $template['id'],
            $metaId

        ]);


        $existe =
            $sql->fetch();





        if($existe){

            $update = $this->db->prepare("

                UPDATE templates_meta SET

                    TMP_Nome = ?,
                    TMP_Categoria = ?,
                    TMP_Idioma = ?,
                    TMP_Status = ?,
                    TMP_Componentes = ?,
                    TMP_DataSync = NOW(),
                    TMP_Ativo = 'S'

                WHERE TMP_ID = ?

            ");





            return $update->execute([

                $template['name'],

                $template['category'],

                $template['language'],

                $template['status'],

                json_encode(
                    $template['components']
                ),

                $existe['TMP_ID']

            ]);

        }






        $insert = $this->db->prepare("

            INSERT INTO templates_meta
            (

                MTA_ID,
                TMP_MetaId,
                TMP_Nome,
                TMP_Categoria,
                TMP_Idioma,
                TMP_Status,
                TMP_Componentes,
                TMP_DataSync

            )

            VALUES
            (

                ?, ?, ?, ?, ?, ?, ?, NOW()

            )

        ");





        return $insert->execute([

            $metaId,

            $template['id'],

            $template['name'],

            $template['category'],

            $template['language'],

            $template['status'],

            json_encode(
                $template['components']
            )

        ]);
    }





    public function listarPorCliente($clienteId)
    {
        $sql = $this->db->prepare("

            SELECT

                t.*,
                m.MTA_Nome

            FROM templates_meta t

            INNER JOIN meta_contas m
            ON m.MTA_ID = t.MTA_ID

            INNER JOIN clientes c
            ON c.CLI_ID = m.CLI_ID

            WHERE c.CLI_ID = ?
                AND TMP_Ativo = 'S'

            ORDER BY TMP_ID DESC

        ");





        $sql->execute([
            $clienteId
        ]);





        return $sql->fetchAll(
            PDO::FETCH_ASSOC
        );
    }

    public function extrairVariaveis($componentes)
    {
        $componentes =
            json_decode(
                $componentes,
                true
            );

        $variaveis = [];





        foreach($componentes as $comp){

            if(isset($comp['text'])){

                preg_match_all(
                    '/{{(.*?)}}/',
                    $comp['text'],
                    $matches
                );





                if(isset($matches[1])){

                    foreach($matches[1] as $var){

                        $variaveis[] = $var;

                    }

                }

            }

        }





        return array_unique(
            $variaveis
        );
    }

    public function buscar($id)
    {
        $sql = $this->db->prepare("

            SELECT *
            FROM templates_meta
            WHERE TMP_ID = ?
                AND TMP_Ativo = 'S'

        ");





        $sql->execute([$id]);





        return $sql->fetch(
            PDO::FETCH_ASSOC
        );
    }

    public function inativarAusentes(
        $metaId,
        $idsMeta
    )
    {
        if(empty($idsMeta)){

            $sql = $this->db->prepare("
                UPDATE templates_meta
                SET TMP_Ativo = 'N'
                WHERE MTA_ID = ?
            ");

            $sql->execute([$metaId]);

            return;
        }

        $placeholders = implode(
            ',',
            array_fill(
                0,
                count($idsMeta),
                '?'
            )
        );

        $params = array_merge(
            [$metaId],
            $idsMeta
        );

        $sql = $this->db->prepare("
            UPDATE templates_meta
            SET TMP_Ativo = 'N'
            WHERE MTA_ID = ?
            AND TMP_MetaId NOT IN ($placeholders)
        ");

        $sql->execute($params);
    }

}