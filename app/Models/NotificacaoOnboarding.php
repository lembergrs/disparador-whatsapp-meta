<?php

namespace Models;

use Core\Database;
use PDO;
use Services\EventoNotificacao;

class NotificacaoOnboarding
{
    private $db;
    public function __construct($db = null){ $this->db = $db ?: Database::getInstance(); }

    public function listarPendentesConexao($ultimoId = 0, $limite = 100)
    {
        $limite = max(1, min(500, (int)$limite));
        $sql = $this->db->prepare("SELECT c.CLI_ID, c.CLI_Nome, c.CLI_NomeFantasia, c.CLI_Email, c.CLI_Telefone, c.CLI_DataCadastro, c.CLI_DataLiberacao, c.CLI_Ativo FROM clientes c WHERE c.CLI_ID > ? AND c.CLI_Ativo='S' AND c.CLI_DataCadastro <= DATE_SUB(NOW(), INTERVAL 24 HOUR) AND (c.CLI_DataLiberacao IS NULL OR c.CLI_DataLiberacao='') AND NOT EXISTS (SELECT 1 FROM meta_contas m WHERE m.CLI_ID=c.CLI_ID AND m.MTA_Ativo='S' AND COALESCE(m.MTA_WabaId,'')<>'' AND COALESCE(m.MTA_PhoneNumberId,'')<>'') AND NOT EXISTS (SELECT 1 FROM notificacoes n WHERE n.CLI_ID=c.CLI_ID AND n.NOT_Tipo=? AND n.NOT_Canal='whatsapp' AND n.NOT_Status IN ('enviada','processando','erro_definitivo')) ORDER BY c.CLI_ID ASC LIMIT {$limite}");
        $sql->execute([(int)$ultimoId, EventoNotificacao::CADASTRO_PENDENTE_CONEXAO]);
        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    public function continuaSemConexao($clienteId)
    {
        $sql = $this->db->prepare("SELECT 1 FROM clientes c WHERE c.CLI_ID=? AND c.CLI_Ativo='S' AND c.CLI_DataCadastro <= DATE_SUB(NOW(), INTERVAL 24 HOUR) AND (c.CLI_DataLiberacao IS NULL OR c.CLI_DataLiberacao='') AND NOT EXISTS (SELECT 1 FROM meta_contas m WHERE m.CLI_ID=c.CLI_ID AND m.MTA_Ativo='S' AND COALESCE(m.MTA_WabaId,'')<>'' AND COALESCE(m.MTA_PhoneNumberId,'')<>'') LIMIT 1");
        $sql->execute([(int)$clienteId]); return (bool)$sql->fetchColumn();
    }

    public static function elegivelPorDados(array $cliente, $agora = null)
    {
        $agora = $agora ?: new \DateTimeImmutable();
        $cadastro = !empty($cliente['CLI_DataCadastro']) ? new \DateTimeImmutable($cliente['CLI_DataCadastro']) : null;
        return ($cliente['CLI_Ativo'] ?? '') === 'S' && $cadastro && $cadastro <= $agora->modify('-24 hours') && empty($cliente['CLI_DataLiberacao']) && empty($cliente['meta_conectada']);
    }
}
