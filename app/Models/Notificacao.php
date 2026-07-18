<?php

namespace Models;

use Core\Database;

class Notificacao
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?: Database::getInstance();
    }

    public function criar(array $dados)
    {
        $sql = $this->db->prepare("INSERT INTO notificacoes (CLI_ID, NOT_Tipo, NOT_Canal, NOT_Assunto, NOT_Destino, NOT_Status, NOT_Dados) VALUES (:cli, :tipo, :canal, :assunto, :destino, 'pendente', :dados)");
        $sql->execute([
            ':cli' => !empty($dados['cliente_id']) ? (int) $dados['cliente_id'] : null,
            ':tipo' => (string) $dados['tipo'],
            ':canal' => (string) $dados['canal'],
            ':assunto' => $dados['assunto'] ?? null,
            ':destino' => $dados['destino'] ?? null,
            ':dados' => json_encode($dados['dados'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function finalizar($id, array $resultado)
    {
        $sucesso = !empty($resultado['sucesso']);
        $status = $sucesso ? 'enviada' : ($resultado['status'] ?? 'erro_temporario');
        if(!in_array($status, ['enviada','erro_temporario','erro_definitivo'], true)){
            $status = 'erro_temporario';
        }

        $sql = $this->db->prepare("UPDATE notificacoes SET NOT_Status = :status, NOT_Tentativas = NOT_Tentativas + 1, NOT_Erro = :erro, NOT_DataEnvio = CASE WHEN :ok = 1 THEN NOW() ELSE NOT_DataEnvio END WHERE NOT_ID = :id");
        return $sql->execute([
            ':status' => $status,
            ':erro' => $this->sanitizar($resultado['mensagem'] ?? $resultado['error_code'] ?? null),
            ':ok' => $sucesso ? 1 : 0,
            ':id' => (int) $id,
        ]);
    }

    private function sanitizar($mensagem)
    {
        $mensagem = trim((string) $mensagem);
        if($mensagem === '') return null;
        $mensagem = preg_replace('/[\r\n\t]+/', ' ', $mensagem);
        $mensagem = preg_replace('/(password|senha|token|secret|authorization)\s*[:=]\s*\S+/i', '$1=[removido]', $mensagem);
        return mb_substr($mensagem, 0, 255, 'UTF-8');
    }
}
