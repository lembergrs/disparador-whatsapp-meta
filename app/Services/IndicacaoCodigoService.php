<?php
namespace Services;
use Models\IndicacaoCodigo; use Services\Indicacao\CodigoIndicacaoGeneratorInterface; use Services\Indicacao\CodigoIndicacaoNormalizer; use Services\Indicacao\IndicacaoStatusTransitionService; use PDO;
class IndicacaoCodigoService
{
 private $db,$modelo,$audit,$generator,$max; public function __construct(PDO $db,IndicacaoCodigo $m,IndicacaoAuditoriaService $a,CodigoIndicacaoGeneratorInterface $g,$max=10){$this->db=$db;$this->modelo=$m;$this->audit=$a;$this->generator=$g;$this->max=max(1,(int)$max);}
 public function criar($clienteId,$campanhaId,array $cliente): int {return $this->tx(function()use($clienteId,$campanhaId,$cliente){for($i=0;$i<$this->max;$i++){$codigo=$this->generator->gerar($cliente);$normal=CodigoIndicacaoNormalizer::normalizar($codigo);if($this->modelo->existeNormalizado($normal))continue;try{$id=$this->modelo->criar(['cliente_id'=>$clienteId,'campanha_id'=>$campanhaId,'codigo'=>$codigo,'normalizado'=>$normal]);$this->aud($id,'criado',null,'nao_liberado');return $id;}catch(\PDOException $e){if((string)$e->getCode()==='23000'||(string)$e->getCode()==='19')continue;throw $e;}}throw new \RuntimeException('Não foi possível gerar código único.');});}
 public function buscar($codigo){return $this->modelo->buscarNormalizado(CodigoIndicacaoNormalizer::normalizar($codigo));}
 public function transicionar($id,$novo,$motivo=null): bool {return $this->tx(function()use($id,$novo,$motivo){$c=$this->modelo->buscar($id);if(!$c)throw new \DomainException('Código não encontrado.');$a=$c['ICD_Status'];IndicacaoStatusTransitionService::validar('codigo',$a,$novo);if(!$this->modelo->status($id,$a,$novo))throw new \RuntimeException('Código alterado concorrentemente.');$this->aud($id,$novo,$a,$novo,$motivo);return true;});}
 private function aud($id,$acao,$a,$n,$m=null){$this->audit->registrar(['entidade'=>'codigo','entidade_id'=>$id,'acao'=>$acao,'status_anterior'=>$a,'status_novo'=>$n,'motivo'=>$m]);}
 private function tx(callable $f){$own=!$this->db->inTransaction();if($own)$this->db->beginTransaction();try{$r=$f();if($own)$this->db->commit();return $r;}catch(\Throwable $e){if($own&&$this->db->inTransaction())$this->db->rollBack();throw $e;}}
}
