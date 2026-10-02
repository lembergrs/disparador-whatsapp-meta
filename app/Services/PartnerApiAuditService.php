<?php
namespace Services;

use Models\ParceiroApiAuditoria;

class PartnerApiAuditService
{
    private $model;
    public function __construct($model=null){$this->model=$model ?: new ParceiroApiAuditoria();}

    public function registrar(array $parceiro,$evento,$status,$codigo,$mensagem,$payload=null,$severidade='warning')
    {
        try{
            list($snapshot,$truncado)=$this->snapshot($payload);
            $this->model->registrar([
                'parceiro_id'=>(int)($parceiro['PAR_ID']??0),
                'api_key_id'=>(int)($parceiro['PAK_ID']??0),
                'severidade'=>in_array($severidade,['info','warning','security'],true)?$severidade:'warning',
                'evento'=>substr((string)$evento,0,80),
                'metodo'=>substr((string)($_SERVER['REQUEST_METHOD']??''),0,10),
                'endpoint'=>substr((string)($_GET['url']??($_SERVER['REQUEST_URI']??'')),0,160),
                'http_status'=>(int)$status,
                'ip'=>$this->ip(),
                'erro_codigo'=>substr((string)$codigo,0,100),
                'erro_mensagem'=>substr((string)$mensagem,0,500),
                'payload'=>$snapshot,
                'payload_truncado'=>$truncado
            ]);
        }catch(\Throwable $e){
            error_log('Partner API audit: '.$e->getMessage());
        }
    }

    private function snapshot($payload)
    {
        if($payload===null) return [null,false];
        if(is_string($payload)){
            $decoded=json_decode($payload,true);
            $payload=is_array($decoded)?$decoded:['raw'=>$payload];
        }
        $payload=$this->sanitizar($payload);
        $json=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        if($json===false)$json='{"snapshot":"indisponivel"}';
        $max=8192;$truncado=strlen($json)>$max;
        if($truncado)$json=substr($json,0,$max).'…';
        return [$json,$truncado];
    }

    private function sanitizar($valor,$chave='')
    {
        $sensivel=preg_match('/authorization|token|secret|password|senha|api[_-]?key|access[_-]?token/i',(string)$chave);
        if($sensivel)return '[REDACTED]';
        if(is_array($valor)){
            $out=[];foreach($valor as $k=>$v)$out[$k]=$this->sanitizar($v,(string)$k);return $out;
        }
        if(is_object($valor))return '[OBJECT]';
        if(is_string($valor) && strlen($valor)>2048)return substr($valor,0,2048).'…';
        return $valor;
    }

    private function ip()
    {
        return substr((string)($_SERVER['REMOTE_ADDR']??''),0,45);
    }
}
