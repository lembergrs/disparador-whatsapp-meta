<?php

function metaStatusSyncAssert($cond, $msg){
    if(!$cond){ fwrite(STDERR, "FAIL: {$msg}\n"); exit(1); }
}

$root = dirname(__DIR__);
$controller = file_get_contents($root . '/app/Controllers/ConfiguracaoController.php');
$metaService = file_get_contents($root . '/app/Services/MetaService.php');
$metaModel = file_get_contents($root . '/app/Models/MetaConta.php');
$view = file_get_contents($root . '/app/Views/configuracao/meta.php');

metaStatusSyncAssert(strpos($metaService, 'public function consultarDadosNumero()') !== false, 'MetaService centraliza consulta dos dados do número.');
metaStatusSyncAssert(strpos($metaService, 'GET') === false || strpos($metaService, 'graphGetConta') !== false, 'Consulta Graph fica encapsulada no service.');
metaStatusSyncAssert(strpos($metaService, "'fields' => 'id,display_phone_number,verified_name,quality_rating,code_verification_status,name_status,status,platform_type'") !== false, 'Campos esperados do Phone Number ID são solicitados.');
metaStatusSyncAssert(strpos($metaService, "MTA_WabaId'] . '/phone_numbers'") !== false, 'Listagem da WABA complementa status operacional quando necessário.');
metaStatusSyncAssert(strpos($metaService, 'Authorization: Bearer') !== false, 'Token é usado somente no backend.');

metaStatusSyncAssert(strpos($metaModel, 'public function atualizarEspelhoMeta') !== false, 'Model possui atualização de espelho Meta.');
metaStatusSyncAssert(strpos($metaModel, 'if($' . "valor === null || $" . "valor === '')") !== false, 'Campos ausentes não apagam valores existentes.');
metaStatusSyncAssert(strpos($metaModel, 'MTA_UltimaVerificacao = NOW()') !== false, 'Última verificação é atualizada.');
metaStatusSyncAssert(strpos($metaModel, '$statusInterno = null') !== false, 'MTA_Status pode ser preservado em sincronização administrativa.');

metaStatusSyncAssert(strpos($controller, 'sincronizarDadosNumeroMeta($conta)') !== false, '/register chama sincronização após sucesso.');
metaStatusSyncAssert(strpos($controller, 'sync_after_register') !== false, 'Falha de sincronização pós-register é logada separadamente.');
metaStatusSyncAssert(strpos($controller, 'array_merge($' . "dadosTelefone, ['status' => 'conectado'])") !== false, 'Registro bem-sucedido marca MTA_Status conectado independentemente do OperationalStatus.');
metaStatusSyncAssert(strpos($controller, 'atualizarStatusMetaAjax') !== false, 'Endpoint administrativo AJAX foi criado.');
metaStatusSyncAssert(strpos($controller, '($' . "usuario['nivel'] ?? null) !== 'admin'") !== false, 'Endpoint exige nível admin exato.');
metaStatusSyncAssert(strpos($controller, 'buscarPorUsuario($contaId, $usuario)') !== false, 'Endpoint valida escopo com Auth::idsContasMetaPermitidas via model.');
metaStatusSyncAssert(strpos($controller, 'atualizarEspelhoMeta((int) $conta[\'MTA_ID\'], (int) $conta[\'CLI_ID\'], $dadosTelefone, null)') !== false, 'Botão administrativo preserva MTA_Status.');
metaStatusSyncAssert(strpos($controller, "'MTA_Token'") !== false && strpos($controller, '$' . "_POST['token']") === false, 'Token não vem do frontend.');
metaStatusSyncAssert(strpos($controller, '$' . "_POST['phone_number_id']") === false, 'Phone Number ID não vem do frontend.');
metaStatusSyncAssert(strpos($controller, 'mensagemAmigavelErroMeta') !== false && strpos($controller, 'Não foi possível consultar a Meta neste momento') !== false, 'Erros de diagnóstico são tratados amigavelmente.');

metaStatusSyncAssert(substr_count($controller, 'function mensagemAmigavelErroMeta') === 1, 'ConfiguracaoController deve manter uma única declaração de mensagemAmigavelErroMeta.');
metaStatusSyncAssert(strpos($controller, 'registro operacional') !== false && strpos($controller, 'Não foi possível concluir o registro com esse PIN') !== false, 'Implementação consolidada preserva erro amigável para PIN/payload inválido.');

metaStatusSyncAssert(strpos($view, 'btnAtualizarStatusMetaAdmin') !== false, 'Botão Atualizar status da Meta aparece na view.');
metaStatusSyncAssert(strpos($view, '($' . "usuario['nivel'] ?? null) === 'admin'") !== false, 'Visibilidade do botão é exclusiva para admin.');
metaStatusSyncAssert(strpos($view, 'idsContasMetaPermitidas') !== false, 'View usa escopo permitido para exibir botão.');
metaStatusSyncAssert(strpos($view, 'configuracao/atualizarStatusMetaAjax') !== false, 'Frontend chama endpoint AJAX correto.');
metaStatusSyncAssert(strpos($view, 'fa-sync-alt') !== false, 'Botão usa ícone de sincronização.');
metaStatusSyncAssert(strpos($view, 'window.location.reload()') !== false, 'Interface atualiza a tela após sucesso.');
metaStatusSyncAssert(strpos($view, 'MTA_OperationalStatus') !== false && strpos($view, 'MTA_QualityRating') !== false, 'Diagnóstico Meta é exibido para admin.');

echo "Meta admin status sync static tests passed\n";
