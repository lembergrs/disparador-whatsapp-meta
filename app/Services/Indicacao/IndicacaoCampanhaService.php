<?php
namespace Services\Indicacao;
use Core\Database;
use InvalidArgumentException;
use Models\IndicacaoCampanha;
class IndicacaoCampanhaService{
    private $model;private $audit;private $db;
    public function __construct(IndicacaoCampanha $model=null,IndicacaoAuditoriaService $audit=null){$this->model=$model?:new IndicacaoCampanha();$this->audit=$audit?:new IndicacaoAuditoriaService();$this->db=Database::getInstance();}
    public function criar(array $d):int{$p=(float)($d['percentual']??0);if($p<=0||$p>100)throw new InvalidArgumentException('Percentual inválido.');$publica=($d['publica']??'N')==='S';$ativa=($d['ativo']??'N')==='S';$this->db->beginTransaction();try{if($publica&&$ativa&&$this->model->buscarPublicaAtiva(true))throw new InvalidArgumentException('Já existe campanha pública ativa.');$id=$this->model->criar($d);$this->audit->registrar('campanha',$id,'criada',null,$ativa?'ativa':'inativa',null,$d['usuario_id']??null,null,['percentual'=>$p]);$this->db->commit();return $id;}catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}}
    public function alterarAtivacao($id,$ativo,$publica=null,$usuarioId=null):void{$camp=$this->model->buscar($id);if(!$camp)throw new InvalidArgumentException('Campanha não encontrada.');$this->db->beginTransaction();try{if($ativo==='S'&&($publica??$camp['ICP_Publica'])==='S'){$atual=$this->model->buscarPublicaAtiva(true);if($atual&&(int)$atual['ICP_ID']!==(int)$id)throw new InvalidArgumentException('Já existe campanha pública ativa.');}$this->model->atualizarStatus($id,$ativo,$publica);$this->audit->registrar('campanha',$id,$ativo==='S'?'ativada':'inativada',$camp['ICP_Ativo'],$ativo,null,$usuarioId);$this->db->commit();}catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}}
}
