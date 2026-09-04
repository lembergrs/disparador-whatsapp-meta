<?php
// Valida o dialeto MySQL em tabelas TEMPORARY da conexão; não altera dados reais.
require __DIR__ . '/fixtures/OnboardingDashboardFixture.php';
require dirname(__DIR__) . '/config/env.php';
require dirname(__DIR__) . '/config/config.php';

try{
    $db = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
}catch(Throwable $e){
    fwrite(STDERR, "FAIL: conexão MySQL indisponível para o teste isolado.\n"); exit(1);
}

$tables = ['meta_contas','templates_meta','conversas','conversa_mensagens','disparos','disparo_manual_lotes','disparo_manual_itens'];
try{
    $db->exec(str_replace('CREATE TABLE ', 'CREATE TEMPORARY TABLE ', onboardingSchema()));
    onboardingAccount($db,1,'conectado',null); onboardingAccount($db,2);
    onboardingTemplate($db,'APPROVED',2);
    onboardingInsert($db,'conversas',['CVS_ID'=>2,'CLI_ID'=>10,'MTA_ID'=>2]);
    onboardingInsert($db,'conversa_mensagens',['MSG_ID'=>1,'CVS_ID'=>2,'MSG_Status'=>'delivered','MSG_MetaMessageId'=>'wamid-1','MSG_DataMensagem'=>'2026-09-04 10:00:00']);
    $connection = new OnboardingSelectOnlyConnection($db);
    $service = new \Services\OnboardingChecklistService(new \Models\OnboardingReadModel($connection));
    $state = $service->calcular(10,onboardingAccess(),1);
    onboardingAssert($state['proxima']['id'] === 'pagamento_meta' && !$state['concluido'], 'MySQL misturou contas.');
    $state = $service->calcular(10,onboardingAccess(),2);
    onboardingAssert($state['concluido'], 'MySQL não reconheceu entrega.');
    $db->exec("UPDATE conversa_mensagens SET MSG_Status='failed'");
    onboardingInsert($db,'disparo_manual_lotes',['DML_ID'=>1,'CLI_ID'=>10,'MTA_ID'=>2]);
    onboardingInsert($db,'disparo_manual_itens',['DMI_ID'=>1,'DML_ID'=>1,'CLI_ID'=>10,'DMI_Status'=>'aguardando_confirmacao','DMI_MessageId'=>'wamid-1','DMI_DataCadastro'=>'2026-09-04 10:00:00']);
    $state = $service->calcular(10,onboardingAccess(),2);
    onboardingAssert(!$state['concluido'] && $state['proxima']['id']==='envio_failed', 'MySQL deve priorizar confirmação de falha.');
    echo 'OnboardingReadModelMysqlTest OK: ' . count($connection->queries) . " SELECTs em tabelas temporárias.\n";
}finally{
    foreach(array_reverse($tables) as $table) $db->exec('DROP TEMPORARY TABLE IF EXISTS ' . $table);
}
