<?php

function metaMessagingLimitAssert($cond, $msg){
    if(!$cond){ fwrite(STDERR, "FAIL: {$msg}\n"); exit(1); }
}

$root = dirname(__DIR__);
require_once $root . '/app/Services/MetaService.php';

$metaService = file_get_contents($root . '/app/Services/MetaService.php');
$metaModel = file_get_contents($root . '/app/Models/MetaConta.php');
$controller = file_get_contents($root . '/app/Controllers/ConfiguracaoController.php');
$adminView = file_get_contents($root . '/app/Views/meta_contas/index.php');
$dashboardView = file_get_contents($root . '/app/Views/dashboard/index.php');
$disparoController = file_get_contents($root . '/app/Controllers/DisparoController.php');
$disparoView = file_get_contents($root . '/app/Views/disparos/index.php');
$campanhaController = file_get_contents($root . '/app/Controllers/CampanhaController.php');
$campanhaView = file_get_contents($root . '/app/Views/campanhas/index.php');
$migration = file_get_contents($root . '/database/migrations/20260717_add_meta_messaging_limit.sql');
$conversaController = file_get_contents($root . '/app/Controllers/ConversaController.php');
$templateModel = file_get_contents($root . '/app/Models/TemplateMeta.php');

metaMessagingLimitAssert(strpos($migration, 'MTA_MessagingLimit') !== false, 'Migration adiciona campo MTA_MessagingLimit.');
metaMessagingLimitAssert(strpos($migration, 'VARCHAR(100)') !== false, 'Campo do limite Meta permite valor textual amplo.');
metaMessagingLimitAssert(strpos($migration, 'Limite de conversas iniciadas pela empresa informado pela Meta') !== false, 'Migration documenta que o campo é limite de conversas da Meta.');

metaMessagingLimitAssert(strpos($metaService, 'whatsapp_business_manager_messaging_limit') !== false, 'Consulta Graph solicita o campo oficial whatsapp_business_manager_messaging_limit.');
metaMessagingLimitAssert(strpos($metaService, 'messaging_limit_tier') !== false, 'Normalização tolera campo legado quando retornado pela API.');
metaMessagingLimitAssert(strpos($metaService, 'normalizarLimiteConversasMeta') !== false, 'MetaService possui normalização central do limite Meta.');
metaMessagingLimitAssert(strpos($metaService, 'formatarLimiteConversasMeta') !== false, 'MetaService possui formatação central do limite Meta.');
metaMessagingLimitAssert(strpos($metaService, 'avisoDesatualizacaoMeta') !== false, 'MetaService possui regra visual para dado desatualizado.');

metaMessagingLimitAssert(\Services\MetaService::normalizarLimiteConversasMeta(250) === '250', 'Valor numérico 250 é normalizado.');
metaMessagingLimitAssert(\Services\MetaService::normalizarLimiteConversasMeta('TIER_250') === '250', 'TIER_250 é normalizado.');
metaMessagingLimitAssert(\Services\MetaService::normalizarLimiteConversasMeta('TIER_2K') === '2000', 'TIER_2K é normalizado.');
metaMessagingLimitAssert(\Services\MetaService::normalizarLimiteConversasMeta('TIER_10K') === '10000', 'TIER_10K é normalizado.');
metaMessagingLimitAssert(\Services\MetaService::normalizarLimiteConversasMeta('TIER_100K') === '100000', 'TIER_100K é normalizado.');
metaMessagingLimitAssert(\Services\MetaService::normalizarLimiteConversasMeta('UNLIMITED') === 'UNLIMITED', 'UNLIMITED é aceito.');
metaMessagingLimitAssert(\Services\MetaService::formatarLimiteConversasMeta('2000') === '2.000 clientes únicos em 24 horas', 'Valor normalizado é exibido com unidade correta.');
metaMessagingLimitAssert(\Services\MetaService::formatarLimiteConversasMeta('UNLIMITED') === 'Ilimitado', 'Ilimitado é exibido corretamente.');
metaMessagingLimitAssert(\Services\MetaService::normalizarLimiteConversasMeta('TIER_DESCONHECIDO') === 'TIER_DESCONHECIDO', 'Valor desconhecido não quebra nem inventa equivalência.');

