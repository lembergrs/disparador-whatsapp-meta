<?php
namespace Services;

use Models\ParceiroWebhookEvento;

class PartnerWebhookEventService
{
    private $eventos;
    public function __construct(ParceiroWebhookEvento $eventos=null){$this->eventos=$eventos ?: new ParceiroWebhookEvento();}

    public function inbound(array $metaConta,array $dados,array $persistencia)
    {
        if(empty($persistencia['created'])) return 0;
        $tipo=$dados['tipo']==='reaction' ? 'message.reaction' : 'message.received';
        $data=[
            'message_id'=>$dados['message_id'],
            'local_message_id'=>(int)($persistencia['id']??0),
            'from'=>$dados['participante'],
            'type'=>$dados['tipo'],
            'text'=>$dados['texto'],
            'timestamp'=>$this->iso($dados['data_mensagem']??null)
        ];
        if(!empty($dados['media_id'])) $data['media']=['id'=>$dados['media_id'],'mime_type'=>$dados['media_mime_type']??null,'filename'=>$dados['media_nome']??null];
        if($dados['tipo']==='reaction') $data['reaction']=['message_id'=>$dados['reacao_message_id']??null,'emoji'=>$dados['reacao_emoji']??null];
        return $this->enfileirarCanal((int)$metaConta['CLI_ID'],(int)$metaConta['MTA_ID'],$tipo,$data,'in:'.$dados['message_id']);
    }

    public function status(array $metaConta,array $status)
    {
        $messageId=trim((string)($status['id']??''));
        $normal=MensagemStatusService::normalizar($status['status']??'');
        if($messageId==='' || !in_array($normal,['sent','delivered','read','failed'],true)) return 0;
        $msg=$this->eventos->mensagemPartnerPorMetaId((int)$metaConta['MTA_ID'],$messageId);
        if(!$msg || ($msg['MSG_Origem']??'')!=='partner_api') return 0;
        $tipo='message.'.$normal;
        $data=['message_id'=>$messageId,'local_message_id'=>(int)$msg['MSG_ID'],'status'=>$normal,'timestamp'=>$this->isoTimestamp($status['timestamp']??null)];
        if($normal==='failed'){
            $erro=$status['errors'][0]??[];
            $data['error']=['code'=>$erro['code']??null,'message'=>$erro['error_data']['details']??$erro['message']??$erro['title']??null];
        }
        return $this->enfileirarCanal((int)$metaConta['CLI_ID'],(int)$metaConta['MTA_ID'],$tipo,$data,'st:'.$messageId.':'.$normal);
    }

    private function enfileirarCanal($clienteId,$metaId,$tipo,array $data,$dedupe)
    {
        $total=0;
        foreach($this->eventos->parceirosDoCanal($clienteId,$metaId) as $p){
            $habilitados=json_decode((string)($p['PAR_WebhookEventos']??'[]'),true);
            if(!is_array($habilitados)||!in_array($tipo,$habilitados,true)) continue;
            $eventId='evt_'.substr(hash('sha256',(int)$p['PAR_ID'].'|'.$dedupe),0,40);
            $payload=[
                'event_id'=>$eventId,
                'event'=>$tipo,
                'created_at'=>date(DATE_ATOM),
                'data'=>array_merge(['client_id'=>(int)$clienteId,'channel_id'=>(int)$metaId],$data)
            ];
            $r=$this->eventos->enfileirar($eventId,(int)$p['PAR_ID'],$clienteId,$metaId,$tipo,$payload);
            if(!empty($r['criado']))$total++;
        }
        return $total;
    }

    private function iso($data){$t=$data?strtotime((string)$data):false;return date(DATE_ATOM,$t?:time());}
    private function isoTimestamp($ts){return is_numeric($ts)&&$ts>0?date(DATE_ATOM,(int)$ts):date(DATE_ATOM);}
}
