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





    private function extrairHeaderMetadados(array $componentes)
    {
        $dados = [
            'tipo' => null,
            'modo' => 'nenhuma',
            'url_exemplo' => null,
            'handle' => null,
            'documento_nome' => null
        ];

        foreach($componentes as $componente){
            if(($componente['type'] ?? '') != 'HEADER'){
                continue;
            }

            $tipo = strtoupper((string) ($componente['format'] ?? ''));
            $dados['tipo'] = $tipo ?: null;

            if(in_array($tipo, ['IMAGE', 'VIDEO', 'DOCUMENT'], true)){
                $dados['modo'] = 'estatica';
                $handles = $componente['example']['header_handle'] ?? [];
                if(is_array($handles) && !empty($handles[0])){
                    $dados['handle'] = $handles[0];
                }
                $dados['documento_nome'] = $componente['media_name'] ?? null;
            }

            break;
        }

        return $dados;
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

        $componentes =
            $this->preservarMapeamentoVariaveis(
                $metaId,
                $template['id'],
                $template['components'] ?? []
            );

        $headerMetadados = $this->extrairHeaderMetadados($componentes);





        if($existe){

            $update = $this->db->prepare("

                UPDATE templates_meta SET

                    TMP_Nome = ?,
                    TMP_Categoria = ?,
                    TMP_Idioma = ?,
                    TMP_Status = ?,
                    TMP_HeaderTipo = ?,
                    TMP_HeaderMidiaModo = ?,
                    TMP_HeaderMidiaUrlExemplo = ?,
                    TMP_HeaderMidiaHandle = ?,
                    TMP_HeaderDocumentoNome = ?,
                    TMP_Componentes = ?,
                    TMP_DataSync = NOW()

                WHERE TMP_ID = ?

            ");





            return $update->execute([

                $template['name'],

                $template['category'],

                $template['language'],

                $template['status'],

                $headerMetadados['tipo'],

                $headerMetadados['modo'],

                $headerMetadados['url_exemplo'],

                $headerMetadados['handle'],

                $headerMetadados['documento_nome'],

                json_encode(
                    $componentes
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
                TMP_HeaderTipo,
                TMP_HeaderMidiaModo,
                TMP_HeaderMidiaUrlExemplo,
                TMP_HeaderMidiaHandle,
                TMP_HeaderDocumentoNome,
                TMP_Componentes,
                TMP_DataSync

            )

            VALUES
            (

                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()

            )

        ");





        return $insert->execute([

            $metaId,

            $template['id'],

            $template['name'],

            $template['category'],

            $template['language'],

            $template['status'],

            $headerMetadados['tipo'],

            $headerMetadados['modo'],

            $headerMetadados['url_exemplo'],

            $headerMetadados['handle'],

            $headerMetadados['documento_nome'],

            json_encode(
                $componentes
            )

        ]);
    }




    private function preservarMapeamentoVariaveis($metaId, $metaTemplateId, $componentesNovos)
    {
        if(!is_array($componentesNovos)){
            $componentesNovos = [];
        }

        $sql = $this->db->prepare("
            SELECT TMP_Componentes
            FROM templates_meta
            WHERE TMP_MetaId = ?
            AND MTA_ID = ?
            LIMIT 1
        ");

        $sql->execute([
            $metaTemplateId,
            $metaId
        ]);

        $atual = $sql->fetch(PDO::FETCH_ASSOC);

        if(!$atual){
            return $componentesNovos;
        }

        $componentesAtuais = json_decode(
            $atual['TMP_Componentes'] ?? '[]',
            true
        );

        if(!is_array($componentesAtuais)){
            return $componentesNovos;
        }

        foreach($componentesAtuais as $componente){
            if(($componente['type'] ?? '') == 'VARIABLE_MAPPING'){
                return $componentesAtuais;
            }
        }

        return $componentesNovos;
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

    public function buscarPorCliente($id, $clienteId)
    {
        $sql = $this->db->prepare("
            SELECT t.*
            FROM templates_meta t
            INNER JOIN meta_contas m
                ON m.MTA_ID = t.MTA_ID
            WHERE t.TMP_ID = ?
            AND m.CLI_ID = ?
            AND t.TMP_Ativo = 'S'
            AND m.MTA_Ativo = 'S'
            LIMIT 1
        "
        );

        $sql->execute([
            $id,
            $clienteId
        ]);

        return $sql->fetch(PDO::FETCH_ASSOC);
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

    public function inativar($id, $clienteId)
    {
        $sql = $this->db->prepare("

            UPDATE templates_meta t
            INNER JOIN meta_contas m
                ON m.MTA_ID = t.MTA_ID

            SET t.TMP_Ativo = 'N'

            WHERE t.TMP_ID = ?
            AND m.CLI_ID = ?

        ");

        return $sql->execute([
            $id,
            $clienteId
        ]);
    }

}