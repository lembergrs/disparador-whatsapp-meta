<?php

namespace Controllers;

use Core\Controller;
use Models\ParceiroApi;
use Models\ParceiroApiIdempotencia;
use Models\Conversa;
use Services\MetaMediaService;
use Services\PartnerApiAuthService;
use Services\PartnerMessageService;
use Services\PartnerApiException;

class ApiV1Controller extends Controller
{
    private $parceiroModel;
    private $authService;

    public function __construct()
    {
        $this->parceiroModel = new ParceiroApi();
        $this->authService = new PartnerApiAuthService($this->parceiroModel);
    }

    public function status()
    {
        if(($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET'){
            $this->json(['error'=>['code'=>'method_not_allowed','message'=>'Método não permitido.']], 405);
        }

        $parceiro = $this->authService->autenticar();
        if(!$parceiro){
            header('WWW-Authenticate: Bearer');
            $this->json(['error'=>['code'=>'unauthorized','message'=>'API key inválida ou ausente.']], 401);
        }

        $canais = $this->parceiroModel->listarCanaisAutorizados($parceiro['PAR_ID']);

        $this->json([
            'data'=>[
                'api'=>'disparador-partner',
                'version'=>'v1',
                'partner'=>[
                    'id'=>(int)$parceiro['PAR_ID'],
                    'identifier'=>$parceiro['PAR_Identificador'],
                    'name'=>$parceiro['PAR_Nome']
                ],
                'channels'=>array_map(function($canal){
                    return [
                        'client_id'=>(int)$canal['CLI_ID'],
                        'channel_id'=>(int)$canal['MTA_ID'],
                        'external_id'=>$canal['PAC_IdentificadorExterno'] ?: null,
                        'name'=>$canal['MTA_Nome'],
                        'phone'=>$canal['MTA_NumeroTelefone'],
                        'status'=>$canal['MTA_Status'],
                        'onboarding_type'=>$canal['MTA_OnboardingType'] ?: 'traditional'
                    ];
                }, $canais)
            ]
        ]);
    }

    public function messages()
    {
        if(($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST'){
            header('Allow: POST');
            $this->json(['error'=>['code'=>'method_not_allowed','message'=>'Método não permitido.']],405);
        }

        $parceiro=$this->authService->autenticar();
        if(!$parceiro){
            header('WWW-Authenticate: Bearer');
            $this->json(['error'=>['code'=>'unauthorized','message'=>'API key inválida ou ausente.']],401);
        }

        $raw=file_get_contents('php://input');
        $dados=json_decode((string)$raw,true);
        if(!is_array($dados)){
            $this->json(['error'=>['code'=>'invalid_json','message'=>'Envie um corpo JSON válido.']],400);
        }

        $idempotencyKey=trim((string)($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ''));
        if($idempotencyKey==='' || strlen($idempotencyKey)<8 || strlen($idempotencyKey)>120 || !preg_match('/^[A-Za-z0-9._:-]+$/',$idempotencyKey)){
            $this->json(['error'=>['code'=>'invalid_idempotency_key','message'=>'Envie Idempotency-Key com 8 a 120 caracteres usando letras, números, ponto, hífen, sublinhado ou dois-pontos.']],400);
        }

        $requestHash=hash('sha256',(string)$raw);
        $idempotencias=new ParceiroApiIdempotencia();
        $reserva=$idempotencias->iniciar((int)$parceiro['PAR_ID'],$idempotencyKey,$requestHash);
        if(!$reserva['novo']){
            $registro=$reserva['registro'];
            if(!$registro || !hash_equals((string)$registro['PAI_RequestHash'],$requestHash)){
                $this->json(['error'=>['code'=>'idempotency_conflict','message'=>'Esta Idempotency-Key já foi usada com outro corpo de requisição.']],409);
            }
            if(($registro['PAI_Status']??'')==='concluido' && !empty($registro['PAI_Resposta'])){
                $payload=json_decode((string)$registro['PAI_Resposta'],true);
                if(is_array($payload)){
                    header('Idempotency-Replayed: true');
                    $this->json($payload,(int)($registro['PAI_HttpStatus']?:202));
                }
            }
            $this->json(['error'=>['code'=>'request_in_progress','message'=>'Uma requisição com esta Idempotency-Key ainda está em processamento.']],409);
        }

        try{
            $resultado=(new PartnerMessageService($this->parceiroModel))->enviar($parceiro,$dados);
            $payload=['data'=>$resultado];
            $idempotencias->concluir((int)$parceiro['PAR_ID'],$idempotencyKey,202,$payload);
            $this->json($payload,202);
        }catch(PartnerApiException $e){
            $payload=['error'=>['code'=>$e->apiCode(),'message'=>$e->getMessage()]];
            $idempotencias->concluir((int)$parceiro['PAR_ID'],$idempotencyKey,$e->httpStatus(),$payload);
            $this->json($payload,$e->httpStatus());
        }catch(\Throwable $e){
            $idempotencias->removerProcessando((int)$parceiro['PAR_ID'],$idempotencyKey);
            error_log('Partner API messages: '.$e->getMessage());
            $this->json(['error'=>['code'=>'internal_error','message'=>'Não foi possível processar o envio.']],500);
        }
    }

    public function media()
    {
        $method=$_SERVER['REQUEST_METHOD'] ?? 'GET';
        if(!in_array($method,['GET','POST'],true)){
            header('Allow: GET, POST');
            $this->json(['error'=>['code'=>'method_not_allowed','message'=>'Método não permitido.']],405);
        }

        $parceiro=$this->authService->autenticar();
        if(!$parceiro){
            header('WWW-Authenticate: Bearer');
            $this->json(['error'=>['code'=>'unauthorized','message'=>'API key inválida ou ausente.']],401);
        }

        if($method==='POST'){
            $clienteId=(int)($_POST['client_id']??0);
            $metaId=(int)($_POST['channel_id']??0);
            $tipo=strtolower(trim((string)($_POST['type']??'')));
            $map=['image'=>'IMAGE','document'=>'DOCUMENT'];
            if($clienteId<=0 || $metaId<=0 || !isset($map[$tipo]) || empty($_FILES['file'])){
                $this->json(['error'=>['code'=>'validation_error','message'=>'Informe client_id, channel_id, type (image ou document) e file.']],422);
            }
            $canal=$this->parceiroModel->buscarCanalAutorizado((int)$parceiro['PAR_ID'],$clienteId,$metaId);
            if(!$canal || ($canal['PAC_Status']??'')!=='ativo'){
                $this->json(['error'=>['code'=>'channel_not_authorized','message'=>'Canal não autorizado ou inativo para este Partner.']],403);
            }
            try{
                $upload=(new MetaMediaService($metaId,$clienteId))->uploadMensagemMedia($_FILES['file'],$map[$tipo]);
                $this->json(['data'=>[
                    'media_id'=>(string)$upload['media_id'],
                    'type'=>$tipo,
                    'mime_type'=>$upload['mime']??null,
                    'filename'=>$upload['nome_original']??null,
                    'size'=>(int)($upload['tamanho']??0)
                ]],201);
            }catch(\Throwable $e){
                $this->json(['error'=>['code'=>'media_upload_failed','message'=>$e->getMessage()]],422);
            }
        }

        $mensagemId=(int)($_GET['id']??0);
        if($mensagemId<=0){
            $this->json(['error'=>['code'=>'media_not_found','message'=>'Mídia não encontrada.']],404);
        }

        $canais=$this->parceiroModel->listarCanaisAutorizados((int)$parceiro['PAR_ID']);
        $mensagem=null;
        foreach($canais as $canal){
            $mensagem=(new Conversa())->buscarMensagemPartner($mensagemId,(int)$canal['CLI_ID'],(int)$canal['MTA_ID']);
            if($mensagem) break;
        }

        $tiposPermitidos=['audio','image','document'];
        if(!$mensagem || empty($mensagem['MSG_MediaId']) || !in_array(strtolower((string)($mensagem['MSG_Tipo']??'')),$tiposPermitidos,true)){
            $this->json(['error'=>['code'=>'media_not_found','message'=>'Mídia não encontrada.']],404);
        }

        try{
            $media=(new MetaMediaService((int)$mensagem['MTA_ID'],(int)$mensagem['CLI_ID']))->obterMidiaMensagem(
                $mensagem['MSG_MediaId'],
                $mensagem['MSG_MediaMimeType']??null,
                $mensagem['MSG_MediaSha256']??null
            );
            $mime=trim((string)($media['mime']??'')) ?: 'application/octet-stream';
            $nome=trim((string)($mensagem['MSG_MediaNome']??''));
            if($nome==='') $nome='media-'.(int)$mensagem['MSG_ID'];
            $nome=preg_replace('/[^A-Za-z0-9._-]+/','_',basename($nome));

            header('Content-Type: '.$mime);
            header('Content-Length: '.filesize($media['arquivo']));
            header('Content-Disposition: attachment; filename="'.$nome.'"');
            header('Cache-Control: private, no-store');
            header('X-Content-Type-Options: nosniff');
            readfile($media['arquivo']);
            exit;
        }catch(\Throwable $e){
            error_log('Partner API media: '.$e->getMessage());
            $this->json(['error'=>['code'=>'media_unavailable','message'=>'Não foi possível obter a mídia.']],502);
        }
    }

    private function json(array $payload, $status = 200)
    {
        http_response_code((int)$status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
