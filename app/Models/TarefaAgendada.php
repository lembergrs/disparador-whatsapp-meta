<?php

namespace Models;

use Core\Database;
use PDO;
use PDOException;

class TarefaAgendada
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?: Database::getInstance();
    }

    public function inserir(array $dados): array
    {
        try{
            $sql = $this->db->prepare("INSERT INTO tarefas_agendadas (TAG_Tipo,TAG_Status,TAG_Prioridade,TAG_ExecutarEm,TAG_Payload,TAG_ChaveIdempotencia,TAG_Tentativas,TAG_MaxTentativas) VALUES (?,'pendente',?,?,?,?,0,?)");
            $sql->execute([$dados['tipo'],$dados['prioridade'],$dados['executar_em'],$dados['payload'],$dados['chave_idempotencia'],$dados['max_tentativas']]);
            return ['criada'=>true, 'id'=>(int)$this->db->lastInsertId()];
        }catch(PDOException $e){
            if($dados['chave_idempotencia'] !== null && $this->violacaoUnica($e)){
                $existente = $this->buscarPorChave($dados['chave_idempotencia']);
                return ['criada'=>false, 'id'=>(int)($existente['TAG_ID'] ?? 0), 'tarefa'=>$existente];
            }
            throw $e;
        }
    }

    public function buscarPorChave($chave)
    {
        $sql = $this->db->prepare('SELECT * FROM tarefas_agendadas WHERE TAG_ChaveIdempotencia=? LIMIT 1');
        $sql->execute([$chave]);
        return $sql->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function buscar($id)
    {
        $sql = $this->db->prepare('SELECT * FROM tarefas_agendadas WHERE TAG_ID=? LIMIT 1');
        $sql->execute([(int)$id]);
        return $sql->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function cancelar($id): bool
    {
        $sql = $this->db->prepare("UPDATE tarefas_agendadas SET TAG_Status='cancelada',TAG_FinalizadaEm=?,TAG_WorkerId=NULL,TAG_ReservadaEm=NULL WHERE TAG_ID=? AND TAG_Status='pendente'");
        $sql->execute([$this->agora(),(int)$id]);
        return $sql->rowCount() === 1;
    }

    public function reagendar($id, $executarEm): bool
    {
        $sql = $this->db->prepare("UPDATE tarefas_agendadas SET TAG_Status='pendente',TAG_ExecutarEm=?,TAG_ProximaTentativaEm=NULL,TAG_Tentativas=0,TAG_ReservadaEm=NULL,TAG_WorkerId=NULL,TAG_IniciadaEm=NULL,TAG_FinalizadaEm=NULL,TAG_UltimoErro=NULL WHERE TAG_ID=? AND TAG_Status IN ('pendente','falha','cancelada')");
        $sql->execute([$executarEm,(int)$id]);
        return $sql->rowCount() === 1;
    }

    public function reservarProxima($workerId, $leaseMinutos, \DateTimeInterface $agora = null)
    {
        $agoraTexto = $this->formatar($agora ?: new \DateTimeImmutable('now'));
        $limiteLease = (new \DateTimeImmutable($agoraTexto))->modify('-' . max(1, (int)$leaseMinutos) . ' minutes')->format('Y-m-d H:i:s');
        $this->falharLeasesEsgotados($limiteLease, $agoraTexto);
        $this->db->beginTransaction();
        try{
            $forUpdate = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $sql = $this->db->prepare("SELECT * FROM tarefas_agendadas WHERE ((TAG_Status='pendente' AND TAG_ExecutarEm<=? AND (TAG_ProximaTentativaEm IS NULL OR TAG_ProximaTentativaEm<=?)) OR (TAG_Status='processando' AND TAG_ReservadaEm<? AND TAG_Tentativas<TAG_MaxTentativas)) ORDER BY TAG_ExecutarEm ASC,TAG_Prioridade ASC,TAG_ID ASC LIMIT 1{$forUpdate}");
            $sql->execute([$agoraTexto,$agoraTexto,$limiteLease]);
            $tarefa = $sql->fetch(PDO::FETCH_ASSOC);
            if(!$tarefa){ $this->db->commit(); return null; }
            $recuperada = $tarefa['TAG_Status'] === 'processando';
            $update = $this->db->prepare("UPDATE tarefas_agendadas SET TAG_Status='processando',TAG_Tentativas=TAG_Tentativas+1,TAG_ReservadaEm=?,TAG_WorkerId=?,TAG_IniciadaEm=COALESCE(TAG_IniciadaEm,?),TAG_ProximaTentativaEm=NULL,TAG_UltimoErro=CASE WHEN ?=1 THEN 'lease_expirado_recuperado' ELSE TAG_UltimoErro END WHERE TAG_ID=? AND TAG_Status=?");
            $update->execute([$agoraTexto,$workerId,$agoraTexto,$recuperada ? 1 : 0,(int)$tarefa['TAG_ID'],$tarefa['TAG_Status']]);
            if($update->rowCount() !== 1){ $this->db->rollBack(); return null; }
            $this->db->commit();
            $tarefa = $this->buscar($tarefa['TAG_ID']);
            $tarefa['recuperada'] = $recuperada;
            return $tarefa;
        }catch(\Throwable $e){
            if($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function concluir($id, $workerId, $finalizadaEm): bool
    {
        $sql = $this->db->prepare("UPDATE tarefas_agendadas SET TAG_Status='concluida',TAG_FinalizadaEm=?,TAG_ReservadaEm=NULL,TAG_WorkerId=NULL,TAG_UltimoErro=NULL WHERE TAG_ID=? AND TAG_Status='processando' AND TAG_WorkerId=?");
        $sql->execute([$finalizadaEm,(int)$id,$workerId]);
        return $sql->rowCount() === 1;
    }

    public function reagendarRetry($id, $workerId, $proxima, $erro): bool
    {
        $sql = $this->db->prepare("UPDATE tarefas_agendadas SET TAG_Status='pendente',TAG_ProximaTentativaEm=?,TAG_ReservadaEm=NULL,TAG_WorkerId=NULL,TAG_UltimoErro=? WHERE TAG_ID=? AND TAG_Status='processando' AND TAG_WorkerId=?");
        $sql->execute([$proxima,$erro,(int)$id,$workerId]);
        return $sql->rowCount() === 1;
    }

    public function falhar($id, $workerId, $finalizadaEm, $erro): bool
    {
        $sql = $this->db->prepare("UPDATE tarefas_agendadas SET TAG_Status='falha',TAG_FinalizadaEm=?,TAG_ReservadaEm=NULL,TAG_WorkerId=NULL,TAG_UltimoErro=? WHERE TAG_ID=? AND TAG_Status='processando' AND TAG_WorkerId=?");
        $sql->execute([$finalizadaEm,$erro,(int)$id,$workerId]);
        return $sql->rowCount() === 1;
    }

    private function falharLeasesEsgotados($limiteLease, $agora): void
    {
        $sql = $this->db->prepare("UPDATE tarefas_agendadas SET TAG_Status='falha',TAG_FinalizadaEm=?,TAG_ReservadaEm=NULL,TAG_WorkerId=NULL,TAG_UltimoErro='lease_expirado_max_tentativas' WHERE TAG_Status='processando' AND TAG_ReservadaEm<? AND TAG_Tentativas>=TAG_MaxTentativas");
        $sql->execute([$agora,$limiteLease]);
    }

    private function violacaoUnica(PDOException $e): bool
    {
        return (string)$e->getCode() === '23000' || strpos(strtolower($e->getMessage()), 'unique') !== false;
    }

    private function agora(): string { return date('Y-m-d H:i:s'); }
    private function formatar(\DateTimeInterface $data): string { return $data->format('Y-m-d H:i:s'); }
}
