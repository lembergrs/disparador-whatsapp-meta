<?php

namespace Services;

use Core\Database;
use Models\Conversa;
use PDO;

class MetaCoexistenceHistoryQueueService
{
    private $db;
    private $ingestion;

    public function __construct($db = null, $ingestion = null)
    {
        $this->db = $db ?: Database::getInstance();
        $this->ingestion = $ingestion ?: new MetaWebhookMessageIngestionService(new Conversa());
    }

    public function enfileirar(array $value, array $metaConta)
    {
        $total = 0;
        foreach(($value['history'] ?? []) as $chunk){
            $metadata = $chunk['metadata'] ?? [];
            $requestId = trim((string) ($metadata['request_id'] ?? ($value['request_id'] ?? '')));
            $phase = trim((string) ($metadata['phase'] ?? ''));
            $order = filter_var($metadata['chunk_order'] ?? null, FILTER_VALIDATE_INT);
            $progress = filter_var($metadata['progress'] ?? null, FILTER_VALIDATE_INT);
            $payload = json_encode(['metadata'=>$value['metadata'] ?? [], 'history'=>[$chunk]], JSON_UNESCAPED_UNICODE);
            if($payload === false) continue;

            $dedupeKey = hash('sha256', implode('|', [$requestId,$phase,$order === false ? '' : $order,hash('sha256',$payload)]));
            $sql = $this->db->prepare("INSERT IGNORE INTO meta_coexistence_history_jobs (MTA_ID,MCH_DedupeKey,MCH_RequestId,MCH_Phase,MCH_ChunkOrder,MCH_Progress,MCH_Payload) VALUES (?,?,?,?,?,?,?)");
            $sql->execute([(int)$metaConta['MTA_ID'], $dedupeKey, $requestId ?: null, $phase ?: null, $order === false ? null : $order, $progress === false ? null : max(0,min(100,$progress)), $payload]);
            $total += $sql->rowCount();
            $this->atualizarProgresso((int)$metaConta['MTA_ID'], $requestId, $phase, $order, $progress, $chunk['errors'] ?? []);
        }
        return ['enfileiradas'=>$total];
    }

    public function processarPendentes($limite, $workerId)
    {
        $resumo = ['recuperados'=>0,'reservados'=>0,'processados'=>0,'erros'=>0];
        if(!$this->tabelaDisponivel()) return $resumo;
        $recuperar = $this->db->prepare("UPDATE meta_coexistence_history_jobs SET MCH_Status='pendente',MCH_WorkerId=NULL,MCH_ReservadoEm=NULL WHERE MCH_Status='processando' AND MCH_ReservadoEm < DATE_SUB(NOW(), INTERVAL 15 MINUTE) AND MCH_Tentativas < 3");
        $recuperar->execute();
        $resumo['recuperados'] = $recuperar->rowCount();
        for($i=0; $i<(int)$limite; $i++){
            $job = $this->reservarProximo($workerId);
            if(!$job) break;
            $resumo['reservados']++;
            try{
                $value = json_decode($job['MCH_Payload'], true);
                if(!is_array($value)) throw new \RuntimeException('Payload de history inválido.');
                $conta = $this->buscarConta((int)$job['MTA_ID']);
                if(!$conta) throw new \RuntimeException('Conta Meta do history não encontrada.');
                $this->ingestion->processarHistorico($value, $conta);
                $this->finalizar((int)$job['MCH_ID']);
                $resumo['processados']++;
            }catch(\Throwable $e){
                $this->falhar((int)$job['MCH_ID'], $e->getMessage());
                $resumo['erros']++;
            }
        }
        return $resumo;
    }

    private function tabelaDisponivel()
    {
        try{
            $sql = $this->db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='meta_coexistence_history_jobs'");
            return (int)$sql->fetchColumn() > 0;
        }catch(\Throwable $e){
            return false;
        }
    }

    private function atualizarProgresso($metaId, $requestId, $phase, $order, $progress, array $errors)
    {
        $declined = false;
        foreach($errors as $error){ if((int)($error['code'] ?? 0) === 2593109) $declined = true; }
        $status = $declined ? 'declined' : ((int)$progress === 100 ? 'completed' : 'processing');
        $sql = $this->db->prepare("UPDATE meta_contas SET MTA_HistorySyncRequestId=COALESCE(MTA_HistorySyncRequestId,?), MTA_HistoryPhase=COALESCE(?,MTA_HistoryPhase), MTA_HistoryChunkOrder=COALESCE(?,MTA_HistoryChunkOrder), MTA_HistoryProgress=CASE WHEN ? IS NULL THEN MTA_HistoryProgress ELSE GREATEST(COALESCE(MTA_HistoryProgress,0),?) END, MTA_HistorySyncStatus=CASE WHEN MTA_HistorySyncStatus IN ('completed','declined') THEN MTA_HistorySyncStatus ELSE ? END, MTA_LastSyncEventAt=NOW() WHERE MTA_ID=? AND MTA_OnboardingType='coexistence'");
        $safeProgress = $progress === false ? null : max(0,min(100,$progress));
        $sql->execute([$requestId ?: null, $phase ?: null, $order === false ? null : $order, $safeProgress, $safeProgress, $status, $metaId]);
    }

    private function reservarProximo($workerId)
    {
        $this->db->beginTransaction();
        try{
            $sql = $this->db->query("SELECT * FROM meta_coexistence_history_jobs WHERE MCH_Status='pendente' ORDER BY MCH_ID ASC LIMIT 1 FOR UPDATE");
            $job = $sql->fetch(PDO::FETCH_ASSOC);
            if(!$job){ $this->db->commit(); return null; }
            $update = $this->db->prepare("UPDATE meta_coexistence_history_jobs SET MCH_Status='processando',MCH_Tentativas=MCH_Tentativas+1,MCH_WorkerId=?,MCH_ReservadoEm=NOW() WHERE MCH_ID=? AND MCH_Status='pendente'");
            $update->execute([$workerId,(int)$job['MCH_ID']]);
            $this->db->commit();
            return $update->rowCount() ? $job : null;
        }catch(\Throwable $e){ if($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }

    private function buscarConta($id)
    {
        $sql = $this->db->prepare("SELECT * FROM meta_contas WHERE MTA_ID=? AND MTA_Ativo='S' LIMIT 1");
        $sql->execute([$id]); return $sql->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function finalizar($id)
    {
        $sql=$this->db->prepare("UPDATE meta_coexistence_history_jobs SET MCH_Status='concluido',MCH_Payload='{}',MCH_ProcessadoEm=NOW(),MCH_WorkerId=NULL,MCH_ReservadoEm=NULL WHERE MCH_ID=?"); $sql->execute([$id]);
    }

    private function falhar($id, $erro)
    {
        $erro=MensagemStatusService::sanitizarErro($erro); $sql=$this->db->prepare("UPDATE meta_coexistence_history_jobs SET MCH_Status='erro',MCH_UltimoErro=?,MCH_WorkerId=NULL,MCH_ReservadoEm=NULL WHERE MCH_ID=?"); $sql->execute([$erro,$id]);
    }
}
