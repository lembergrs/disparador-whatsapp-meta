<?php

namespace Models;

use Core\Database;
use Core\Auth;
use PDO;

class Conversa
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    private function escopoUsuario($usuario)
    {
        $clienteId = (int) ($usuario['CLI_ID'] ?? ($usuario['cliente_id'] ?? 0));
        $metaIds = Auth::idsContasMetaPermitidas($usuario);

        return [
            'cliente_id' => $clienteId,
            'meta_ids' => $metaIds
        ];
    }

    private function aplicarEscopoUsuario(array &$where, array &$params, $usuario, $alias = 'c')
    {
        $escopo = $this->escopoUsuario($usuario);

        if($escopo['cliente_id'] <= 0 || empty($escopo['meta_ids'])){
            $where[] = '1 = 0';
            return;
        }

        $where[] = $alias . '.CLI_ID = ?';
        $params[] = $escopo['cliente_id'];

        $placeholders = implode(', ', array_fill(0, count($escopo['meta_ids']), '?'));
        $where[] = $alias . '.MTA_ID IN (' . $placeholders . ')';

        foreach($escopo['meta_ids'] as $metaId){
            $params[] = (int) $metaId;
        }
    }

    public function totalConversasNaoLidasPorUsuario($usuario)
    {
        $where = [
            "c.CVS_Ativo = 'S'",
            "c.CVS_NaoLida = 'S'"
        ];
        $params = [];

        $this->aplicarEscopoUsuario($where, $params, $usuario, 'c');

        $sql = $this->db->prepare("
            SELECT COUNT(DISTINCT c.CVS_ID)
            FROM conversas c
            WHERE " . implode(' AND ', $where) . "
        " );

        $sql->execute($params);

        return (int) $sql->fetchColumn();
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
                CVS_DataUltimaMensagem,
                CVS_DataAtualizacao
            )
            VALUES
            (
                ?, ?, ?, ?, NOW(), NOW()
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
                    CVS_QtdeNaoLidas = CVS_QtdeNaoLidas + 1,
                    CVS_DataAtualizacao = NOW()
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
                CVS_DataUltimaMensagem = NOW(),
                CVS_DataAtualizacao = NOW()
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
        $etiqueta = '',
        $usuario = null,
        $responsavel = '',
        $manterConversaId = null
    )
    {
        $where = [];
        $params = [];

        $where[] = "c.CVS_Ativo = 'S'";

        if($usuario){
            $this->aplicarEscopoUsuario($where, $params, $usuario, 'c');
        }else{
            $where[] = "c.CLI_ID = ?";
            $params[] = $clienteId;
        }

        $busca = trim($busca);
        $status = trim($status);
        $etiqueta = (int) $etiqueta;
        $responsavel = trim((string) $responsavel);
        $manterConversaId = (int) $manterConversaId;

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

            if($manterConversaId > 0){
                $where[] = "(c.CVS_NaoLida = 'S' OR c.CVS_ID = ?)";
                $params[] = $manterConversaId;
            }else{
                $where[] = "c.CVS_NaoLida = 'S'";
            }

        }elseif($status == 'L'){

            $where[] = "c.CVS_NaoLida = 'N'";
        }

        if(
            $usuario
            &&
            ($usuario['nivel'] ?? null) == 'cliente_usuario'
        ){
            $where[] = "c.CON_Responsavel_USU_ID = ?";
            $params[] = (int) $usuario['id'];
        }elseif($responsavel !== ''){

            if($responsavel == 'sem'){
                $where[] = "c.CON_Responsavel_USU_ID IS NULL";
            }elseif((int) $responsavel > 0){
                $where[] = "c.CON_Responsavel_USU_ID = ?";
                $params[] = (int) $responsavel;
            }
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
                r.USU_Nome AS ResponsavelNome,
                r.USU_ID AS ResponsavelId,

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

            LEFT JOIN usuarios r
                ON r.USU_ID = c.CON_Responsavel_USU_ID
                AND r.CLI_ID = c.CLI_ID

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

    public function buscar($conversaId, $clienteId, $usuario = null)
    {
        $where = [
            'c.CVS_ID = ?'
        ];
        $params = [
            $conversaId
        ];

        if($usuario){
            $this->aplicarEscopoUsuario($where, $params, $usuario, 'c');
        }else{
            $where[] = 'c.CLI_ID = ?';
            $params[] = $clienteId;
        }

        $sql = $this->db->prepare("
            SELECT
                c.*,
                r.USU_Nome AS ResponsavelNome,
                r.USU_ID AS ResponsavelId
            FROM conversas c
            LEFT JOIN usuarios r
                ON r.USU_ID = c.CON_Responsavel_USU_ID
                AND r.CLI_ID = c.CLI_ID
            WHERE " . implode(' AND ', $where) . "
            LIMIT 1
        ");

        $sql->execute($params);

        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function buscarAcessivel($conversaId, $clienteId, $usuario)
    {
        $conversa = $this->buscar($conversaId, $clienteId, $usuario);

        if(
            !$conversa
            ||
            (($usuario['nivel'] ?? null) == 'cliente_usuario'
            &&
            (int) ($conversa['CON_Responsavel_USU_ID'] ?? 0) != (int) ($usuario['id'] ?? 0))
        ){
            return false;
        }

        return $conversa;
    }

    public function usuarioPodeSerResponsavel($usuarioId, $clienteId)
    {
        $sql = $this->db->prepare("
            SELECT USU_ID, USU_Nome
            FROM usuarios
            WHERE USU_ID = ?
            AND CLI_ID = ?
            AND USU_Ativo = 'S'
            AND USU_Nivel IN ('cliente_admin', 'cliente_usuario')
            LIMIT 1
        ");

        $sql->execute([
            $usuarioId,
            $clienteId
        ]);

        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function atribuirResponsavel($conversaId, $clienteId, $usuarioId = null, $usuario = null)
    {
        $conversa = $this->buscar($conversaId, $clienteId, $usuario);

        if(!$conversa){
            return [
                'sucesso' => false,
                'erro' => 'Conversa não encontrada para este cliente.'
            ];
        }

        if($usuarioId === null){
            $sql = $this->db->prepare("
                UPDATE conversas
                SET
                    CON_Responsavel_USU_ID = NULL,
                    CVS_DataAtualizacao = NOW()
                WHERE CVS_ID = ?
                AND CLI_ID = ?
            ");

            $ok = $sql->execute([
                $conversaId,
                $clienteId
            ]);

            return [
                'sucesso' => $ok,
                'mensagem' => 'Responsável removido com sucesso.',
                'responsavel' => null
            ];
        }

        $responsavel = $this->usuarioPodeSerResponsavel(
            $usuarioId,
            $clienteId
        );

        if(!$responsavel){
            return [
                'sucesso' => false,
                'erro' => 'Responsável inválido, inativo ou de outro cliente.'
            ];
        }

        $sql = $this->db->prepare("
            UPDATE conversas
            SET
                CON_Responsavel_USU_ID = ?,
                CVS_DataAtualizacao = NOW()
            WHERE CVS_ID = ?
            AND CLI_ID = ?
        ");

        $ok = $sql->execute([
            $usuarioId,
            $conversaId,
            $clienteId
        ]);

        return [
            'sucesso' => $ok,
            'mensagem' => 'Conversa atribuída com sucesso.',
            'responsavel' => $responsavel
        ];
    }

    public function marcarComoLida($conversaId, $clienteId, $usuario = null)
    {
        $where = [
            'CVS_ID = ?'
        ];
        $params = [
            $conversaId
        ];

        if($usuario){
            $escopo = $this->escopoUsuario($usuario);

            if($escopo['cliente_id'] <= 0 || empty($escopo['meta_ids'])){
                return false;
            }

            $where[] = 'CLI_ID = ?';
            $params[] = $escopo['cliente_id'];
            $where[] = 'MTA_ID IN (' . implode(', ', array_fill(0, count($escopo['meta_ids']), '?')) . ')';

            foreach($escopo['meta_ids'] as $metaId){
                $params[] = (int) $metaId;
            }
        }else{
            $where[] = 'CLI_ID = ?';
            $params[] = $clienteId;
        }

        $sql = $this->db->prepare("
            UPDATE conversas
            SET
                CVS_NaoLida = 'N',
                CVS_QtdeNaoLidas = 0,
                CVS_DataAtualizacao = NOW()
            WHERE " . implode(' AND ', $where) . "
        ");

        return $sql->execute($params);
    }

    public function marcarComoNaoLida($conversaId, $clienteId, $usuario = null)
    {
        $where = [
            'CVS_ID = ?'
        ];
        $params = [
            $conversaId
        ];

        if($usuario){
            $escopo = $this->escopoUsuario($usuario);

            if($escopo['cliente_id'] <= 0 || empty($escopo['meta_ids'])){
                return false;
            }

            $where[] = 'CLI_ID = ?';
            $params[] = $escopo['cliente_id'];
            $where[] = 'MTA_ID IN (' . implode(', ', array_fill(0, count($escopo['meta_ids']), '?')) . ')';

            foreach($escopo['meta_ids'] as $metaId){
                $params[] = (int) $metaId;
            }
        }else{
            $where[] = 'CLI_ID = ?';
            $params[] = $clienteId;
        }

        $sql = $this->db->prepare("
            UPDATE conversas
            SET
                CVS_NaoLida = 'S',
                CVS_QtdeNaoLidas = CASE
                    WHEN CVS_QtdeNaoLidas <= 0 THEN 1
                    ELSE CVS_QtdeNaoLidas
                END,
                CVS_DataAtualizacao = NOW()
            WHERE " . implode(' AND ', $where) . "
        ");

        return $sql->execute($params);
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

    public function ultimaAtualizacaoCliente($clienteId, $usuario = null)
    {
        $where = [
            "CVS_Ativo = 'S'"
        ];
        $params = [];

        if($usuario){
            $this->aplicarEscopoUsuario($where, $params, $usuario, 'conversas');
        }else{
            $where[] = 'CLI_ID = ?';
            $params[] = $clienteId;
        }

        $sql = $this->db->prepare("
            SELECT MAX(COALESCE(CVS_DataAtualizacao, CVS_DataUltimaMensagem)) AS ultima
            FROM conversas
            WHERE " . implode(' AND ', $where) . "
        ");

        $sql->execute($params);

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

        $this->tocarAtualizacao($conversaId, $clienteId);

        return true;
    }

    private function tocarAtualizacao($conversaId, $clienteId)
    {
        $sql = $this->db->prepare("
            UPDATE conversas
            SET CVS_DataAtualizacao = NOW()
            WHERE CVS_ID = ?
            AND CLI_ID = ?
        ");

        return $sql->execute([
            $conversaId,
            $clienteId
        ]);
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
