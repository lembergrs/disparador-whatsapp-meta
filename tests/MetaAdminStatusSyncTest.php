<?php

function metaStatusSyncAssert($cond, $msg){
    if(!$cond){ fwrite(STDERR, "FAIL: {$msg}\n"); exit(1); }
}

$root = dirname(__DIR__);
$controller = file_get_contents($root . '/app/Controllers/ConfiguracaoController.php');
$metaService = file_get_contents($root . '/app/Services/MetaService.php');
$metaModel = file_get_contents($root . '/app/Models/MetaConta.php');
$view = file_get_contents($root . '/app/Views/configuracao/meta.php');
$metaContasView = file_get_contents($root . '/app/Views/meta_contas/index.php');
$conversaController = file_get_contents($root . '/app/Controllers/ConversaController.php');
$templateModel = file_get_contents($root . '/app/Models/TemplateMeta.php');

metaStatusSyncAssert(strpos($metaService, 'public function consultarDadosNumero()') !== false, 'MetaService centraliza consulta dos dados do número.');
metaStatusSyncAssert(strpos($metaService, 'GET') === false || strpos($metaService, 'graphGetConta') !== false, 'Consulta Graph fica encapsulada no service.');
metaStatusSyncAssert(strpos($metaService, "'fields' => 'id,display_phone_number,verified_name,quality_rating,code_verification_status,name_status,status,platform_type,whatsapp_business_manager_messaging_limit'") !== false, 'Campos esperados do Phone Number ID são solicitados, incluindo limite Meta.');
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
metaStatusSyncAssert(strpos($metaModel, 'public function buscarPorIdAdmin') !== false, 'Model possui busca administrativa por MTA_ID.');
metaStatusSyncAssert(strpos($controller, 'buscarPorIdAdmin($contaId)') !== false, 'Endpoint administrativo usa busca por MTA_ID sem escopo operacional.');
metaStatusSyncAssert(strpos($controller, 'buscarPorUsuario($contaId, $usuario)') === false, 'Endpoint administrativo não usa buscarPorUsuario.');
metaStatusSyncAssert(strpos($controller, 'atualizarEspelhoMeta((int) $conta[\'MTA_ID\'], (int) $conta[\'CLI_ID\'], $dadosTelefone, null)') !== false, 'Botão administrativo preserva MTA_Status.');
metaStatusSyncAssert(strpos($controller, "'MTA_Token'") !== false && strpos($controller, '$' . "_POST['token']") === false, 'Token não vem do frontend.');
metaStatusSyncAssert(strpos($controller, '$' . "_POST['phone_number_id']") === false, 'Phone Number ID não vem do frontend.');
metaStatusSyncAssert(strpos($controller, 'mensagemAmigavelErroMeta') !== false && strpos($controller, 'Não foi possível consultar a Meta neste momento') !== false, 'Erros de diagnóstico são tratados amigavelmente.');

$inicioEndpoint = strpos($controller, 'public function atualizarStatusMetaAjax()');
$fimEndpoint = strpos($controller, 'public function atualizarStatusNumeroWhatsApp()', $inicioEndpoint);
$endpointAdmin = substr($controller, $inicioEndpoint, $fimEndpoint - $inicioEndpoint);
metaStatusSyncAssert(strpos($endpointAdmin, 'idsContasMetaPermitidas') === false, 'Endpoint administrativo não exige idsContasMetaPermitidas.');
metaStatusSyncAssert(strpos($endpointAdmin, '($' . "usuario['nivel'] ?? null) !== 'admin'") !== false, 'Endpoint bloqueia cliente, cliente_admin e operador por nível exato admin.');
metaStatusSyncAssert(strpos($endpointAdmin, 'exigirPost') !== false, 'Endpoint mantém validação CSRF via exigirPost.');
metaStatusSyncAssert(strpos($endpointAdmin, '($' . "conta['MTA_Ativo'] ?? 'N') !== 'S'") !== false, 'Endpoint bloqueia conta inexistente ou inativa.');
metaStatusSyncAssert(strpos($endpointAdmin, 'empty($' . "conta['MTA_PhoneNumberId']) || empty($" . "conta['MTA_Token'])") !== false, 'Endpoint bloqueia conta sem token ou Phone Number ID.');
metaStatusSyncAssert(strpos($endpointAdmin, 'atualizarEspelhoMeta((int) $' . "conta['MTA_ID'], (int) $" . "conta['CLI_ID'], $" . "dadosTelefone, null)") !== false, 'Sincronização administrativa preserva MTA_Status.');
metaStatusSyncAssert(strpos($endpointAdmin, "'MTA_Token'") === false || strpos($endpointAdmin, '$' . "_POST['token']") === false, 'Endpoint não recebe token do frontend.');
metaStatusSyncAssert(strpos($endpointAdmin, '$' . "_POST['phone_number_id']") === false, 'Endpoint não recebe Phone Number ID do frontend.');

