<?php

namespace Services\Indicacao;

use Core\Database;
use InvalidArgumentException;
use Models\IndicacaoCodigo;
use PDO;

class IndicacaoCodigoService
{
    private $model;
    private $gen;
    private $norm;
    private $audit;
    private $trans;
    private $db;

    public function __construct(IndicacaoCodigo $model=null, CodigoIndicacaoGeneratorInterface $gen=null, CodigoIndicacaoNormalizer $norm=null, IndicacaoAuditoriaService $audit=null, IndicacaoStatusTransitionService $trans=null, PDO $db=null)
    {
        $this->db = $db ?: Database::getInstance();
        $this->model = $model ?: new IndicacaoCodigo($this->db);
        $this->gen = $gen ?: new CodigoIndicacaoPadraoGenerator();
        $this->norm = $norm ?: new CodigoIndicacaoNormalizer();
        $this->audit = $audit ?: new IndicacaoAuditoriaService();
        $this->trans = $trans ?: new IndicacaoStatusTransitionService();
    }

    public function criar($clienteId, $campanhaId, array $cliente, $usuarioId=null): int
    {
        for($i=0; $i<10; $i++){
            $codigo = $this->gen->gerar($cliente);
            $n = $this->norm->normalizar($codigo);
            if($this->model->buscarPorNormalizado($n)) continue;
            $propria = !$this->db->inTransaction();
            if($propria){
                $this->db->beginTransaction();
            }
            try{
                $id = $this->model->criar([
                    'cliente_id'=>$clienteId,
                    'campanha_id'=>$campanhaId,
                    'codigo'=>$codigo,
                    'codigo_normalizado'=>$n
                ]);
                $this->audit->registrar('codigo',$id,'criado',null,'nao_liberado',null,$usuarioId,null,[
                    'cliente_id'=>$clienteId,
                    'campanha_id'=>$campanhaId,
                    'codigo_prefixo'=>strtok($codigo,'-')
                ]);
                if($propria){
                    $this->db->commit();
                }
                return $id;
            }catch(\PDOException $e){
                if($propria && $this->db->inTransaction()) $this->db->rollBack();
                if($e->getCode()==='23000') continue;
                throw $e;
            }catch(\Throwable $e){
                if($propria && $this->db->inTransaction()) $this->db->rollBack();
                throw $e;
            }
        }
        throw new \RuntimeException('Não foi possível gerar código único.');
    }

    public function buscar($codigo, $forUpdate=false)
    {
        return $this->model->buscarPorNormalizado($this->norm->normalizar($codigo), $forUpdate);
    }

    public function alterarStatus($id, $novo, $usuarioId=null, $motivo=null): void
    {
        $c = $this->model->buscar($id);
        if(!$c) throw new InvalidArgumentException('Código não encontrado.');
        $atual = $c['ICD_Status'];
        $this->trans->validar('codigo',$atual,$novo);
        $agora = date('Y-m-d H:i:s');
        $datas = [];
        if($novo==='ativo') $datas['liberado_em']=$agora;
        if($novo==='suspenso') $datas['suspenso_em']=$agora;
        if($novo==='cancelado') $datas['cancelado_em']=$agora;
        $propria = !$this->db->inTransaction();
        if($propria){
            $this->db->beginTransaction();
        }
        try{
            if(!$this->model->alterarStatus($id,$atual,$novo,$datas)) throw new \RuntimeException('Status alterado por outro processo.');
            $this->audit->registrar('codigo',$id,'status_alterado',$atual,$novo,$motivo,$usuarioId);
            if($propria){
                $this->db->commit();
            }
        }catch(\Throwable $e){
            if($propria && $this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }
}
