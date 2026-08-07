<?php
namespace Services\Indicacao;
use Models\IndicacaoAuditoria;
class IndicacaoAuditoriaService{
    private $model;
    private const CHAVES=['origem','campanha_id','cliente_id','indicador_id','indicado_id','percentual','codigo_prefixo'];
    public function __construct(IndicacaoAuditoria $model=null){$this->model=$model?:new IndicacaoAuditoria();}
    public function registrar($entidade,$id,$acao,$anterior=null,$novo=null,$motivo=null,$usuarioId=null,$correlacao=null,array $dados=[]):void{
        $seguros=[];foreach(self::CHAVES as $k){if(array_key_exists($k,$dados))$seguros[$k]=$this->sanitizar($dados[$k]);}
        $this->model->registrar(['entidade'=>$entidade,'entidade_id'=>$id,'acao'=>$acao,'status_anterior'=>$anterior,'status_novo'=>$novo,'motivo'=>$this->sanitizar($motivo),'usuario_id'=>$usuarioId,'correlacao'=>$this->sanitizar($correlacao),'dados'=>$seguros]);
    }
    private function sanitizar($v){if($v===null)return null;$s=preg_replace('/[\r\n\t]+/',' ',trim((string)$v));$s=preg_replace('/(token|authorization|bearer|secret|password|senha|credential|payload)\s*[:=]?\s*\S+/i','$1=[removido]',$s);return mb_substr($s,0,500,'UTF-8');}
}
