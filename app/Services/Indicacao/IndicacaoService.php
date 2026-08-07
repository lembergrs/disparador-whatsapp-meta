<?php

namespace Services\Indicacao;

use Core\Database;
use InvalidArgumentException;
use Models\Indicacao;
use Models\IndicacaoCampanha;
use Models\IndicacaoCodigo;
use PDO;

class IndicacaoService
{
    private $model;
    private $campanhas;
    private $codigos;
    private $audit;
    private $trans;
    private $db;

    public function __construct(Indicacao $model=null, IndicacaoCampanha $campanhas=null, IndicacaoCodigo $codigos=null, IndicacaoAuditoriaService $audit=null, IndicacaoStatusTransitionService $trans=null, PDO $db=null)
    {
        $this->db = $db ?: Database::getInstance();
        $this->model = $model ?: new Indicacao($this->db);
        $this->campanhas = $campanhas ?: new IndicacaoCampanha($this->db);
        $this->codigos = $codigos ?: new IndicacaoCodigo($this->db);
        $this->audit = $audit ?: new IndicacaoAuditoriaService();
        $this->trans = $trans ?: new IndicacaoStatusTransitionService();
    }

    public function criar($codigoId, $indicadorId, $indicadoId, $origem='manual', $usuarioId=null): int
    {
        if((int)$indicadorId === (int)$indicadoId) throw new InvalidArgumentException('Autoindicação não permitida.');
        if(!in_array($origem,['link','manual'],true)) throw new InvalidArgumentException('Origem inválida.');

        return $this->transacao(function() use ($codigoId,$indicadorId,$indicadoId,$origem,$usuarioId){
            if($this->model->buscarPorIndicado($indicadoId)) throw new InvalidArgumentException('Cliente já possui indicação.');
            $codigo = $this->codigos->buscar($codigoId);
            if(!$codigo || $codigo['ICD_Status'] !== 'ativo') throw new InvalidArgumentException('Código inelegível.');
            if((int)$codigo['CLI_ID'] !== (int)$indicadorId) throw new InvalidArgumentException('Código não pertence ao indicador informado.');
            $camp = $this->campanhas->buscar($codigo['ICP_ID'], true);
            if(!$camp) throw new InvalidArgumentException('Campanha não encontrada.');
            $percentual = (float)$camp['ICP_Percentual'];
            $id = $this->model->criar([
                'codigo_id'=>$codigoId,
                'campanha_id'=>$camp['ICP_ID'],
                'indicador_id'=>$indicadorId,
                'indicado_id'=>$indicadoId,
                'percentual'=>$percentual,
                'origem'=>$origem,
                'status'=>'cadastrada'
            ]);
            $this->audit->registrar('indicacao',$id,'indicacao_criada',null,'cadastrada',null,$usuarioId,null,[
                'origem'=>$origem,
                'campanha_id'=>$camp['ICP_ID'],
                'indicador_id'=>$indicadorId,
                'indicado_id'=>$indicadoId,
                'percentual'=>$percentual
            ]);
            return $id;
        });
    }

    public function alterarStatus($id, $novo, $usuarioId=null, $motivo=null, array $datas=[]): void
    {
        $this->transacao(function() use ($id,$novo,$usuarioId,$motivo,$datas){
            $r = $this->model->buscar($id);
            if(!$r) throw new InvalidArgumentException('Indicação não encontrada.');
            $atual = $r['IND_Status'];
            $this->trans->validar('indicacao',$atual,$novo);
            if(!$this->model->alterarStatus($id,$atual,$novo,$motivo,$datas)) throw new \RuntimeException('Status alterado por outro processo.');
            $acao = $novo === 'cancelada' ? 'indicacao_cancelada' : 'status_alterado';
            $this->audit->registrar('indicacao',$id,$acao,$atual,$novo,$motivo,$usuarioId);
        });
    }

    private function transacao(callable $callback)
    {
        $propria = !$this->db->inTransaction();
        if($propria) $this->db->beginTransaction();
        try{
            $resultado = $callback();
            if($propria) $this->db->commit();
            return $resultado;
        }catch(\Throwable $e){
            if($propria && $this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }
}
