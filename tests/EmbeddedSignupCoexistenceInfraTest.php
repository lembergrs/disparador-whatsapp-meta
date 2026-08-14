<?php

require_once __DIR__ . '/../app/Services/EmbeddedSignupOnboardingMode.php';
require_once __DIR__ . '/../app/Services/EmbeddedSignupFlowService.php';

use Services\EmbeddedSignupFlowService;
use Services\EmbeddedSignupOnboardingMode;

function coexistenceAssert($condition, $message)
{
    if(!$condition){
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

coexistenceAssert(EmbeddedSignupOnboardingMode::normalize(null) === 'traditional', 'modo ausente deve ser traditional');
coexistenceAssert(EmbeddedSignupOnboardingMode::acceptsFinishEvent('traditional', 'FINISH'), 'traditional aceita FINISH');
coexistenceAssert(!EmbeddedSignupOnboardingMode::acceptsFinishEvent('traditional', 'FINISH_WHATSAPP_BUSINESS_APP_ONBOARDING'), 'traditional rejeita FINISH Coexistence');
coexistenceAssert(EmbeddedSignupOnboardingMode::acceptsFinishEvent('coexistence', 'FINISH_WHATSAPP_BUSINESS_APP_ONBOARDING'), 'Coexistence aceita seu FINISH');
coexistenceAssert(!EmbeddedSignupOnboardingMode::acceptsFinishEvent('coexistence', 'FINISH'), 'Coexistence rejeita FINISH tradicional');
coexistenceAssert(EmbeddedSignupOnboardingMode::fromFinishEvent('FINISH') === 'traditional', 'FINISH detecta traditional');
coexistenceAssert(EmbeddedSignupOnboardingMode::fromFinishEvent('FINISH_WHATSAPP_BUSINESS_APP_ONBOARDING') === 'coexistence', 'evento WhatsApp Business App detecta Coexistence');

try{
    EmbeddedSignupOnboardingMode::normalize('arbitrary');
    coexistenceAssert(false, 'modo arbitrário deveria falhar');
}catch(InvalidArgumentException $e){}

$view = file_get_contents(__DIR__ . '/../app/Views/configuracao/meta.php');
$controller = file_get_contents(__DIR__ . '/../app/Controllers/ConfiguracaoController.php');
$attempt = file_get_contents(__DIR__ . '/../app/Models/MetaEmbeddedSignupAttempt.php');
$account = file_get_contents(__DIR__ . '/../app/Models/MetaConta.php');
$config = file_get_contents(__DIR__ . '/../config/config.php');
$migration = file_get_contents(__DIR__ . '/../database/migrations/20260812_add_meta_coexistence_onboarding_infra.sql');
$webhook = file_get_contents(__DIR__ . '/../public/webhook/meta.php');

coexistenceAssert(strpos($config, "env_valor('META_COEXISTENCE_ENABLED', 'false')") !== false, 'flag deve vir desabilitada por padrão');
coexistenceAssert(strpos($view, 'whatsapp_business_app_onboarding') !== false, 'payload Coexistence inclui featureType');
coexistenceAssert(strpos($view, "options.extras.featureType = 'whatsapp_business_app_onboarding'") !== false, 'featureType é condicional');
coexistenceAssert(strpos($view, 'signupRequiresFinish') !== false, 'fluxo unificado aguarda evento para detectar a modalidade');
coexistenceAssert(strpos($view, 'btnConectarWhatsAppCoexistence') === false, 'não existe escolha prévia de Coexistence');
coexistenceAssert(strpos($view, "MTA_OnboardingType'] ?? 'traditional') !== 'coexistence'") !== false, 'PIN fica oculto para Coexistence');
coexistenceAssert(strpos($controller, 'EmbeddedSignupOnboardingMode::fromFinishEvent') !== false, 'backend detecta modalidade pelo FINISH da Meta');
coexistenceAssert(strpos($controller, 'colunasCoexistenceExistem') !== false, 'Coexistence exige a migration de conta antes de iniciar');
coexistenceAssert(strpos($controller, "empty(\$tentativa['finish'])") !== false, 'Coexistence sem FINISH não pode cair no fallback');
coexistenceAssert(strpos($controller, 'Este número não utiliza registro por PIN no Disparador.') !== false, 'endpoint PIN rejeita Coexistence sem expor termo técnico');
coexistenceAssert(strpos($controller, "? \$this->embeddedSignupFlowService()->definirStatusCoexistencia") !== false, 'Coexistence usa status próprio');
coexistenceAssert(strpos($attempt, 'onboarding_type') !== false, 'tentativa persiste o modo');
coexistenceAssert(strpos($account, 'MTA_OnboardingType') !== false && strpos($account, 'MTA_PlatformType') !== false, 'conta persiste modo e plataforma');
coexistenceAssert(strpos($migration, 'MTA_OnboardingType') !== false && strpos($migration, 'MTA_PlatformType') !== false && strpos($migration, 'onboarding_type') !== false, 'migration contém campos mínimos');
coexistenceAssert(strpos($webhook, "\$field === 'smb_app_state_sync'") !== false, 'infraestrutura posterior preserva o roteamento state sync');

$calls = [];
$service = new EmbeddedSignupFlowService(function($endpoint) use (&$calls){ $calls[] = $endpoint; return ['success'=>true]; }, '123');
coexistenceAssert($service->definirStatusCoexistencia([]) === 'requer_acao', 'metadata insuficiente usa estado intermediário sem PIN');
coexistenceAssert($service->definirStatusCoexistencia(['operational_status'=>'CONNECTED']) === 'conectado', 'CONNECTED comprovado pode conectar');
$realMetaState = [
    'operational_status' => 'CONNECTED',
    'platform_type' => 'CLOUD_API',
    'name_status' => 'AVAILABLE_WITHOUT_REVIEW',
    'code_verification_status' => 'NOT_VERIFIED'
];
coexistenceAssert($service->definirStatusCoexistencia($realMetaState) === 'conectado', 'Coexistence CONNECTED permanece operacional com NOT_VERIFIED');
coexistenceAssert($service->definirStatusCoexistencia(['operational_status'=>'PENDING']) === 'requer_acao', 'Coexistence sem CONNECTED requer ação');
coexistenceAssert($service->definirStatusCoexistencia(['operational_status'=>'']) === 'requer_acao', 'Coexistence com status operacional vazio requer ação');
coexistenceAssert($service->definirStatusConexao($realMetaState) === 'requer_acao', 'traditional preserva bloqueio por NOT_VERIFIED');
coexistenceAssert($calls === [], 'estratégia de status Coexistence nunca chama register');

echo "Embedded signup Coexistence infrastructure tests passed\n";
