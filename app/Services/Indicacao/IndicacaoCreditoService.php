<?php
namespace Services\Indicacao;
use Core\Database;
use InvalidArgumentException;
use Models\Indicacao;
use Models\IndicacaoCredito;
class IndicacaoCreditoService{
    private $model;private $indicacoes;private $audit;private $trans;private $db;
    public function __construct(IndicacaoCredito $model=null,Indicacao $indicacoes=null,IndicacaoAuditoriaService $audit=null,IndicacaoStatusTransitionService $trans=null){$this->model=$model?:new IndicacaoCredito();$this->indicacoes=$indicacoes?:new Indicacao();$this->audit=$audit?:new IndicacaoAuditoriaService();$this->trans=$trans?:new IndicacaoStatusTransitionService();$this->db=Database::getInstance();}
    public function criarPorIndicacao($indicacaoId,$usuarioId=null,$status='pendente'):int{if($this->model->buscarPorIndicacao($indicacaoId))throw new InvalidArgumentException('Indicação já possui crédito.');$ind=$this->indicacoes->buscar($indicacaoId);if(!$ind)throw new InvalidArgumentException('Indicação não encontrada.');$this->db->beginTransaction();try{$id=$this->model->criar(['indicacao_id'=>$indicacaoId,'indicador_id'=>$ind['CLI_Indicador_ID'],'campanha_id'=>$ind['ICP_ID'],'percentual'=>$ind['IND_PercentualSnapshot'],'status'=>$status]);$this->audit->registrar('credito',$id,'criado',null,$status,null,$usuarioId,null,['campanha_id'=>$ind['ICP_ID'],'indicador_id'=>$ind['CLI_Indicador_ID'],'percentual'=>$ind['IND_PercentualSnapshot']]);$this->db->commit();return $id;}catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}}
    public function alterarStatus($id,$novo,$usuarioId=null,$motivo=null):void{$r=$this->model->buscar($id);if(!$r)throw new InvalidArgumentException('Crédito não encontrado.');$atual=$r['ICR_Status'];$this->trans->validar('credito',$atual,$novo);$agora=date('Y-m-d H:i:s');$datas=[];$map=['liberado'=>'liberado_em','bloqueado'=>'bloqueado_em','cancelado'=>'cancelado_em','expirado'=>'expirado_em','utilizado'=>'utilizado_em'];if(isset($map[$novo]))$datas[$map[$novo]]=$agora;$this->db->beginTransaction();try{if(!$this->model->alterarStatus($id,$atual,$novo,$datas))throw new \RuntimeException('Status alterado por outro processo.');$this->audit->registrar('credito',$id,'status_alterado',$atual,$novo,$motivo,$usuarioId);$this->db->commit();}catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}}
    public function listarFIFO($indicadorId,$limite=100){return $this->model->listarFIFO($indicadorId,$limite);}
}
