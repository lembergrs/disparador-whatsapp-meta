<?php

namespace Services;

class MetaCoexistenceLifecycleService
{
    private $repository;
    private $logger;

    public function __construct($repository, callable $logger = null)
    {
        $this->repository = $repository;
        $this->logger = $logger;
    }

    public function processar(array $value, array $conta)
    {
        if(EmbeddedSignupOnboardingMode::normalize($conta['MTA_OnboardingType'] ?? null) !== EmbeddedSignupOnboardingMode::COEXISTENCE) return false;
        $evento = strtoupper(trim((string)($value['event'] ?? ($value['account_update']['event'] ?? ''))));
        if(!in_array($evento, ['PARTNER_REMOVED','ACCOUNT_OFFBOARDED','ACCOUNT_RECONNECTED'], true)) return false;
        $detalhes = $value['account_update'] ?? $value;
        $ok = $this->repository->atualizarLifecycleCoexistence((int)$conta['MTA_ID'], $evento, [
            'reason'=>$detalhes['reason'] ?? null,
            'initiated_by'=>$detalhes['initiated_by'] ?? null
        ]);
        if($this->logger) call_user_func($this->logger, 'account_update_coexistence', [
            'conta_id'=>(int)$conta['MTA_ID'], 'event'=>$evento, 'updated'=>(bool)$ok
        ]);
        return $ok;
    }
}
