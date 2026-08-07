<?php
namespace Services;
use Models\IndicacaoAuditoria;
class IndicacaoAuditoriaService
{
 private $modelo; public function __construct(IndicacaoAuditoria $modelo){$this->modelo=$modelo;}
 public function registrar(array $dados): int {$dados['motivo']=$this->texto($dados['motivo']??null);$permitidos=['campos','origem','percentual','publica'];$seguros=array_intersect_key($dados['dados']??[],array_flip($permitidos));$json=json_encode($seguros,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$dados['dados']=$json==='[]'?null:$json;return $this->modelo->registrar($dados);}
 private function texto($v){if($v===null)return null;$v=preg_replace('/[\r\n\t]+/',' ',trim((string)$v));$v=preg_replace('/(token|senha|password|secret|authorization|payload)\s*[:=]?\s*\S+/i','$1=[removido]',$v);return mb_substr($v,0,500,'UTF-8');}
}
