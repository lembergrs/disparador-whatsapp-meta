<?php

namespace Services;

use Exception;

class EmbeddedSignupFlowService
{
    private $graphRequest;
    private $appId;

    public function __construct(callable $graphRequest, $appId)
    {
        $this->graphRequest = $graphRequest;
        $this->appId = (string) $appId;
    }

    public function validarDebugToken(array $debug)
    {
        $data = $debug['data'] ?? [];

        if(empty($data['is_valid'])){
            throw new Exception('Token retornado pela Meta inválido.');
        }

        if((string) ($data['app_id'] ?? '') !== $this->appId){
            throw new Exception('Token retornado não pertence ao app configurado.');
        }

        if(!empty($data['expires_at']) && (int) $data['expires_at'] < time()){
            throw new Exception('Token retornado pela Meta expirado.');
        }

        $scopes = $this->permissoesConcedidas($debug);
        foreach(['whatsapp_business_management', 'whatsapp_business_messaging'] as $required){
            if(!in_array($required, $scopes, true)){
                throw new Exception('Permissão obrigatória ausente: ' . $required);
            }
        }

        return $debug;
    }

    public function permissoesConcedidas(array $debug)
    {
        $data = $debug['data'] ?? [];
        $scopes = [];

        foreach(($data['scopes'] ?? []) as $scope){
            if(is_string($scope) && $scope !== ''){
                $scopes[] = $scope;
            }
        }

        foreach(($data['granular_scopes'] ?? []) as $scope){
            if(!empty($scope['scope'])){
                $scopes[] = (string) $scope['scope'];
            }
        }

        return array_values(array_unique($scopes));
    }

    public function extrairWabaIdsDoDebugToken(array $debug)
    {
        $ids = [];
        $businessId = $debug['data']['profile_id'] ?? null;

        foreach(($debug['data']['granular_scopes'] ?? []) as $scope){
            if(($scope['scope'] ?? '') !== 'whatsapp_business_management'){
                continue;
            }

            foreach(($scope['target_ids'] ?? []) as $targetId){
                $ids[] = (string) $targetId;
            }
        }

        return [array_values(array_unique($ids)), $businessId];
    }


    public function registrarPhoneNumber($phoneNumberId, $pin, $accessToken)
    {
        $resposta = call_user_func(
            $this->graphRequest,
            $phoneNumberId . '/register',
            [
                'messaging_product' => 'whatsapp',
                'pin' => $pin
            ],
            $accessToken,
            'POST'
        );

        return $this->validarRespostaRegistroPhoneNumber($resposta);
    }

    public function validarRespostaRegistroPhoneNumber(array $resposta)
    {
        if(($resposta['success'] ?? null) !== true){
            throw new Exception('A Meta não confirmou o registro operacional do número.');
        }

        return true;
    }

    public function assinarAppNaWaba($wabaId, $accessToken)
    {
        $resposta = call_user_func(
            $this->graphRequest,
            $wabaId . '/subscribed_apps',
            [],
            $accessToken,
            'POST'
        );

        return $this->validarRespostaAssinatura($resposta);
    }

    public function validarRespostaAssinatura(array $resposta)
    {
        if(($resposta['success'] ?? null) !== true){
            throw new Exception('A Meta não confirmou a assinatura do app na WABA.');
        }

        return true;
    }

    public function definirStatusConexao(array $dadosWhatsApp)
    {
        $valoresAcao = ['PENDING', 'PENDING_REVIEW', 'FLAGGED', 'REJECTED', 'DISCONNECTED', 'UNVERIFIED', 'NOT_VERIFIED', 'EXPIRED'];

        foreach(['operational_status', 'code_verification_status', 'name_status'] as $campo){
            $valor = strtoupper(trim((string) ($dadosWhatsApp[$campo] ?? '')));
            if($valor === ''){
                continue;
            }

            if(in_array($valor, $valoresAcao, true)){
                return 'requer_acao';
            }
        }

        return 'conectado';
    }

    public function definirStatusCoexistencia(array $dadosWhatsApp)
    {
        $operationalStatus = strtoupper(trim((string) ($dadosWhatsApp['operational_status'] ?? '')));
        if($operationalStatus !== 'CONNECTED'){
            return 'requer_acao';
        }

        return $this->definirStatusConexao($dadosWhatsApp);
    }

    public function iniciarSincronizacaoCoexistence(array $conta, $repository)
    {
        $service = new MetaCoexistenceSyncService($this->graphRequest, $repository);
        return $service->iniciar($conta);
    }
}
