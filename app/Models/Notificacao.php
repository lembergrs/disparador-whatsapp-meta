<?php

namespace Models;

use Core\Database;
use PDO;
use Services\MensagemStatusService;

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

    public function reservarIdempotente(array $dados)
    {
        $sql = $this->db->prepare("INSERT INTO notificacoes (CLI_ID, NOT_Tipo, NOT_Canal, NOT_Assunto, NOT_Destino, NOT_Status, NOT_Dados, NOT_ChaveIdempotencia, NOT_Template) VALUES (:cli, :tipo, :canal, :assunto, :destino, 'pendente', :dados, :chave, :template) ON DUPLICATE KEY UPDATE NOT_ID = LAST_INSERT_ID(NOT_ID)");
        $sql->execute([
            ':cli'=>(int)$dados['cliente_id'], ':tipo'=>$dados['tipo'], ':canal'=>$dados['canal'],
            ':assunto'=>$dados['assunto'] ?? null, ':destino'=>$dados['destino'] ?? null,
            ':dados'=>json_encode($dados['dados'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':chave'=>$dados['chave'], ':template'=>$dados['template'] ?? null,
        ]);
        $id = (int)$this->db->lastInsertId();
        $consulta = $this->db->prepare('SELECT * FROM notificacoes WHERE NOT_ID = ? LIMIT 1');
        $consulta->execute([$id]); return $consulta->fetch(PDO::FETCH_ASSOC);
    }

    public function marcarProcessando($id, $maxTentativas = 5)
    {
        $sql = $this->db->prepare("UPDATE notificacoes SET NOT_Status='processando', NOT_Tentativas=NOT_Tentativas+1, NOT_Erro=NULL WHERE NOT_ID=? AND NOT_Status IN ('pendente','erro_temporario') AND NOT_Tentativas < ?");
        $sql->execute([(int)$id, max(1, (int)$maxTentativas)]); return $sql->rowCount() === 1;
    }

    public function finalizarWhatsApp($id, array $resultado)
    {
        $sucesso = !empty($resultado['sucesso']);
        $status = $sucesso ? 'enviada' : ($resultado['status'] ?? 'erro_temporario');
        if(!in_array($status, ['enviada','erro_temporario','erro_definitivo'], true)) $status = 'erro_temporario';
        $sql = $this->db->prepare("UPDATE notificacoes SET NOT_Status=:status, NOT_Erro=:erro, NOT_CodigoErro=:codigo, NOT_ProviderMessageId=:message_id, NOT_DataEnvio=CASE WHEN :ok=1 THEN NOW() ELSE NOT_DataEnvio END WHERE NOT_ID=:id AND NOT_Status='processando'");
        return $sql->execute([':status'=>$status, ':erro'=>$this->sanitizar($resultado['mensagem'] ?? null), ':codigo'=>$this->sanitizar($resultado['error_code'] ?? null), ':message_id'=>$this->sanitizar($resultado['message_id'] ?? null), ':ok'=>$sucesso ? 1 : 0, ':id'=>(int)$id]);
    }

    public function atualizarStatusWhatsAppPorMessageId($messageId, $status, $dataEvento = null, array $erro = [])
    {
        $status = MensagemStatusService::normalizar($status);
        $mapa = ['sent'=>'enviada', 'delivered'=>'entregue', 'read'=>'lida', 'failed'=>'erro_definitivo'];
        if(!isset($mapa[$status]) || trim((string)$messageId) === '') return false;

        $novoStatus = $mapa[$status];
        $permitidos = [
            'sent'=>['pendente','processando'],
            'delivered'=>['pendente','processando','enviada','enviado'],
            'read'=>['pendente','processando','enviada','enviado','entregue'],
            'failed'=>['pendente','processando','enviada','enviado','erro_temporario'],
        ][$status];
        $placeholders = implode(',', array_fill(0, count($permitidos), '?'));
        $campoData = ['sent'=>'NOT_DataEnvio', 'delivered'=>'NOT_DataEntrega', 'read'=>'NOT_DataLeitura', 'failed'=>'NOT_DataErro'][$status];
        $dataEvento = $dataEvento && strtotime($dataEvento) ? date('Y-m-d H:i:s', strtotime($dataEvento)) : date('Y-m-d H:i:s');
        $codigo = preg_replace('/[^A-Za-z0-9_.-]/', '', (string)($erro['codigo'] ?? '')) ?: null;
        $mensagem = MensagemStatusService::sanitizarErro($erro['mensagem'] ?? null) ?: null;

        $sql = $this->db->prepare("UPDATE notificacoes SET NOT_Status=?, {$campoData}=COALESCE({$campoData}, ?), NOT_CodigoErro=CASE WHEN ?='failed' THEN ? ELSE NOT_CodigoErro END, NOT_Erro=CASE WHEN ?='failed' THEN ? ELSE NOT_Erro END WHERE NOT_Canal='whatsapp' AND NOT_ProviderMessageId=? AND NOT_Status IN ({$placeholders})");
        $params = [$novoStatus, $dataEvento, $status, $codigo, $status, $mensagem, $messageId];
        $sql->execute(array_merge($params, $permitidos));
        return $sql->rowCount() > 0;
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


    public function listarAdmin(array $filtros = [], $limite = 25, $offset = 0)
    {
        [$where, $params] = $this->whereAdmin($filtros);
        $limite = max(1, min(100, (int) $limite));
        $offset = max(0, (int) $offset);

        $sql = $this->db->prepare("
            SELECT n.*, c.CLI_Nome, c.CLI_NomeFantasia, c.CLI_RazaoSocial, c.CLI_Email
            FROM notificacoes n
            LEFT JOIN clientes c ON c.CLI_ID = n.CLI_ID
            {$where}
            ORDER BY n.NOT_CriadoEm DESC, n.NOT_ID DESC
            LIMIT {$limite} OFFSET {$offset}
        ");
        $sql->execute($params);
        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contarAdmin(array $filtros = [])
    {
        [$where, $params] = $this->whereAdmin($filtros);
        $sql = $this->db->prepare("SELECT COUNT(*) FROM notificacoes n LEFT JOIN clientes c ON c.CLI_ID = n.CLI_ID {$where}");
        $sql->execute($params);
        return (int) $sql->fetchColumn();
    }

    public function resumoAdmin()
    {
        $sql = $this->db->query("
            SELECT
                COUNT(*) total,
                SUM(CASE WHEN NOT_Status IN ('enviada','enviado') THEN 1 ELSE 0 END) enviadas,
                SUM(CASE WHEN NOT_Status IN ('pendente','processando') THEN 1 ELSE 0 END) pendentes,
                SUM(CASE WHEN NOT_Status LIKE 'erro%' THEN 1 ELSE 0 END) erros,
                SUM(CASE WHEN NOT_Status IN ('enviada','enviado') AND DATE(NOT_DataEnvio) = CURDATE() THEN 1 ELSE 0 END) enviadas_hoje
            FROM notificacoes
        ");
        return $sql->fetch(PDO::FETCH_ASSOC) ?: ['total'=>0,'enviadas'=>0,'pendentes'=>0,'erros'=>0,'enviadas_hoje'=>0];
    }

    public function buscarAdmin($id)
    {
        $sql = $this->db->prepare("
            SELECT n.*, c.CLI_Nome, c.CLI_NomeFantasia, c.CLI_RazaoSocial, c.CLI_Email
            FROM notificacoes n
            LEFT JOIN clientes c ON c.CLI_ID = n.CLI_ID
            WHERE n.NOT_ID = ?
            LIMIT 1
        ");
        $sql->execute([(int) $id]);
        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function marcarReenvioProcessando($id)
    {
        $sql = $this->db->prepare("
            UPDATE notificacoes
            SET NOT_Status = 'processando', NOT_Tentativas = NOT_Tentativas + 1, NOT_Erro = NULL
            WHERE NOT_ID = ?
            AND NOT_Canal = 'email'
            AND NOT_Status IN ('pendente','erro_temporario','erro_definitivo')
        ");
        $sql->execute([(int) $id]);
        return $sql->rowCount() === 1;
    }

    public function finalizarReenvio($id, array $resultado)
    {
        $sucesso = !empty($resultado['sucesso']);
        $status = $sucesso ? 'enviada' : ($resultado['status'] ?? 'erro_temporario');
        if(!in_array($status, ['enviada','erro_temporario','erro_definitivo'], true)){
            $status = 'erro_temporario';
        }
        $sql = $this->db->prepare("UPDATE notificacoes SET NOT_Status = :status, NOT_Erro = :erro, NOT_DataEnvio = CASE WHEN :ok = 1 THEN NOW() ELSE NOT_DataEnvio END WHERE NOT_ID = :id");
        return $sql->execute([':status'=>$status, ':erro'=>$this->sanitizar($resultado['mensagem'] ?? $resultado['error_code'] ?? null), ':ok'=>$sucesso ? 1 : 0, ':id'=>(int)$id]);
    }

    private function whereAdmin(array $filtros)
    {
        $where = [];
        $params = [];
        if(!empty($filtros['cliente_id'])){ $where[] = 'n.CLI_ID = ?'; $params[] = (int) $filtros['cliente_id']; }
        if(!empty($filtros['evento'])){ $where[] = 'n.NOT_Tipo = ?'; $params[] = (string) $filtros['evento']; }
        if(!empty($filtros['canal'])){ $where[] = 'n.NOT_Canal = ?'; $params[] = (string) $filtros['canal']; }
        if(!empty($filtros['status'])){
            if($filtros['status'] === 'erro'){ $where[] = "n.NOT_Status LIKE 'erro%'"; }
            else { $where[] = 'n.NOT_Status = ?'; $params[] = (string) $filtros['status']; }
        }
        if(!empty($filtros['data_inicial'])){ $where[] = 'DATE(n.NOT_CriadoEm) >= ?'; $params[] = (string) $filtros['data_inicial']; }
        if(!empty($filtros['data_final'])){ $where[] = 'DATE(n.NOT_CriadoEm) <= ?'; $params[] = (string) $filtros['data_final']; }
        if(!empty($filtros['destino'])){ $where[] = 'n.NOT_Destino LIKE ?'; $params[] = '%' . $filtros['destino'] . '%'; }
        if(!empty($filtros['q'])){
            $where[] = '(n.NOT_Assunto LIKE ? OR n.NOT_Destino LIKE ? OR n.NOT_Erro LIKE ? OR c.CLI_Nome LIKE ? OR c.CLI_NomeFantasia LIKE ?)';
            for($i = 0; $i < 5; $i++) $params[] = '%' . $filtros['q'] . '%';
        }
        return [$where ? 'WHERE ' . implode(' AND ', $where) : '', $params];
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
