<?php

namespace Services;

use Core\Database;
use PDO;

class MetaBillingConsumptionReversalService
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?: Database::getInstance();
    }

    public function processar($messageId, array $erro)
    {
        if(!$this->ehFalhaPagamentoMeta($erro)) return false;

        $messageId = trim((string)$messageId);
        if($messageId === '') return false;

        $this->db->beginTransaction();
        try{
            $origem = $this->localizarOrigem($messageId);
            if(!$origem){
                $this->db->rollBack();
                return false;
            }

            $insert = $this->db->prepare("
                INSERT IGNORE INTO consumo_estornos_meta
                    (CEM_MessageId, CLI_ID, CEM_AnoMes, CEM_Origem, CEM_OrigemId, CEM_CodigoErro, CEM_DataCadastro)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $insert->execute([
                $messageId,
                $origem['CLI_ID'],
                $origem['ano_mes'],
                $origem['origem'],
                $origem['origem_id'],
                (string)($erro['codigo'] ?? '')
            ]);

            if($insert->rowCount() !== 1){
                $this->db->commit();
                return false;
            }

            $update = $this->db->prepare("
                UPDATE consumo_mensal
                SET CMS_Mensagens = GREATEST(CMS_Mensagens - 1, 0),
                    CMS_AtualizadoEm = NOW()
                WHERE CLI_ID = ?
                  AND CMS_AnoMes = ?
            ");
            $update->execute([$origem['CLI_ID'], $origem['ano_mes']]);

            $this->recalcularExcedente($origem['CLI_ID'], $origem['ano_mes']);
            $this->db->commit();
            return true;
        }catch(\Throwable $e){
            if($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    private function localizarOrigem($messageId)
    {
        $sql = $this->db->prepare("
            SELECT CLI_ID,
                   DATE_FORMAT(COALESCE(DMI_DataEnvio, DMI_DataCadastro), '%Y%m') AS ano_mes,
                   'disparo_manual' AS origem,
                   DMI_ID AS origem_id
            FROM disparo_manual_itens
            WHERE DMI_MessageId = ?
            LIMIT 1
        ");
        $sql->execute([$messageId]);
        $row = $sql->fetch(PDO::FETCH_ASSOC);
        if($row) return $row;

        $sql = $this->db->prepare("
            SELECT c.CLI_ID,
                   DATE_FORMAT(COALESCE(f.FIL_DataEnvio, f.FIL_DataCadastro), '%Y%m') AS ano_mes,
                   'campanha' AS origem,
                   f.FIL_ID AS origem_id
            FROM fila_envio f
            INNER JOIN campanhas c ON c.CAM_ID = f.CAM_ID
            WHERE f.FIL_MessageId = ?
            LIMIT 1
        ");
        $sql->execute([$messageId]);
        return $sql->fetch(PDO::FETCH_ASSOC) ?: false;
    }

    private function recalcularExcedente($cliId, $anoMes)
    {
        $sql = $this->db->prepare("
            SELECT CMS_Mensagens, CMS_LimiteMensagens
            FROM consumo_mensal
            WHERE CLI_ID = ? AND CMS_AnoMes = ?
            LIMIT 1
        ");
        $sql->execute([$cliId, $anoMes]);
        $consumo = $sql->fetch(PDO::FETCH_ASSOC);
        if(!$consumo) return;

        $excedentes = max(0, (int)$consumo['CMS_Mensagens'] - (int)$consumo['CMS_LimiteMensagens']);

        $sql = $this->db->prepare("
            UPDATE excedentes_mensais
            SET EXC_Mensagens = ?,
                EXC_ValorTotal = ? * EXC_ValorUnitario
            WHERE CLI_ID = ? AND EXC_AnoMes = ?
        ");
        $sql->execute([$excedentes, $excedentes, $cliId, $anoMes]);
    }

    private function ehFalhaPagamentoMeta(array $erro)
    {
        if((string)($erro['codigo'] ?? '') === '131042') return true;

        $mensagem = strtolower((string)($erro['mensagem'] ?? ''));
        return strpos($mensagem, 'unsettled payment') !== false
            || strpos($mensagem, 'errors related to your payment method') !== false;
    }
}
