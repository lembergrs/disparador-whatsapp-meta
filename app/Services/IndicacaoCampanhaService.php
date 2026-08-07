<?php
namespace Services;
use Models\IndicacaoCampanha; use PDO;
class IndicacaoCampanhaService
{
 private $db,$modelo,$auditoria; public function __construct(PDO $db,IndicacaoCampanha $modelo,IndicacaoAuditoriaService $auditoria){$this->db=$db;$this->modelo=$modelo;$this->auditoria=$auditoria;}
 public function criar(array $d): int {$this->validar($d);return $this->tx(function()use($d){$d['regras_snapshot']=$this->json($d['regras']??[]);$id=$this->modelo->criar($d);$this->audit($id,'criada',null,'N',$d);return $id;});}
 public function editar($id,array $d): bool {$this->validar($d);return $this->tx(function()use($id,$d){$antes=$this->modelo->buscar($id,true);if(!$antes)throw new \DomainException('Campanha não encontrada.');if($antes['ICP_Ativo']==='S'||$this->modelo->possuiIndicacoes($id))throw new \DomainException('Campanha ativa ou com histórico não pode ter suas regras alteradas.');$d['regras_snapshot']=$this->json($d['regras']??[]);$ok=$this->modelo->atualizar($id,$d);$this->audit($id,'editada',$antes['ICP_Ativo'],$antes['ICP_Ativo'],$d);return $ok;});}
 public function ativar($id): bool {return $this->tx(function()use($id){$c=$this->modelo->buscar($id,true);if(!$c)throw new \DomainException('Campanha não encontrada.');if($c['ICP_Publica']==='S'){$ativa=$this->modelo->buscarPublicaAtiva(true);if($ativa&&(int)$ativa['ICP_ID']!==(int)$id)throw new \DomainException('Já existe campanha pública ativa.');}$ok=$this->modelo->status($id,true);$this->audit($id,'ativada',$c['ICP_Ativo'],'S',[]);return $ok;});}
 public function inativar($id): bool {return $this->tx(function()use($id){$c=$this->modelo->buscar($id,true);if(!$c)throw new \DomainException('Campanha não encontrada.');$ok=$this->modelo->status($id,false);$this->audit($id,'inativada',$c['ICP_Ativo'],'N',[]);return $ok;});}
 private function validar($d){$p=(float)($d['percentual']??0);if($p<=0||$p>100)throw new \InvalidArgumentException('Percentual deve ser maior que zero e menor ou igual a 100.');if(trim((string)($d['nome']??''))==='')throw new \InvalidArgumentException('Nome obrigatório.');}
 private function json($v){$j=json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);if($j===false)throw new \InvalidArgumentException('Regras inválidas.');return $j;}
 private function audit($id,$acao,$a,$n,$d){$this->auditoria->registrar(['entidade'=>'campanha','entidade_id'=>$id,'acao'=>$acao,'status_anterior'=>$a,'status_novo'=>$n,'usuario_id'=>$d['usuario_id']??null,'dados'=>['percentual'=>$d['percentual']??null,'publica'=>$d['publica']??null]]);}
 private function tx(callable $f){$own=!$this->db->inTransaction();if($own)$this->db->beginTransaction();try{$r=$f();if($own)$this->db->commit();return $r;}catch(\Throwable $e){if($own&&$this->db->inTransaction())$this->db->rollBack();throw $e;}}
}