metaMessagingLimitAssert(strpos($metaModel, "'MTA_MessagingLimit' => 'messaging_limit'") !== false, 'Model persiste limite retornado pela Meta.');
metaMessagingLimitAssert(strpos($metaModel, 'if($valor === null || $valor === \'\')') !== false, 'Model preserva valor anterior quando campo vem ausente/vazio.');
metaMessagingLimitAssert(strpos($controller, 'messaging_limit_label') !== false, 'Endpoint retorna label segura do limite Meta.');
$inicioEndpoint = strpos($controller, 'public function atualizarStatusMetaAjax()');
$fimEndpoint = strpos($controller, 'public function atualizarStatusNumeroWhatsApp()', $inicioEndpoint);
$endpointAdmin = substr($controller, $inicioEndpoint, $fimEndpoint - $inicioEndpoint);
metaMessagingLimitAssert(strpos($endpointAdmin, "'token'") === false && strpos($endpointAdmin, 'MTA_Token') !== false, 'Endpoint usa token do backend e não retorna token no JSON.');

metaMessagingLimitAssert(strpos($adminView, 'Limite Meta') !== false, 'Painel Admin exibe coluna Limite Meta.');
metaMessagingLimitAssert(strpos($adminView, 'Limite de conversas iniciadas pela empresa informado e controlado pela Meta') !== false, 'Tooltip do limite Meta deixa claro que a Meta controla o limite.');
metaMessagingLimitAssert(strpos($adminView, '.js-meta-messaging-limit') !== false, 'Sincronização AJAX atualiza célula de Limite Meta.');
metaMessagingLimitAssert(strpos($adminView, 'messaging_limit_label') !== false, 'AJAX usa label de limite Meta retornada pelo endpoint.');

metaMessagingLimitAssert(strpos($dashboardView, 'Seu plano no Disparador') !== false, 'Dashboard separa o bloco do plano Disparador.');
metaMessagingLimitAssert(strpos($dashboardView, 'Limite de conversas da Meta') !== false, 'Dashboard exibe bloco separado do limite Meta.');
metaMessagingLimitAssert(strpos($dashboardView, 'definido e controlado exclusivamente pela Meta') !== false, 'Dashboard informa que a Meta controla o limite.');
metaMessagingLimitAssert(strpos($dashboardView, 'Entenda os limites do Disparador e da Meta') !== false, 'Dashboard possui modal explicativo dos limites.');
metaMessagingLimitAssert(strpos($dashboardView, 'Limite da Meta ainda não disponível') !== false, 'Dashboard trata conta sem dado Meta.');
metaMessagingLimitAssert(strpos($dashboardView, 'Informação da Meta possivelmente desatualizada') !== false || strpos($dashboardView, 'avisoDesatualizacaoMeta') !== false, 'Dashboard mostra aviso para dado desatualizado.');
metaMessagingLimitAssert(strpos($dashboardView, 'saldo Meta') === false && strpos($dashboardView, 'capacidade restante') === false && strpos($dashboardView, '0 de 250') === false, 'Dashboard não apresenta saldo ou restante do limite Meta.');

metaMessagingLimitAssert(strpos($disparoController, 'metaContaLimite') !== false && strpos($disparoView, 'Plano Disparador') !== false, 'Disparo manual exibe bloco compacto do plano Disparador.');
metaMessagingLimitAssert(strpos($disparoView, 'Limite de conversas da Meta') !== false, 'Disparo manual exibe bloco compacto do limite Meta.');
metaMessagingLimitAssert(strpos($disparoView, 'Limite atualmente informado pela Meta') !== false, 'Disparo manual não mostra zero quando limite Meta falta.');
metaMessagingLimitAssert(strpos($disparoController, 'MTA_MessagingLimit') === false, 'Disparo manual não bloqueia por MTA_MessagingLimit no controller.');

metaMessagingLimitAssert(strpos($campanhaController, 'metaContaLimite') !== false && strpos($campanhaView, 'Plano Disparador') !== false, 'Campanhas exibem bloco compacto do plano Disparador.');
metaMessagingLimitAssert(strpos($campanhaView, 'Limite de conversas da Meta') !== false, 'Campanhas exibem bloco compacto do limite Meta.');
metaMessagingLimitAssert(strpos($campanhaView, 'A aprovação e o processamento dos envios também dependem das regras e limites aplicados pela Meta') !== false, 'Campanhas informam dependência de regras da Meta.');
metaMessagingLimitAssert(strpos($campanhaController, 'MTA_MessagingLimit') === false, 'Campanhas não bloqueiam nem dividem automaticamente por MTA_MessagingLimit no controller.');

metaMessagingLimitAssert(strpos($conversaController, 'idsContasMetaPermitidas') !== false, 'Conversas mantêm escopo restrito.');
metaMessagingLimitAssert(strpos($templateModel, 'idsContasMetaPermitidas') !== false, 'Templates mantêm escopo restrito.');

echo "Meta messaging limit tests passed\n";
