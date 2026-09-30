<?php

namespace Controllers;

use Core\Controller;
use Models\ParceiroApi;
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

        try{
            $resultado=(new PartnerMessageService($this->parceiroModel))->enviar($parceiro,$dados);
            $this->json(['data'=>$resultado],202);
        }catch(PartnerApiException $e){
            $this->json(['error'=>['code'=>$e->apiCode(),'message'=>$e->getMessage()]],$e->httpStatus());
        }catch(\Throwable $e){
            error_log('Partner API messages: '.$e->getMessage());
            $this->json(['error'=>['code'=>'internal_error','message'=>'Não foi possível processar o envio.']],500);
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
