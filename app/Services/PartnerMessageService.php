<?php
namespace Services;

use Models\ParceiroApi;
use Models\TemplateMeta;
use Models\Conversa;

class PartnerMessageService
{
    private $parceiros;
    private $templates;
    private $conversas;

    public function __construct($parceiros=null,$templates=null,$conversas=null)
    {
        $this->parceiros=$parceiros ?: new ParceiroApi();
        $this->templates=$templates ?: new TemplateMeta();
        $this->conversas=$conversas ?: new Conversa();
    }

    public function enviar(array $parceiro,array $dados)
    {
        $clienteId=(int)($dados['client_id']??0);
        $metaId=(int)($dados['channel_id']??0);
        $to=preg_replace('/\D/','',(string)($dados['to']??''));
        $type=strtolower(trim((string)($dados['type']??'')));

        if($clienteId<=0 || $metaId<=0 || strlen($to)<10 || strlen($to)>15){
            throw new PartnerApiException('validation_error','Informe client_id, channel_id e to válidos.',422);
        }
        if(!in_array($type,['text','template','image','document','audio'],true)){
            throw new PartnerApiException('unsupported_message_type','Nesta versão, type deve ser text, template, image, document ou audio.',422);
        }

        $canal=$this->parceiros->buscarCanalAutorizado((int)$parceiro['PAR_ID'],$clienteId,$metaId);
        if(!$canal || ($canal['PAC_Status']??'')!=='ativo'){
            throw new PartnerApiException('channel_not_authorized','Canal não autorizado ou inativo para este Partner.',403);
        }
        if(strtolower((string)($canal['MTA_Status']??''))!=='conectado'){
            throw new PartnerApiException('channel_not_ready','O canal ainda não está conectado.',409);
        }

        $meta=new MetaService($metaId,$clienteId);
        $conversaId=(int)$this->conversas->buscarOuCriar($clienteId,$metaId,$to,null,false);

        if($type==='text'){
            $body=trim((string)($dados['text']['body']??''));
            if($body===''){ throw new PartnerApiException('validation_error','Informe text.body.',422); }
            if($this->tamanhoUtf8($body)>4096){ throw new PartnerApiException('validation_error','text.body excede o limite de 4096 caracteres.',422); }
            if(!$this->janelaAberta($conversaId)){
                throw new PartnerApiException('customer_care_window_closed','A janela de atendimento de 24 horas está fechada. Envie um template aprovado.',409);
            }
            $retorno=$meta->enviarTexto($to,$body);
            return $this->finalizar($retorno,$conversaId,'text',$body);
        }

        if(in_array($type,['image','document','audio'],true)){
            if(!$this->janelaAberta($conversaId)){
                throw new PartnerApiException('customer_care_window_closed','A janela de atendimento de 24 horas está fechada. Envie um template aprovado.',409);
            }
            $mediaId=trim((string)($dados[$type]['media_id']??''));
            if($mediaId===''){ throw new PartnerApiException('validation_error','Informe '.$type.'.media_id.',422); }
            $caption=trim((string)($dados[$type]['caption']??''));
            if($caption!=='' && $this->tamanhoUtf8($caption)>1024){ throw new PartnerApiException('validation_error',$type.'.caption excede o limite de 1024 caracteres.',422); }
            $filename=$type==='document' ? trim((string)($dados[$type]['filename']??'')) : '';
            if($filename!=='' && $this->tamanhoUtf8($filename)>255){ throw new PartnerApiException('validation_error','document.filename excede o limite de 255 caracteres.',422); }
            $retorno=$meta->enviarMidia($to,$type,$mediaId,$caption,$filename);
            return $this->finalizar($retorno,$conversaId,$type,$caption!==''?$caption:'['.strtoupper($type).']',[
                'media_id'=>$mediaId,
                'media_nome'=>$filename!==''?$filename:null
            ]);
        }

        $templateId=(int)($dados['template']['id']??0);
        if($templateId<=0){ throw new PartnerApiException('validation_error','Informe template.id.',422); }
        $template=$this->templates->buscarAprovadoParaEnvioPorCliente($templateId,$clienteId);
        if(!$template || (int)$template['MTA_ID']!==$metaId){
            throw new PartnerApiException('template_not_available','Template aprovado não encontrado para este cliente/canal.',422);
        }
        $variables=$dados['template']['variables']??[];
        if(!is_array($variables)){ throw new PartnerApiException('validation_error','template.variables deve ser um objeto ou array.',422); }
        if(count($variables)>100){ throw new PartnerApiException('validation_error','template.variables excede o limite de 100 variáveis.',422); }
        foreach($variables as $valor){
            if(is_array($valor) || is_object($valor) || $this->tamanhoUtf8((string)$valor)>1024){
                throw new PartnerApiException('validation_error','Cada variável de template deve ser escalar e ter no máximo 1024 caracteres.',422);
            }
        }
        $header=$dados['template']['header_media']??null;
        if($header!==null && !is_array($header)){ throw new PartnerApiException('validation_error','template.header_media deve ser um objeto.',422); }

        $retorno=$meta->enviarTemplate($to,$template,$variables,$header);
        return $this->finalizar($retorno,$conversaId,'template','Template: '.$template['TMP_Nome'],[
            'template_id'=>(int)$template['TMP_ID'],
            'template_name'=>$template['TMP_Nome'],
            'language'=>$template['TMP_Idioma']
        ]);
    }

    private function tamanhoUtf8($valor)
    {
        $valor=(string)$valor;
        return function_exists('mb_strlen') ? mb_strlen($valor,'UTF-8') : strlen($valor);
    }

    private function janelaAberta($conversaId)
    {
        $ultima=$this->conversas->ultimaMensagemRecebida($conversaId);
        if(!$ultima || empty($ultima['MSG_DataMensagem'])) return false;
        return time() <= strtotime($ultima['MSG_DataMensagem']) + 86400;
    }

    private function finalizar(array $retorno,$conversaId,$tipo,$texto,array $extra=[])
    {
        $messageId=$retorno['response']['messages'][0]['id'] ?? ($retorno['messages'][0]['id'] ?? null);
        if(!$messageId){
            $erro=$retorno['response']['error']['message'] ?? ($retorno['error']['message'] ?? 'A Meta recusou o envio.');
            throw new PartnerApiException('meta_send_failed',(string)$erro,502);
        }

        $localId=$this->conversas->salvarMensagem([
            'conversa_id'=>$conversaId,'direcao'=>'enviada','origem'=>'partner_api','tipo'=>$tipo,
            'texto'=>$texto,'message_id'=>$messageId,'status'=>'aguardando_confirmacao',
            'retorno'=>$retorno,'data_mensagem'=>date('Y-m-d H:i:s'),
            'media_id'=>$extra['media_id']??null,'media_nome'=>$extra['media_nome']??null
        ]);

        return array_merge([
            'message_id'=>(string)$messageId,
            'local_message_id'=>(int)$localId,
            'status'=>'accepted',
            'type'=>$tipo
        ],$extra);
    }
}

class PartnerApiException extends \RuntimeException
{
    private $apiCode;
    private $httpStatus;
    public function __construct($apiCode,$message,$httpStatus=400)
    {
        parent::__construct($message);
        $this->apiCode=(string)$apiCode;
        $this->httpStatus=(int)$httpStatus;
    }
    public function apiCode(){return $this->apiCode;}
    public function httpStatus(){return $this->httpStatus;}
}
