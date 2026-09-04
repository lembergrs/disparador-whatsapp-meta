<?php
require __DIR__ . '/fixtures/OnboardingDashboardFixture.php';

$cases = 0;
$check = function($state, $next, $done = false) use (&$cases){
    onboardingAssert(($state['proxima']['id'] ?? null) === $next, 'Próxima etapa incorreta: ' . json_encode($state['proxima'], JSON_UNESCAPED_UNICODE));
    onboardingAssert($state['concluido'] === $done, 'Marco de ativação incorreto.');
    $cases++;
};

// A: cliente novo, somente SELECT, sem acesso aos módulos operacionais.
$db = onboardingDb();
$check(onboardingCalculate($db, onboardingAccess(true)), 'conexao_iniciar');

// B/C/D: ativo não equivale a conectado; NULL e pendente exigem declaração.
foreach(['pendente_registro'=>'conexao_registro', 'erro_registro'=>'conexao_erro', 'requer_acao'=>'conexao_acao', 'desconectado'=>'conexao_reconectar', 'erro'=>'conexao_reconectar'] as $status=>$next){
    $db = onboardingDb(); onboardingAccount($db, 1, $status, null);
    $state = onboardingCalculate($db, onboardingAccess(true));
    $check($state, $next);
    onboardingAssert(!$state['itens'][1]['done'], 'Conta apenas ativa não pode concluir conexão.');
}
foreach([null, 'pendente_confirmacao'] as $payment){
    $db = onboardingDb(); onboardingAccount($db, 1, 'conectado', $payment);
    $state = onboardingCalculate($db); $check($state, 'pagamento_meta');
    onboardingAssert($state['itens'][1]['done'], 'Conexão operacional deve concluir etapa.');
}

// E/F/G/H/I: template aprovado preexistente supera rejeitados e pendentes.
$db = onboardingDb(); onboardingAccount($db); $check(onboardingCalculate($db), 'template_criar');
foreach(['PENDING'=>'template_pending', 'REJECTED'=>'template_rejected', 'PAUSED'=>'template_indisponivel', 'DISABLED'=>'template_indisponivel', 'APPROVED'=>'primeiro_envio'] as $status=>$next){
    $db = onboardingDb(); onboardingAccount($db); onboardingTemplate($db, $status);
    $check(onboardingCalculate($db), $next);
}
$db = onboardingDb(); onboardingAccount($db);
foreach(['REJECTED','PENDING','APPROVED'] as $status) onboardingTemplate($db, $status);
$state = onboardingCalculate($db); $check($state, 'primeiro_envio');
onboardingAssert($state['itens'][3]['done'] && $state['itens'][4]['done'], 'Template já aprovado dispensa criação.');
$db->exec("UPDATE templates_meta SET TMP_Ativo='N'");
$check(onboardingCalculate($db), 'template_criar');

// J/K/L/M/N: erro e processamento nunca ativam; entrega/leitura sim, sem tabelas de contatos/listas/campanhas.
foreach(['failed'=>'envio_failed', 'erro'=>'envio_failed', 'aguardando_confirmacao'=>'envio_accepted', 'sent'=>'envio_sent', 'enviado'=>'envio_sent', 'processing'=>'envio_processing', 'delivered'=>null, 'entregue'=>null, 'read'=>null, 'lido'=>null] as $status=>$next){
    $db = onboardingDb(); onboardingAccount($db); onboardingTemplate($db); onboardingMessage($db, $status);
    $state = onboardingCalculate($db); $check($state, $next, $next === null);
    if($next === null) onboardingAssert($state['percentual'] === 100 && $state['opcionais'], 'Entrega conclui sem contatos e campanha.');
}
$db = onboardingDb(); onboardingAccount($db); onboardingTemplate($db);
onboardingInsert($db, 'disparos', ['CLI_ID'=>10, 'MTA_ID'=>1, 'DSP_Status'=>'failed']);
$check(onboardingCalculate($db), 'envio_failed');

// Lote vazio não é envio. Item pendente é processamento; espelho failed supera aceite antigo.
$db = onboardingDb(); onboardingAccount($db); onboardingTemplate($db);
onboardingInsert($db, 'disparo_manual_lotes', ['DML_ID'=>1,'CLI_ID'=>10,'MTA_ID'=>1]);
$check(onboardingCalculate($db), 'primeiro_envio');
onboardingInsert($db, 'disparo_manual_itens', ['DMI_ID'=>1,'DML_ID'=>1,'CLI_ID'=>10,'DMI_Status'=>'pendente','DMI_DataCadastro'=>'2026-09-04 10:00:00']);
$check(onboardingCalculate($db), 'envio_processing');
$db->exec("UPDATE disparo_manual_itens SET DMI_MessageId='wamid-1',DMI_Status='aguardando_confirmacao'");
$check(onboardingCalculate($db), 'envio_accepted');
onboardingMessage($db, 'failed');
$state = onboardingCalculate($db); $check($state, 'envio_failed');
onboardingAssert($state['proxima']['url'] === 'disparo/historico', 'Envio manual mantém destino do histórico manual.');
onboardingInsert($db, 'disparo_manual_itens', ['DMI_ID'=>2,'DML_ID'=>1,'CLI_ID'=>10,'DMI_Status'=>'pendente','DMI_DataCadastro'=>'2026-09-04 11:00:00']);
$check(onboardingCalculate($db), 'envio_processing');

