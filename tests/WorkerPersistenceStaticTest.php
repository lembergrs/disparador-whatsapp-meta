<?php

$root = dirname(__DIR__);
$campanha = file_get_contents($root . '/app/Services/CampanhaQueueService.php');
$manual = file_get_contents($root . '/app/Services/DisparoManualQueueService.php');
$migration = file_get_contents($root . '/database/migrations/20260714_add_worker_retry_fields.sql');

function assertContains($haystack, $needle, $message)
{
    if(strpos($haystack, $needle) === false){
        fwrite(STDERR, "Assertion failed: {$message}\nMissing: {$needle}\n");
        exit(1);
    }
}


function assertNotContains($haystack, $needle, $message)
{
    if(strpos($haystack, $needle) !== false){
        fwrite(STDERR, "Assertion failed: {$message}\nUnexpected: {$needle}\n");
        exit(1);
    }
}

function assertMatches($pattern, $subject, $message)
{
    if(!preg_match($pattern, $subject)){
        fwrite(STDERR, "Assertion failed: {$message}\nPattern: {$pattern}\n");
        exit(1);
    }
}

assertContains($migration, 'FIL_WorkerId', 'migration adds campaign worker id');
assertContains($migration, 'FIL_ProximaTentativa', 'migration adds campaign next attempt');
assertContains($migration, 'DMI_WorkerId', 'migration adds manual worker id');
assertContains($migration, 'DMI_Tentativas', 'migration adds manual attempts');
assertContains($migration, 'idx_fila_envio_status_proxima', 'migration adds campaign retry index');
assertContains($migration, 'idx_dmi_status_proxima', 'migration adds manual retry index');

assertMatches("/FIL_Status = 'pendente'.*FIL_ProximaTentativa IS NULL OR f\.FIL_ProximaTentativa <= NOW\(\)/s", $campanha, 'campaign query only loads eligible pending items');
assertMatches("/UPDATE fila_envio.*FIL_Status = 'processando'.*FIL_WorkerId = \?.*FIL_DataReserva = NOW\(\).*FIL_Tentativas = FIL_Tentativas \+ 1.*WHERE FIL_ID = \?.*FIL_Status = 'pendente'.*FIL_ProximaTentativa IS NULL OR FIL_ProximaTentativa <= NOW\(\)/s", $campanha, 'campaign reservation is conditional and persistent');
assertContains($campanha, 'return $stmt->rowCount() === 1;', 'campaign send depends on successful reservation row count');
assertContains($campanha, "FIL_Status = 'pendente',", 'campaign temporary failures return to pending');
assertContains($campanha, 'FIL_ProximaTentativa = {$proximaTentativaSql}', 'campaign temporary failures schedule next attempt');
assertContains($campanha, 'max_attempts', 'campaign max attempts becomes definitive error');
assertContains($campanha, 'FIL_Tentativas = GREATEST(FIL_Tentativas - 1, 0)', 'campaign temporary block compensates attempts');
assertContains($campanha, "FIL_MessageId IS NULL", 'campaign recovery excludes items with message id');
assertContains($campanha, "FIL_UltimoErroTipo = 'recuperado_timeout'", 'campaign recovery marks timeout');
assertContains($campanha, 'WorkerRetryPolicyService::ERRO_PERSISTENCIA_POS_ENVIO', 'campaign handles post-send persistence failure');
assertContains($campanha, "FIL_MessageId = COALESCE(FIL_MessageId, ?)", 'campaign emergency update persists message id');
assertMatches("/FIL_Status IN \('pendente','processando'\).*FIL_ProximaTentativa IS NOT NULL AND FIL_ProximaTentativa > NOW\(\)/s", $campanha, 'campaign finalization waits for pending processing or future retry');
assertContains($campanha, "FIL_Status = 'enviado'", 'campaign success persists valid fila_envio status');
assertNotContains($campanha, "FIL_Status = 'aguardando_confirmacao'", 'campaign queue does not persist invalid aguardando_confirmacao enum');
assertContains($campanha, '$this->consumo->registrarMensagem', 'campaign consumption increments on success path');

assertMatches("/DMI_Status = 'pendente'.*DMI_ProximaTentativa IS NULL OR i\.DMI_ProximaTentativa <= NOW\(\)/s", $manual, 'manual worker query only loads eligible pending items');
assertMatches("/UPDATE disparo_manual_itens.*DMI_Status = 'processando'.*DMI_WorkerId = \?.*DMI_DataReserva = NOW\(\).*DMI_Tentativas = DMI_Tentativas \+ 1.*WHERE DMI_ID = \?.*DMI_Status = 'pendente'.*DMI_ProximaTentativa IS NULL OR DMI_ProximaTentativa <= NOW\(\)/s", $manual, 'manual worker reservation is conditional and persistent');
assertContains($manual, "DMI_Status = 'pendente',", 'manual temporary failures return to pending');
assertContains($manual, 'DMI_ProximaTentativa = {$proximaTentativaSql}', 'manual temporary failures schedule next attempt');
assertContains($manual, 'DMI_Tentativas = GREATEST(DMI_Tentativas - 1, 0)', 'manual temporary block compensates attempts');
assertContains($manual, "DMI_MessageId IS NULL", 'manual recovery excludes items with message id');
assertContains($manual, 'WorkerRetryPolicyService::ERRO_PERSISTENCIA_POS_ENVIO', 'manual handles post-send persistence failure');
assertContains($manual, '$this->consumo->registrarMensagem', 'manual consumption increments on success path');

fwrite(STDOUT, "Worker persistence static checks passed\n");
