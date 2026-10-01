<?php
namespace Services;

use Models\ParceiroApi;
use Models\ParceiroWebhookEvento;

class PartnerWebhookDeliveryService
{
    private $eventos;
    private $api;
    public function __construct(ParceiroWebhookEvento $eventos=null,ParceiroApi $api=null){$this->eventos=$eventos?:new ParceiroWebhookEvento();$this->api=$api?:new ParceiroApi();}

    public function processarPendentes($limite=20)
    {
        $r=['recuperados'=>$this->eventos->recuperarTravados(15),'reservados'=>0,'entregues'=>0,'retries'=>0,'falhas'=>0];
        for($i=0;$i<max(1,(int)$limite);$i++){
            $e=$this->eventos->reservarProximo(); if(!$e)break; $r['reservados']++;
            try{
                $destino=$this->validarDestino($e['PAR_WebhookUrl']);
                $secret=$this->api->segredoWebhook((int)$e['PAR_ID']);
                if(!$secret) throw new \RuntimeException('Segredo de assinatura do webhook indisponível.');
                $body=(string)$e['PWE_Payload'];
                $timestamp=(string)time();
                $signature=hash_hmac('sha256',$timestamp.'.'.$body,$secret);
                [$http,$erro]=$this->post($e['PAR_WebhookUrl'],$body,$timestamp,$signature,$e['PWE_EventId'],$destino);
                if($http>=200&&$http<300){$this->eventos->marcarEntregue($e['PWE_ID'],$http);$r['entregues']++;continue;}
                $msg=$erro ?: 'HTTP '.$http;
                $this->retry($e,$http,$msg); ((int)$e['PWE_Tentativas'] >= (int)$e['PWE_MaxTentativas'])?$r['falhas']++:$r['retries']++;
            }catch(\Throwable $x){
                $this->retry($e,null,$x->getMessage()); ((int)$e['PWE_Tentativas'] >= (int)$e['PWE_MaxTentativas'])?$r['falhas']++:$r['retries']++;
            }
        }
        return $r;
    }

    private function retry(array $e,$http,$erro)
    {
        $tent=(int)$e['PWE_Tentativas'];
        $delay=min(3600,30*(2**max(0,$tent-1)));
        $this->eventos->marcarFalhaOuRetry($e,$http,$erro,$delay);
    }

    private function post($url,$body,$timestamp,$signature,$eventId,array $destino)
    {
        $ch=curl_init();
        curl_setopt_array($ch,[
            CURLOPT_URL=>$url,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_MAXREDIRS=>0,
            CURLOPT_RESOLVE=>[$destino['resolve']],
            CURLOPT_HTTPHEADER=>[
                'Content-Type: application/json','User-Agent: Disparador-Partner-Webhook/1.0',
                'X-Disparador-Event-Id: '.$eventId,'X-Disparador-Timestamp: '.$timestamp,
                'X-Disparador-Signature: sha256='.$signature
            ]
        ]);
        $resp=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
        if($resp===false && $err==='')$err='Falha HTTP sem resposta.';
        return [$http,$err];
    }

    private function validarDestino($url)
    {
        $parts=parse_url((string)$url);
        if(!$parts || strtolower((string)($parts['scheme']??''))!=='https' || empty($parts['host'])) throw new \RuntimeException('Webhook deve usar HTTPS.');
        if(isset($parts['user'])||isset($parts['pass'])) throw new \RuntimeException('Webhook não pode conter credenciais na URL.');
        $host=strtolower(rtrim($parts['host'],'.'));
        if($host==='localhost'||substr($host,-6)==='.local') throw new \RuntimeException('Destino privado não permitido.');
        $ips=[];
        if(filter_var($host,FILTER_VALIDATE_IP))$ips=[$host];
        else{
            $records=@dns_get_record($host,DNS_A|DNS_AAAA);
            foreach($records?:[] as $rec){if(!empty($rec['ip']))$ips[]=$rec['ip'];if(!empty($rec['ipv6']))$ips[]=$rec['ipv6'];}
        }
        if(!$ips) throw new \RuntimeException('DNS do webhook não pôde ser resolvido.');
        foreach($ips as $ip){
            if(filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)===false) throw new \RuntimeException('Destino privado ou reservado não permitido.');
        }
        $port=(int)($parts['port']??443);
        if($port!==443) throw new \RuntimeException('Webhook deve usar HTTPS na porta 443.');
        $ip=$ips[0];
        $resolveIp=strpos($ip,':')!==false ? '['.$ip.']' : $ip;
        return ['resolve'=>$host.':'.$port.':'.$resolveIp];
    }
}