// O: três contas com evidências separadas. Consulta explícita não herda outras contas/clientes.
$db = onboardingDb();
onboardingAccount($db, 1, 'conectado', null);
onboardingAccount($db, 2, 'pendente_registro', 'confirmado_cliente');
onboardingAccount($db, 3, 'pendente_registro', null);
onboardingTemplate($db, 'APPROVED', 3); onboardingMessage($db, 'delivered', 3);
onboardingAccount($db, 99, 'conectado', 'confirmado_cliente', 99); onboardingTemplate($db, 'APPROVED', 99);
onboardingMessage($db, 'delivered', 99, 2, 'api', 'enviada', 99);
$state = onboardingCalculate($db, null, 1); $check($state, 'pagamento_meta');
onboardingAssert(!$state['itens'][3]['done'] && !$state['concluido'], 'Conta A não pode usar template/entrega da C.');
$check(onboardingCalculate($db, null, 2), 'conexao_registro');
$state = onboardingCalculate($db, null, 99); $check($state, 'selecionar_conta');
onboardingAssert(count($state['contas']) === 3 && !$state['conta'], 'Outra empresa não pode vazar no seletor.');
$state = onboardingCalculate($db); $check($state, 'conexao_registro', true);
onboardingAssert((int)$state['conta']['MTA_ID'] === 3, 'Conta com conquista tem prioridade determinística.');
$db->exec("DELETE FROM conversa_mensagens; UPDATE meta_contas SET MTA_Ativo='N' WHERE MTA_ID=99");
$state = onboardingCalculate($db); onboardingAssert((int)$state['conta']['MTA_ID'] === 1, 'Conectada deve preceder vinculadas mais recentes.');

// P: entrega histórica permanece após desconexão ou remoção do template.
$db = onboardingDb(); onboardingAccount($db); onboardingMessage($db, 'delivered');
$db->exec("UPDATE meta_contas SET MTA_Status='desconectado'");
$state = onboardingCalculate($db); $check($state, 'conexao_reconectar', true);
onboardingAssert($state['recuperacao'], 'Desconexão após entrega é recuperação, não nova ativação.');

// Recebidas, histórico do Business App, eco e status entregue sem id não ativam.
foreach([['history','enviada'],['business_app','enviada'],['api','recebida']] as [$origin,$direction]){
    $db = onboardingDb(); onboardingAccount($db); onboardingTemplate($db); onboardingMessage($db,'delivered',1,1,$origin,$direction);
    $check(onboardingCalculate($db), 'primeiro_envio');
}
$db = onboardingDb(); onboardingAccount($db); onboardingTemplate($db); onboardingMessage($db,'delivered');
$db->exec("UPDATE conversa_mensagens SET MSG_MetaMessageId=NULL");
$check(onboardingCalculate($db), 'primeiro_envio');

// Fluxos alternativos, conta incorreta em snapshot e acesso bloqueado não geram CTA indevido.
$db = onboardingDb(); onboardingAccount($db, 1, 'requer_acao');
$db->exec("UPDATE meta_contas SET MTA_OnboardingType='coexistence'");
$state = onboardingCalculate($db, onboardingAccess(true)); $check($state,'conexao_acao');
onboardingAssert(strpos($state['proxima']['descricao'],'não usa o registro por PIN') !== false, 'Coexistência não pode pedir PIN.');
$db = onboardingDb(); onboardingAccount($db); onboardingTemplate($db);
$check(onboardingCalculate($db, ['operacional'=>false,'gerenciar'=>true]), 'financeiro');
$check(onboardingCalculate($db, onboardingAccess(true)), 'acesso_pendente');
$check(onboardingCalculate($db, ['operacional'=>false,'pre_trial'=>false,'gerenciar'=>false]), 'responsavel');
$db->exec("DELETE FROM templates_meta");
$check(onboardingCalculate($db, ['operacional'=>true,'gerenciar'=>false]), 'responsavel');

// Q: todas as consultas foram executadas com banco query_only e conexão que rejeita escrita.
$before = $db->query('SELECT * FROM meta_contas')->fetchAll(PDO::FETCH_ASSOC);
onboardingCalculate($db); onboardingCalculate($db);
onboardingAssert($before === $db->query('SELECT * FROM meta_contas')->fetchAll(PDO::FETCH_ASSOC), 'Leitura alterou conta.');
echo "OnboardingGuidedDashboardTest OK: {$cases} cenários; SQL real em memória, somente leitura.\n";
