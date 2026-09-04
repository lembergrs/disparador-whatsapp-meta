<?php

namespace Models;

use Core\Database;
use PDO;

/** Somente SELECT. Não consulta Graph nem altera estados operacionais. */
class OnboardingReadModel
{
    private $db;

    public function __construct($db = null) { $this->db = $db ?: Database::getInstance(); }

    public function contas(int $clienteId): array
    {
        $sql = $this->db->prepare("SELECT m.MTA_ID, m.CLI_ID, m.MTA_Nome, m.MTA_NumeroTelefone,
            m.MTA_Ativo, m.MTA_Status, m.MTA_OnboardingType, m.MTA_PagamentoMetaStatus,
            m.MTA_QualityRating, m.MTA_OperationalStatus, m.MTA_UltimaVerificacao, m.MTA_MessagingLimit,
            CASE WHEN EXISTS (
                SELECT 1 FROM conversa_mensagens cm INNER JOIN conversas c ON c.CVS_ID=cm.CVS_ID
                WHERE c.CLI_ID=m.CLI_ID AND c.MTA_ID=m.MTA_ID AND cm.MSG_Direcao='enviada'
                AND (cm.MSG_Origem IS NULL OR cm.MSG_Origem='api')
                AND COALESCE(cm.MSG_MetaMessageId,'')<>''
                AND cm.MSG_Status IN ('delivered','entregue','read','lido')
            ) OR EXISTS (
                SELECT 1 FROM disparos d WHERE d.CLI_ID=m.CLI_ID AND d.MTA_ID=m.MTA_ID
                AND COALESCE(d.DSP_MessageId,'')<>'' AND d.DSP_Status IN ('delivered','entregue','read','lido')
            ) OR EXISTS (
                SELECT 1 FROM disparo_manual_itens i INNER JOIN disparo_manual_lotes l ON l.DML_ID=i.DML_ID
                WHERE l.CLI_ID=m.CLI_ID AND i.CLI_ID=m.CLI_ID AND l.MTA_ID=m.MTA_ID
                AND COALESCE(i.DMI_MessageId,'')<>'' AND i.DMI_Status IN ('delivered','entregue','read','lido')
            ) THEN 1 ELSE 0 END AS entregue
            FROM meta_contas m WHERE m.CLI_ID=? AND m.MTA_Ativo='S' ORDER BY m.MTA_ID DESC");
        $sql->execute([$clienteId]);
        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    public function templates(int $clienteId, int $contaId): array
    {
        $sql = $this->db->prepare("SELECT t.MTA_ID, t.TMP_MetaId, t.TMP_Status, t.TMP_Ativo
            FROM templates_meta t INNER JOIN meta_contas m ON m.MTA_ID=t.MTA_ID
            WHERE m.CLI_ID=? AND m.MTA_ID=? AND m.MTA_Ativo='S' AND t.TMP_Ativo='S'
            AND COALESCE(t.TMP_MetaId,'')<>''
            GROUP BY t.MTA_ID, t.TMP_MetaId, t.TMP_Status, t.TMP_Ativo");
        $sql->execute([$clienteId, $contaId]);
        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    public function envio(int $clienteId, int $contaId): ?array
    {
        $sql = $this->db->prepare("SELECT i.DMI_Status AS status, i.DMI_MessageId AS message_id,
            i.DMI_DataCadastro AS data, l.MTA_ID, 'manual' AS origem,
            (SELECT cm.MSG_Status FROM conversa_mensagens cm INNER JOIN conversas c ON c.CVS_ID=cm.CVS_ID
                WHERE c.CLI_ID=l.CLI_ID AND c.MTA_ID=l.MTA_ID AND cm.MSG_Direcao='enviada'
                AND (cm.MSG_Origem IS NULL OR cm.MSG_Origem='api')
                AND COALESCE(i.DMI_MessageId,'')<>'' AND cm.MSG_MetaMessageId=i.DMI_MessageId
                ORDER BY cm.MSG_ID DESC LIMIT 1) AS status_confirmado
            FROM disparo_manual_itens i INNER JOIN disparo_manual_lotes l ON l.DML_ID=i.DML_ID
            WHERE i.CLI_ID=? AND l.CLI_ID=? AND l.MTA_ID=?
            ORDER BY i.DMI_DataCadastro DESC, i.DMI_ID DESC LIMIT 1");
        $sql->execute([$clienteId, $clienteId, $contaId]);
        $manual = $sql->fetch(PDO::FETCH_ASSOC) ?: null;
        if($manual && !empty($manual['status_confirmado'])) $manual['status'] = $manual['status_confirmado'];

        $sql = $this->db->prepare("SELECT cm.MSG_Status AS status, cm.MSG_MetaMessageId AS message_id,
            cm.MSG_DataMensagem AS data, c.MTA_ID, 'conversa' AS origem
            FROM conversa_mensagens cm INNER JOIN conversas c ON c.CVS_ID=cm.CVS_ID
            WHERE c.CLI_ID=? AND c.MTA_ID=? AND cm.MSG_Direcao='enviada'
            AND (cm.MSG_Origem IS NULL OR cm.MSG_Origem='api')
            ORDER BY cm.MSG_DataMensagem DESC, cm.MSG_ID DESC LIMIT 1");
        $sql->execute([$clienteId, $contaId]);
        $conversa = $sql->fetch(PDO::FETCH_ASSOC) ?: null;
        if($manual && $conversa && !empty($manual['message_id']) && $manual['message_id'] === $conversa['message_id']){
            $manual['status'] = $conversa['status'];
            return $manual;
        }
        if($manual || $conversa) return !$manual ? $conversa : (!$conversa || $manual['data'] >= $conversa['data'] ? $manual : $conversa);

        // Legado sem histórico datado: uma aceitação antiga não supera uma falha conhecida.
        $sql = $this->db->prepare("SELECT DSP_Status AS status, DSP_MessageId AS message_id, MTA_ID, 'legado' AS origem
            FROM disparos WHERE CLI_ID=? AND MTA_ID=?
            ORDER BY CASE WHEN DSP_Status IN ('failed','erro','falha') THEN 0
                WHEN DSP_Status IN ('sent','enviado') THEN 1 ELSE 2 END LIMIT 1");
        $sql->execute([$clienteId, $contaId]);
        return $sql->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
