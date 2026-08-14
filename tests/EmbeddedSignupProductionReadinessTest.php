<?php

require_once __DIR__ . '/../app/Services/EmbeddedSignupOnboardingMode.php';

use Services\EmbeddedSignupOnboardingMode;

function productionAssert($condition, $message){ if(!$condition){ fwrite(STDERR, "FAIL: {$message}\n"); exit(1); } }

$root = dirname(__DIR__);
$controller = file_get_contents($root.'/app/Controllers/ConfiguracaoController.php');
$view = file_get_contents($root.'/app/Views/configuracao/meta.php');
$attempt = file_get_contents($root.'/app/Models/MetaEmbeddedSignupAttempt.php');
$account = file_get_contents($root.'/app/Models/MetaConta.php');
$sync = file_get_contents($root.'/app/Services/MetaCoexistenceSyncService.php');
$worker = file_get_contents($root.'/app/Services/WorkerService.php');
$historyQueue = file_get_contents($root.'/app/Services/MetaCoexistenceHistoryQueueService.php');
$config = file_get_contents($root.'/config/config.php');

productionAssert(EmbeddedSignupOnboardingMode::fromFinishEvent('FINISH') === 'traditional', 'traditional é detectado automaticamente');
productionAssert(EmbeddedSignupOnboardingMode::fromFinishEvent('FINISH_WHATSAPP_BUSINESS_APP_ONBOARDING') === 'coexistence', 'Coexistence é detectado automaticamente');
productionAssert(strpos($attempt, 'onboarding_type = ?') !== false, 'modalidade detectada é persistida atomicamente com FINISH');
productionAssert(strpos($view, 'id="btnConectarWhatsApp"') !== false && strpos($view, 'id="btnConectarWhatsAppVazio"') !== false && strpos($view, 'btnConectarWhatsAppCoexistence') === false, 'cliente possui uma única ação conceitual sem seletor de modalidade');
productionAssert(strpos($view, 'onboarding_mode:') === false, 'frontend não envia modalidade escolhida');
productionAssert(strpos($view, 'As opções disponíveis serão apresentadas pela Meta durante a conexão.') !== false, 'interface explica a escolha feita pela Meta');
productionAssert(strpos($controller, 'Este número não utiliza registro por PIN no Disparador.') !== false, 'Coexistence não registra novamente por PIN');
productionAssert(strpos($sync, "['smb_app_state_sync'=>'contact', 'history'=>'history']") !== false, 'sync inicial mantém contatos antes do histórico');
productionAssert(strpos($sync, 'reservarSyncUmaVez') !== false, 'sync normal só solicita cada tipo no primeiro onboarding aplicável');
productionAssert(strpos($worker, 'repetirSyncCoexistence') === false, 'worker não repete sync automaticamente');
productionAssert(strpos($account, "MTA_HistorySyncStatus IN ('completed','declined')") !== false || strpos($account, "['completed','declined']") !== false || strpos($account, "IN ('requested','request_failed')") !== false, 'retry não inclui estados terminais');
productionAssert(strpos($historyQueue, 'MTA_Status') === false, 'falha ou ausência de histórico não invalida conexão');
productionAssert(strpos($account, "'PARTNER_REMOVED'=>['desconectado','DISCONNECTED']") !== false, 'PARTNER_REMOVED desconecta');
productionAssert(strpos($account, "'ACCOUNT_OFFBOARDED'=>['desconectado','DISCONNECTED']") !== false, 'ACCOUNT_OFFBOARDED desconecta');
productionAssert(strpos($account, "'ACCOUNT_RECONNECTED'=>['conectado','CONNECTED']") !== false, 'ACCOUNT_RECONNECTED restaura operação');
productionAssert(strpos($account, 'A conta já foi vinculada por outra modalidade') === false, 'reconexão não é bloqueada pela modalidade antiga');
productionAssert(strpos($account, 'buscarPorClienteWabaPhone') !== false && strpos($account, 'atualizarEmbeddedSignup') !== false, 'reconexão reutiliza cliente + WABA + Phone Number');
productionAssert(strpos($account, 'temContaDesconectadaPorCliente') !== false && strpos($controller, '$contaExistenteId ?: null') !== false, 'conta desconectada pode reabrir onboarding sem liberar número adicional');
productionAssert(strpos($controller, 'public function repetirSyncCoexistenceAjax()') !== false && strpos($view, 'repetirSyncCoexistenceAjax') === false, 'retry permanece administrativo e fora da interface do cliente');
productionAssert(strpos($config, "env_valor('META_COEXISTENCE_ENABLED', 'false')") !== false, 'flag mantém padrão fail-safe');
productionAssert(strpos($config, 'META_COEXISTENCE_TEST_CLIENT_IDS') !== false, 'allowlist permanece disponível somente para rollout controlado');

echo "Embedded Signup production readiness tests passed\n";
