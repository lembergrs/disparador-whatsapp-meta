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
        if(!self::ehFalhaElegivel($erro)) return false;

        $messageId = trim((string)$messageId);
        if($messageId === '') return false;

        $this->db->beginTransaction();
        try{
            $origem = $this->localizarOrigem($messageId);
            if(!$origem || $origem['ano_mes'] !== date('Ym')){
                $this->db->rollBack();
                return false;
            }

            $comprovante = $this->db->prepare("SELECT CEV_DataConsumo FROM consumo_envios_meta WHERE CEV_MessageId = ? AND CLI_ID = ? AND CEV_AnoMes = ?");
            $comprovante->execute([$messageId, $origem['CLI_ID'], $origem['ano_mes']]);
            $dataConsumo = $comprovante->fetchColumn();
            if (!$dataConsumo) {
                $this->db->rollBack();
                return false;
            }
            $consumo = $this->db->prepare("SELECT CMS_ID, CMS_Mensagens, CMS_MensagensAvaliacao, CMS_InicioPlanoPago FROM consumo_mensal WHERE CLI_ID = ? AND CMS_AnoMes = ? FOR UPDATE");
            $consumo->execute([$origem['CLI_ID'], $origem['ano_mes']]);
            $registro = $consumo->fetch(PDO::FETCH_ASSOC);
            $avaliacao = $registro && !empty($registro['CMS_InicioPlanoPago']) && ($dataConsumo ?: $origem['data_envio']) < $registro['CMS_InicioPlanoPago'];
            $campo = $avaliacao ? 'CMS_MensagensAvaliacao' : 'CMS_Mensagens';
            if (!$registro || (int)$registro[$campo] <= 0) {
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
                SET {$campo} = GREATEST({$campo} - 1, 0),
                    CMS_AtualizadoEm = NOW()
                WHERE CLI_ID = ?
                  AND CMS_AnoMes = ?
            ");
            $update->execute([$origem['CLI_ID'], $origem['ano_mes']]);

            if (!$avaliacao) $this->recalcularExcedente($origem['CLI_ID'], $origem['ano_mes']);
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
                   DMI_DataEnvio AS data_envio,
                   'disparo_manual' AS origem,
                   DMI_ID AS origem_id
            FROM disparo_manual_itens
            WHERE DMI_MessageId = ? AND DMI_DataEnvio IS NOT NULL AND DMI_Status = 'failed'
            LIMIT 1
        ");
        $sql->execute([$messageId]);
        $row = $sql->fetch(PDO::FETCH_ASSOC);
        if($row) return $row;

        $sql = $this->db->prepare("
            SELECT c.CLI_ID,
                   DATE_FORMAT(COALESCE(f.FIL_DataEnvio, f.FIL_DataCadastro), '%Y%m') AS ano_mes,
                   f.FIL_DataEnvio AS data_envio,
                   'campanha' AS origem,
                   f.FIL_ID AS origem_id
            FROM fila_envio f
            INNER JOIN campanhas c ON c.CAM_ID = f.CAM_ID
            WHERE f.FIL_MessageId = ? AND f.FIL_DataEnvio IS NOT NULL AND f.FIL_Status = 'failed'
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

    public static function ehFalhaElegivel(array $erro)
    {
        if(in_array((string)($erro['codigo'] ?? ''), ['131042','131026','131047','131048','131049','131050','131056','132000','132001','132015','132016'], true)) return true;

        $mensagem = strtolower((string)($erro['mensagem'] ?? ''));
        return strpos($mensagem, 'healthy ecosystem engagement') !== false
            || strpos($mensagem, 'unsettled payment') !== false
            || strpos($mensagem, 'errors related to your payment method') !== false;
    }
}
