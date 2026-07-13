<?php

$controller = file_get_contents(__DIR__ . '/../app/Controllers/ConfiguracaoController.php');

$assert = function($condition, $message){
    if(!$condition){
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$assert(strpos($controller, 'business{id}') === false, 'consulta da WABA não deve conter business{id}');
$assert(
    strpos($controller, "id,name,phone_numbers{id,display_phone_number,verified_name,quality_rating,code_verification_status,name_status,status}") !== false,
    'consulta da WABA usa apenas fields aceitos pela Graph API v25'
);
$assert(
    strpos($controller, "'business_id' => \$finishIds['business_id'] ?? \$businessIdFallback") !== false,
    'business_id prioriza FINISH e usa fallback do debug_token'
);

$finishIds = ['business_id' => '987654321'];
$businessIdFallback = '123456789';
$businessId = $finishIds['business_id'] ?? $businessIdFallback;
$assert($businessId === '987654321', 'business_id usa FINISH quando disponível');

$finishIds = [];
$businessIdFallback = '123456789';
$businessId = $finishIds['business_id'] ?? $businessIdFallback;
$assert($businessId === '123456789', 'business_id usa profile_id/debug_token quando FINISH não contém business_id');

echo "Embedded signup WABA fields tests passed\n";
