<?php
namespace Services\Indicacao;
use Core\Database;
use InvalidArgumentException;
use Models\Indicacao;
use Models\IndicacaoCredito;
use PDO;
class IndicacaoCreditoService{
    private $model;private $indicacoes;private $audit;private $trans;private $db;
    public function __construct(IndicacaoCredito $model=null,Indicacao $indicacoes=null,IndicacaoAuditoriaService $audit=null,IndicacaoStatusTransitionService $trans=null,PDO $db=null){$this->db=$db?:Database::getInstance();$this->model=$model?:new IndicacaoCredito($this->db);$this->indicacoes=$indicacoes?:new Indicacao($this->db);$this->audit=$audit?:new IndicacaoAuditoriaService();$this->trans=$trans?:new IndicacaoStatusTransitionService();}
    public function criarPorIndicacao($indicacaoId,$usuarioId=null,$status='pendente'):int{return $this->transacao(function()use($indicacaoId,$usuarioId,$status){if($this->model->buscarPorIndicacao($indicacaoId,true))throw new InvalidArgumentException('Indicação já possui crédito.');$ind=$this->indicacoes->buscar($indicacaoId,true);if(!$ind)throw new InvalidArgumentException('Indicação não encontrada.');$id=$this->model->criar(['indicacao_id'=>$indicacaoId,'indicador_id'=>$ind['CLI_Indicador_ID'],'campanha_id'=>$ind['ICP_ID'],'percentual'=>$ind['IND_PercentualSnapshot'],'status'=>$status]);$this->audit->registrar('credito',$id,'credito_criado',null,$status,null,$usuarioId,null,['campanha_id'=>$ind['ICP_ID'],'indicador_id'=>$ind['CLI_Indicador_ID'],'percentual'=>$ind['IND_PercentualSnapshot']]);return $id;});}
    public function alterarStatus($id,$novo,$usuarioId=null,$motivo=null):void{$this->transacao(function()use($id,$novo,$usuarioId,$motivo){$r=$this->model->buscar($id);if(!$r)throw new InvalidArgumentException('Crédito não encontrado.');$atual=$r['ICR_Status'];$this->trans->validar('credito',$atual,$novo);$agora=date('Y-m-d H:i:s');$datas=[];$map=['bloqueado'=>'bloqueado_em','cancelado'=>'cancelado_em','expirado'=>'expirado_em','utilizado'=>'utilizado_em'];if(isset($map[$novo]))$datas[$map[$novo]]=$agora;if($novo==='liberado'&&empty($r['ICR_LiberadoEm']))$datas['liberado_em']=$agora;if(!$this->model->alterarStatus($id,$atual,$novo,$datas))throw new \RuntimeException('Status alterado por outro processo.');$acao='status_alterado';if($novo==='liberado')$acao=$atual==='reservado'?'credito_reliberado':'credito_liberado';elseif($novo==='reservado')$acao='credito_reservado';elseif($novo==='utilizado')$acao='credito_utilizado';$this->audit->registrar('credito',$id,$acao,$atual,$novo,$motivo,$usuarioId);});}
    public function listarFIFO($indicadorId,$limite=100){return $this->model->listarFIFO($indicadorId,$limite);}
    public function selecionarDisponiveisFifo($indicadorId,$limite,$forUpdate=true){return $this->model->selecionarDisponiveisFifo($indicadorId,$limite,$forUpdate);}
    private function transacao(callable $callback){$propria=!$this->db->inTransaction();if($propria)$this->db->beginTransaction();try{$resultado=$callback();if($propria)$this->db->commit();return $resultado;}catch(\Throwable $e){if($propria&&$this->db->inTransaction())$this->db->rollBack();throw $e;}}
}