metaStatusSyncAssert(substr_count($controller, 'function mensagemAmigavelErroMeta') === 1, 'ConfiguracaoController deve manter uma única declaração de mensagemAmigavelErroMeta.');
metaStatusSyncAssert(strpos($controller, 'registro operacional') !== false && strpos($controller, 'Não foi possível concluir o registro com esse PIN') !== false, 'Implementação consolidada preserva erro amigável para PIN/payload inválido.');

metaStatusSyncAssert(strpos($view, 'btnAtualizarStatusMetaAdmin') !== false, 'Botão Atualizar status da Meta aparece na view.');
metaStatusSyncAssert(strpos($view, '($' . "usuario['nivel'] ?? null) === 'admin'") !== false, 'Visibilidade do botão é exclusiva para admin.');
metaStatusSyncAssert(strpos($view, 'idsContasMetaPermitidas') !== false, 'View usa escopo permitido para exibir botão.');
metaStatusSyncAssert(strpos($view, 'configuracao/atualizarStatusMetaAjax') !== false, 'Frontend chama endpoint AJAX correto.');
metaStatusSyncAssert(strpos($view, 'fa-sync-alt') !== false, 'Botão usa ícone de sincronização.');
metaStatusSyncAssert(strpos($view, 'window.location.reload()') !== false, 'Interface atualiza a tela após sucesso.');
metaStatusSyncAssert(strpos($view, 'MTA_OperationalStatus') !== false && strpos($view, 'MTA_QualityRating') !== false, 'Diagnóstico Meta é exibido para admin.');


metaStatusSyncAssert(strpos($metaContasView, 'SincronizarStatusMeta') !== false || strpos($metaContasView, 'btnSincronizarStatusMeta') !== false, 'Painel administrativo de contas Meta exibe botão de sincronização.');
metaStatusSyncAssert(strpos($metaContasView, 'Status Meta') !== false && strpos($metaContasView, 'MTA_OperationalStatus') !== false, 'Tabela exibe coluna Status Meta baseada em MTA_OperationalStatus.');
metaStatusSyncAssert(strpos($metaContasView, 'Quality') !== false && strpos($metaContasView, 'MTA_QualityRating') !== false, 'Tabela exibe coluna Quality baseada em MTA_QualityRating.');
metaStatusSyncAssert(strpos($metaContasView, 'Última sincronização') !== false && strpos($metaContasView, 'MTA_UltimaVerificacao') !== false, 'Tabela exibe última sincronização.');
metaStatusSyncAssert(strpos($metaContasView, 'title="Editar conta Meta"') !== false, 'Tooltip de editar conta Meta existe.');
metaStatusSyncAssert(strpos($metaContasView, 'title="Conectar ou reconectar o número do WhatsApp"') !== false, 'Tooltip de conectar/reconectar existe.');
metaStatusSyncAssert(strpos($metaContasView, 'title="Atualizar informações do número diretamente na Meta"') !== false, 'Tooltip de sincronização existe.');
metaStatusSyncAssert(strpos($metaContasView, 'title="Excluir conta Meta"') !== false, 'Tooltip de excluir conta Meta existe.');
metaStatusSyncAssert(strpos($metaContasView, 'configuracao/atualizarStatusMetaAjax') !== false, 'Botão do painel admin reutiliza endpoint de sincronização existente.');
metaStatusSyncAssert(strpos($metaContasView, '.js-meta-operational-status') !== false && strpos($metaContasView, '.js-meta-quality-rating') !== false && strpos($metaContasView, '.js-meta-ultima-verificacao') !== false, 'AJAX atualiza Status Meta, Quality e Última sincronização na linha.');
metaStatusSyncAssert(strpos($metaContasView, "row(linha).invalidate('dom').draw(false)") !== false, 'DataTable é invalidado sem resetar paginação após atualização da linha.');
metaStatusSyncAssert(strpos($metaContasView, 'window.location.reload') === false, 'Painel admin não recarrega a página inteira após sincronizar.');


metaStatusSyncAssert(strpos($conversaController, 'idsContasMetaPermitidas') !== false, 'Conversas continuam usando escopo restrito.');
metaStatusSyncAssert(strpos($templateModel, 'idsContasMetaPermitidas($usuario)') !== false, 'Templates continuam usando escopo restrito.');

echo "Meta admin status sync static tests passed\n";
