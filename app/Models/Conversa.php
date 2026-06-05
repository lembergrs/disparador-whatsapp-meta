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

    public function listarConversas($clienteId)
    {
        $sql = $this->db->prepare("

            SELECT *

            FROM conversas

            WHERE CLI_ID = ?
            AND CVS_Ativo = 'S'

            ORDER BY CVS_DataUltimaMensagem DESC

            LIMIT 100

        ");

        $sql->execute([$clienteId]);

        return $sql->fetchAll(PDO::FETCH_ASSOC);
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
            SET CVS_NaoLida = 'S'
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

}