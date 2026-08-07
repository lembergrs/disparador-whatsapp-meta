<?php
namespace Services;
use Models\IndicacaoCredito; use Models\Indicacao; use Services\Indicacao\IndicacaoStatusTransitionService; use PDO;
class IndicacaoCreditoService
{
 private $db,$modelo,$indicacoes,$audit; public function __construct(PDO $db,IndicacaoCredito $m,Indicacao $i,IndicacaoAuditoriaService $a){$this->db=$db;$this->modelo=$m;$this->indicacoes=$i;$this->audit=$a;}
 public function criar($indicacaoId): int {return $this->tx(function()use($indicacaoId){$i=$this->indicacoes->buscar($indicacaoId);if(!$i)throw new \DomainException('Indicação não encontrada.');$id=$this->modelo->criar(['indicacao_id'=>$indicacaoId,'indicador_id'=>$i['CLI_Indicador_ID'],'campanha_id'=>$i['ICP_ID'],'percentual'=>$i['IND_PercentualSnapshot']]);$this->aud($id,'credito_criado',null,'pendente',null,['percentual'=>$i['IND_PercentualSnapshot']]);return $id;});}
 public function transicionar($id,$novo,$motivo=null): bool {return $this->tx(function()use($id,$novo,$motivo){$c=$this->modelo->buscar($id);if(!$c)throw new \DomainException('Crédito não encontrado.');$a=$c['ICR_Status'];IndicacaoStatusTransitionService::validar('credito',$a,$novo);if(!$this->modelo->status($id,$a,$novo))throw new \RuntimeException('Crédito alterado concorrentemente.');$acoes=['reservado'=>'credito_reservado','utilizado'=>'credito_utilizado'];if($novo==='liberado')$acoes['liberado']=$a==='reservado'?'credito_reliberado':'credito_liberado';$this->aud($id,$acoes[$novo]??'status_alterado',$a,$novo,$motivo);return true;});}
 public function disponiveisFifo($cliente,$limite=100): array{return $this->modelo->listarDisponiveisFifo($cliente,$limite);}
 private function aud($id,$acao,$a,$n,$m=null,$d=[]){$this->audit->registrar(['entidade'=>'credito','entidade_id'=>$id,'acao'=>$acao,'status_anterior'=>$a,'status_novo'=>$n,'motivo'=>$m,'dados'=>$d]);}
 private function tx(callable $f){$own=!$this->db->inTransaction();if($own)$this->db->beginTransaction();try{$r=$f();if($own)$this->db->commit();return $r;}catch(\Throwable $e){if($own&&$this->db->inTransaction())$this->db->rollBack();throw $e;}}
}
