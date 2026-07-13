<?php

namespace Models;

use Core\Database;
use PDO;

class MetaEmbeddedSignupAttempt
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    private function stateHash($state)
    {
        return hash('sha256', (string) $state);
    }

    public function criar($state, $clienteId, $requestId, $ttlSeconds = 1800)
    {
        $sql = $this->db->prepare("\n            INSERT INTO meta_embedded_signup_attempts\n                (state_hash, cliente_id, request_id, created_at, expires_at)\n            VALUES\n                (?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? SECOND))\n        ");

        return $sql->execute([
            $this->stateHash($state),
            (int) $clienteId,
            $requestId,
            (int) $ttlSeconds
        ]);
    }

    public function buscarValida($state, $clienteId)
    {
        $sql = $this->db->prepare("\n            SELECT *\n            FROM meta_embedded_signup_attempts\n            WHERE state_hash = ?\n            AND cliente_id = ?\n            AND expires_at > NOW()\n            LIMIT 1\n        ");

        $sql->execute([
            $this->stateHash($state),
            (int) $clienteId
        ]);

        $attempt = $sql->fetch(PDO::FETCH_ASSOC);
        if(!$attempt){
            return null;
        }

        if(!empty($attempt['finish_json'])){
            $decoded = json_decode($attempt['finish_json'], true);
            $attempt['finish'] = is_array($decoded) ? $decoded : null;
        }else{
            $attempt['finish'] = null;
        }

        return $attempt;
    }

    public function salvarFinish($state, $clienteId, array $finish)
    {
        $sql = $this->db->prepare("\n            UPDATE meta_embedded_signup_attempts\n            SET finish_json = ?,\n                updated_at = NOW()\n            WHERE state_hash = ?\n            AND cliente_id = ?\n            AND used_at IS NULL\n            AND finish_json IS NULL\n            AND expires_at > NOW()\n        ");

        $sql->execute([
            json_encode($finish, JSON_UNESCAPED_UNICODE),
            $this->stateHash($state),
            (int) $clienteId
        ]);

        if($sql->rowCount() > 0){
            return true;
        }

        $attempt = $this->buscarValida($state, $clienteId);
        return $attempt && empty($attempt['used_at']) && !empty($attempt['finish']);
    }

    public function consumir($state, $clienteId)
    {
        $sql = $this->db->prepare("\n            UPDATE meta_embedded_signup_attempts\n            SET used_at = NOW(),\n                updated_at = NOW()\n            WHERE state_hash = ?\n            AND cliente_id = ?\n            AND used_at IS NULL\n            AND expires_at > NOW()\n        ");

        $sql->execute([
            $this->stateHash($state),
            (int) $clienteId
        ]);

        if($sql->rowCount() !== 1){
            return null;
        }

        return $this->buscarPorHashMesmoConsumida($state, $clienteId);
    }

    private function buscarPorHashMesmoConsumida($state, $clienteId)
    {
        $sql = $this->db->prepare("\n            SELECT *\n            FROM meta_embedded_signup_attempts\n            WHERE state_hash = ?\n            AND cliente_id = ?\n            LIMIT 1\n        ");

        $sql->execute([
            $this->stateHash($state),
            (int) $clienteId
        ]);

        $attempt = $sql->fetch(PDO::FETCH_ASSOC);
        if(!$attempt){
            return null;
        }

        if(!empty($attempt['finish_json'])){
            $decoded = json_decode($attempt['finish_json'], true);
            $attempt['finish'] = is_array($decoded) ? $decoded : null;
        }else{
            $attempt['finish'] = null;
        }

        return $attempt;
    }
}
