<?php

namespace Models;

use Core\Database;
use PDO;
use PDOException;

class NotificacaoTransacional
{
    public const STATUS_PENDENTE = 'pendente';
    public const STATUS_PROCESSANDO = 'processando';
    public const STATUS_ENVIADO = 'enviado';
    public const STATUS_ERRO_TEMPORARIO = 'erro_temporario';
    public const STATUS_ERRO_DEFINITIVO = 'erro_definitivo';

    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?: Database::getInstance();
    }

    public function criarPendenteIdempotente(array $dados)
    {
        $sql = $this->db->prepare("\n            INSERT INTO notificacoes_transacionais (\n                CLI_ID, USU_ID, COB_ID, NOT_Tipo, NOT_Canal, NOT_Destinatario,\n                NOT_Assunto, NOT_Status, NOT_Tentativas, NOT_ChaveIdempotencia\n            ) VALUES (\n                :cliente_id, :usuario_id, :cobranca_id, :tipo, :canal, :destinatario,\n                :assunto, :status, 0, :chave\n            )\n        ");

        try{
            $sql->execute([
                ':cliente_id' => (int) $dados['cliente_id'],
                ':usuario_id' => !empty($dados['usuario_id']) ? (int) $dados['usuario_id'] : null,
                ':cobranca_id' => !empty($dados['cobranca_id']) ? (int) $dados['cobranca_id'] : null,
                ':tipo' => (string) $dados['tipo'],
                ':canal' => (string) $dados['canal'],
                ':destinatario' => (string) $dados['destinatario'],
                ':assunto' => (string) $dados['assunto'],
                ':status' => self::STATUS_PENDENTE,
                ':chave' => (string) $dados['chave_idempotencia']
            ]);
        }catch(PDOException $e){
            if(!$this->erroDuplicidade($e)){
                throw $e;
            }
        }

        return $this->buscarPorChave((string) $dados['chave_idempotencia']);
    }

    public function buscarPorChave($chave)
    {
        $sql = $this->db->prepare("SELECT * FROM notificacoes_transacionais WHERE NOT_ChaveIdempotencia = ? LIMIT 1");
        $sql->execute([(string) $chave]);

        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function marcarProcessando($id)
    {
        $sql = $this->db->prepare("\n            UPDATE notificacoes_transacionais\n            SET NOT_Status = :status,\n                NOT_Tentativas = NOT_Tentativas + 1,\n                NOT_UltimoErro = NULL\n            WHERE NOT_ID = :id\n            AND NOT_Status IN (:pendente, :erro_temporario)\n        ");

        $sql->execute([
            ':status' => self::STATUS_PROCESSANDO,
            ':id' => (int) $id,
            ':pendente' => self::STATUS_PENDENTE,
            ':erro_temporario' => self::STATUS_ERRO_TEMPORARIO
        ]);

        return $sql->rowCount() === 1;
    }

    public function marcarResultado($id, array $resultado)
    {
        $status = !empty($resultado['sucesso'])
            ? self::STATUS_ENVIADO
            : ($resultado['status'] ?? self::STATUS_ERRO_TEMPORARIO);

        if(!in_array($status, [self::STATUS_ENVIADO, self::STATUS_ERRO_TEMPORARIO, self::STATUS_ERRO_DEFINITIVO], true)){
            $status = self::STATUS_ERRO_TEMPORARIO;
        }

        $sql = $this->db->prepare("\n            UPDATE notificacoes_transacionais\n            SET NOT_Status = :status,\n                NOT_UltimoErro = :erro,\n                NOT_DataEnvio = CASE WHEN :enviado = 1 THEN NOW() ELSE NOT_DataEnvio END\n            WHERE NOT_ID = :id\n        ");

        return $sql->execute([
            ':status' => $status,
            ':erro' => $this->sanitizarErro($resultado['mensagem'] ?? $resultado['error_code'] ?? null),
            ':enviado' => !empty($resultado['sucesso']) ? 1 : 0,
            ':id' => (int) $id
        ]);
    }

    private function erroDuplicidade(PDOException $e)
    {
        $info = $e->errorInfo ?? [];
        return ($info[0] ?? null) === '23000' || (int) ($info[1] ?? 0) === 1062 || strpos($e->getMessage(), 'Duplicate') !== false;
    }

    private function sanitizarErro($mensagem)
    {
        $mensagem = trim((string) $mensagem);
        if($mensagem === ''){
            return null;
        }

        $mensagem = preg_replace('/[\r\n\t]+/', ' ', $mensagem);
        $mensagem = preg_replace('/(password|senha|token|secret|authorization)\s*[:=]\s*\S+/i', '$1=[removido]', $mensagem);

        return mb_substr($mensagem, 0, 255, 'UTF-8');
    }
}
