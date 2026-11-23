<?php
require_once __DIR__.'/../app/Services/MetaBillingConsumptionReversalService.php';
use Services\MetaBillingConsumptionReversalService;
foreach ([131042,131049,131026,131050] as $codigo) {
    if (!MetaBillingConsumptionReversalService::ehFalhaElegivel(['codigo'=>$codigo])) throw new RuntimeException('Código elegível: '.$codigo);
}
if (MetaBillingConsumptionReversalService::ehFalhaElegivel(['codigo'=>999999])) throw new RuntimeException('Código desconhecido');
echo "MetaBillingConsumptionReversalServiceTest OK\n";
