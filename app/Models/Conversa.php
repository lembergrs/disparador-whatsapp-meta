<?php

namespace Models;

use Core\Database;
use PDO;

class Conversa
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function buscarOuCriar($clienteId, $metaId, $numero, $nome = null)
    {
        $sql = $this->db->prepare("
            SELECT *
            FROM conversas
            WHERE CLI_ID = ?
            AND MTA_ID = ?
            AND CVS_Numero = ?
            LIMIT 1
        ");

        $sql->execute([
            $clienteId,
            $metaId,
            $numero
        ]);

        $conversa = $sql->fetch(PDO::FETCH_ASSOC);

        if($conversa){
            return $conversa['CVS_ID'];
        }

        $sql = $this->db->prepare("
            INSERT INTO conversas
            (
                CLI_ID,
                MTA_ID,
                CVS_Numero,
                CVS_Nome,
                CVS_DataUltimaMensagem
            )
            VALUES
            (
                ?, ?, ?, ?, NOW()
            )
        ");

        $sql->execute([
            $clienteId,
            $metaId,
            $numero,
            $nome
        ]);

        return $this->db->lastInsertId();
    }

    public function salvarMensagem($dados)
    {
        $sql = $this->db->prepare("
            INSERT INTO conversa_mensagens
            (
                CVS_ID,
                MSG_Direcao,
                MSG_Tipo,
                MSG_Texto,
                MSG_MetaMessageId,
                MSG_Status,
                MSG_Retorno,
                MSG_DataMensagem
            )
            VALUES
            (
                ?, ?, ?, ?, ?, ?, ?, ?
            )
        ");

        $sql->execute([
            $dados['conversa_id'],
            $dados['direcao'],
            $dados['tipo'] ?? 'text',
            $dados['texto'] ?? null,
            $dados['message_id'] ?? null,
            $dados['status'] ?? null,
            json_encode($dados['retorno'] ?? [], JSON_UNESCAPED_UNICODE),
            $dados['data_mensagem'] ?? date('Y-m-d H:i:s')
        ]);

        $this->atualizarResumo(
            $dados['conversa_id'],
            $dados['texto'] ?? '',
            $dados['direcao']
        );

        return $this->db->lastInsertId();
    }

    public function atualizarResumo($conversaId, $ultimaMensagem, $direcao)
    {
        if($direcao == 'recebida'){

            $sql = $this->db->prepare("

                UPDATE conversas
                SET
                    CVS_UltimaMensagem = ?,
                    CVS_DataUltimaMensagem = NOW(),
                    CVS_NaoLida = 'S',
                    CVS_QtdeNaoLidas = CVS_QtdeNaoLidas + 1
                WHERE CVS_ID = ?

            ");

            return $sql->execute([
                $ultimaMensagem,
                $conversaId
            ]);

        }

        $sql = $this->db->prepare("

            UPDATE conversas
            SET
                CVS_UltimaMensagem = ?,
                CVS_DataUltimaMensagem = NOW()
            WHERE CVS_ID = ?

        ");

        return $sql->execute([
            $ultimaMensagem,
            $conversaId
        ]);
    }

    public function listarConversas(
        $clienteId,
        $busca = '',
        $status = '',
        $etiqueta = ''
    )
    {
        $where = [];
        $params = [];

        $where[] = "c.CLI_ID = ?";
        $params[] = $clienteId;

        $where[] = "c.CVS_Ativo = 'S'";

        $busca = trim($busca);
        $status = trim($status);
        $etiqueta = (int) $etiqueta;

        if($busca != ''){

            $buscaNumerica =
                preg_replace('/\D/', '', $busca);

            if($buscaNumerica != ''){

                $where[] = "
                    (
                        c.CVS_Nome LIKE ?
                        OR c.CVS_Numero LIKE ?
                        OR c.CVS_Numero LIKE ?
                    )
                ";

                $params[] = '%' . $busca . '%';
                $params[] = '%' . $busca . '%';
                $params[] = '%' . $buscaNumerica . '%';

            }else{

                $where[] = "c.CVS_Nome LIKE ?";
                $params[] = '%' . $busca . '%';

            }
        }

        if($status == 'N'){

            $where[] = "c.CVS_NaoLida = 'S'";

        }elseif($status == 'L'){

            $where[] = "c.CVS_NaoLida = 'N'";
        }

        if($etiqueta > 0){

            $where[] = "EXISTS (
                SELECT 1
                FROM conversa_etiqueta_vinculos vf
                INNER JOIN conversa_etiquetas ef
                    ON ef.ETQ_ID = vf.ETQ_ID
                WHERE vf.CVS_ID = c.CVS_ID
                AND ef.CLI_ID = c.CLI_ID
                AND ef.ETQ_Ativo = 'S'
                AND ef.ETQ_ID = ?
            )";

            $params[] = $etiqueta;
        }

        $sql = "
            SELECT
                c.*,

                GROUP_CONCAT(
                    DISTINCT CONCAT(
                        e.ETQ_Nome,
                        '#',
                        e.ETQ_Cor
                    )
                    ORDER BY e.ETQ_Nome ASC
                    SEPARATOR '|'
                ) AS Etiquetas

            FROM conversas c

            LEFT JOIN conversa_etiqueta_vinculos v
                ON v.CVS_ID = c.CVS_ID

            LEFT JOIN conversa_etiquetas e
                ON e.ETQ_ID = v.ETQ_ID
                AND e.CLI_ID = c.CLI_ID
                AND e.ETQ_Ativo = 'S'

            WHERE " . implode(' AND ', $where) . "

            GROUP BY c.CVS_ID

            ORDER BY c.CVS_DataUltimaMensagem DESC

            LIMIT 100
        ";

        $query = $this->db->prepare($sql);

        $query->execute($params);

        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarMensagens($conversaId)
    {
        $sql = $this->db->prepare("

            SELECT *

            FROM conversa_mensagens

            WHERE CVS_ID = ?

            ORDER BY MSG_ID DESC

            LIMIT 100

        ");

        $sql->execute([$conversaId]);

        $mensagens = $sql->fetchAll(PDO::FETCH_ASSOC);

        return array_reverse($mensagens);
    }

    public function buscar($conversaId, $clienteId)
    {
        $sql = $this->db->prepare("
            SELECT *
            FROM conversas
            WHERE CVS_ID = ?
            AND CLI_ID = ?
            LIMIT 1
        ");

        $sql->execute([
            $conversaId,
            $clienteId
        ]);

        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function marcarComoLida($conversaId, $clienteId)
    {
        $sql = $this->db->prepare("

            UPDATE conversas
            SET
                CVS_NaoLida = 'N',
                CVS_QtdeNaoLidas = 0
            WHERE CVS_ID = ?
            AND CLI_ID = ?

        ");

        return $sql->execute([
            $conversaId,
            $clienteId
        ]);
    }

    public function marcarComoNaoLida($conversaId, $clienteId)
    {
        $sql = $this->db->prepare("
            UPDATE conversas
            SET 
                CVS_NaoLida = 'S',
                CVS_QtdeNaoLidas = CASE 
                    WHEN CVS_QtdeNaoLidas <= 0 THEN 1 
                    ELSE CVS_QtdeNaoLidas 
                END
            WHERE CVS_ID = ?
            AND CLI_ID = ?
        ");

        return $sql->execute([
            $conversaId,
            $clienteId
        ]);
    }

    public function ultimaMensagemRecebida($conversaId)
    {
        $sql = $this->db->prepare("

            SELECT MSG_DataMensagem

            FROM conversa_mensagens

            WHERE CVS_ID = ?
            AND MSG_Direcao = 'recebida'

            ORDER BY MSG_DataMensagem DESC

            LIMIT 1

        ");

        $sql->execute([
            $conversaId
        ]);

        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function ultimaAtualizacaoCliente($clienteId)
    {
        $sql = $this->db->prepare("

            SELECT MAX(CVS_DataUltimaMensagem) AS ultima

            FROM conversas

            WHERE CLI_ID = ?
            AND CVS_Ativo = 'S'

        ");

        $sql->execute([
            $clienteId
        ]);

        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function listarEtiquetas($clienteId)
    {
        $sql = $this->db->prepare("
            SELECT *
            FROM conversa_etiquetas
            WHERE CLI_ID = ?
            AND ETQ_Ativo = 'S'
            ORDER BY ETQ_Nome ASC
        ");

        $sql->execute([
            $clienteId
        ]);

        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    public function etiquetasDaConversa($conversaId, $clienteId)
    {
        $sql = $this->db->prepare("
            SELECT e.*
            FROM conversa_etiquetas e
            INNER JOIN conversa_etiqueta_vinculos v
                ON v.ETQ_ID = e.ETQ_ID
            INNER JOIN conversas c
                ON c.CVS_ID = v.CVS_ID
            WHERE v.CVS_ID = ?
            AND e.CLI_ID = ?
            AND c.CLI_ID = ?
            AND e.ETQ_Ativo = 'S'
            ORDER BY e.ETQ_Nome ASC
        ");

        $sql->execute([
            $conversaId,
            $clienteId,
            $clienteId
        ]);

        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    public function salvarEtiquetasConversa($conversaId, $clienteId, $etiquetas)
    {
        $conversa =
            $this->buscar(
                $conversaId,
                $clienteId
            );

        if(!$conversa){
            return false;
        }

        $sql = $this->db->prepare("
            DELETE v
            FROM conversa_etiqueta_vinculos v
            INNER JOIN conversa_etiquetas e
                ON e.ETQ_ID = v.ETQ_ID
            WHERE v.CVS_ID = ?
            AND e.CLI_ID = ?
        ");

        $sql->execute([
            $conversaId,
            $clienteId
        ]);

        if(!empty($etiquetas)){

            $insert = $this->db->prepare("
                INSERT IGNORE INTO conversa_etiqueta_vinculos
                (
                    CVS_ID,
                    ETQ_ID
                )
                SELECT
                    ?,
                    ETQ_ID
                FROM conversa_etiquetas
                WHERE ETQ_ID = ?
                AND CLI_ID = ?
                AND ETQ_Ativo = 'S'
                LIMIT 1
            ");

            foreach($etiquetas as $etqId){

                $etqId =
                    (int) $etqId;

                if($etqId <= 0){
                    continue;
                }

                $insert->execute([
                    $conversaId,
                    $etqId,
                    $clienteId
                ]);

            }

        }

        return true;
    }

    public function criarEtiqueta($clienteId, $nome, $cor = 'secondary')
    {
        $sql = $this->db->prepare("
            INSERT INTO conversa_etiquetas
            (
                CLI_ID,
                ETQ_Nome,
                ETQ_Cor
            )
            VALUES
            (
                ?, ?, ?
            )
        ");

        $sql->execute([
            $clienteId,
            $nome,
            $cor
        ]);

        return $this->db->lastInsertId();
    }

}
