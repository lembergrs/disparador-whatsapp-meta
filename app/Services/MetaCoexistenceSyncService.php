<?php

namespace Services;

class MetaCoexistenceSyncService
{
    private $graphRequest;
    private $repository;

    public function __construct(callable $graphRequest, $repository)
    {
        $this->graphRequest = $graphRequest;
        $this->repository = $repository;
    }

    public function iniciar(array $conta)
    {
        if(EmbeddedSignupOnboardingMode::normalize($conta['MTA_OnboardingType'] ?? null) !== EmbeddedSignupOnboardingMode::COEXISTENCE){
            return ['iniciado'=>false, 'motivo'=>'traditional'];
        }

        $resultado = ['iniciado'=>true, 'contact_request_id'=>null, 'history_request_id'=>null];
        foreach(['smb_app_state_sync'=>'contact', 'history'=>'history'] as $syncType=>$tipo){
            if(!$this->repository->reservarSyncUmaVez((int) $conta['MTA_ID'], $tipo)){
                $resultado[$tipo . '_request_id'] = $conta[$tipo === 'contact' ? 'MTA_ContactSyncRequestId' : 'MTA_HistorySyncRequestId'] ?? null;
                if($tipo === 'contact' && empty($resultado['contact_request_id'])){
                    $resultado['history_deferred'] = true;
                    break;
                }
                continue;
            }

            try{
                $resposta = call_user_func($this->graphRequest, $conta['MTA_PhoneNumberId'] . '/smb_app_data', [
                    'messaging_product'=>'whatsapp',
                    'sync_type'=>$syncType
                ], $conta['MTA_Token'], 'POST');
                $requestId = trim((string) ($resposta['request_id'] ?? ''));
                if($requestId === '') throw new \RuntimeException('A Meta não retornou request_id para o sync Coexistence.');
                $this->repository->confirmarSyncSolicitado((int) $conta['MTA_ID'], $tipo, $requestId);
                $resultado[$tipo . '_request_id'] = $requestId;
            }catch(\Throwable $e){
                $this->repository->marcarSyncFalho((int) $conta['MTA_ID'], $tipo);
                throw $e;
            }
        }

        return $resultado;
    }
}
