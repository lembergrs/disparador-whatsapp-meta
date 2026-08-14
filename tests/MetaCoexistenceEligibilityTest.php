<?php

require_once __DIR__ . '/../app/Services/MetaCoexistenceEligibility.php';

use Services\MetaCoexistenceEligibility;

function eligibilityAssert($condition, $message)
{
    if(!$condition){
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$empty = new MetaCoexistenceEligibility(false, '');
eligibilityAssert(!$empty->availableForClient(14), 'global false e allowlist vazia nega acesso');

$homologation = new MetaCoexistenceEligibility(false, '14');
eligibilityAssert($homologation->availableForClient(14), 'CLI_ID 14 está permitido');
eligibilityAssert(!$homologation->availableForClient(15), 'outro cliente permanece negado');

$normalized = new MetaCoexistenceEligibility(false, ' 14, 27 ,31 ');
eligibilityAssert($normalized->availableForClient(14), 'whitespace é removido');
eligibilityAssert($normalized->availableForClient(27) && $normalized->availableForClient(31), 'múltiplos IDs são aceitos');

$malformed = MetaCoexistenceEligibility::normalizeTestClientIds('14,,0,-1,abc,2.5, 27x, +31,0042,42');
eligibilityAssert($malformed === [14, 42], 'IDs vazios e malformados são ignorados');
eligibilityAssert(!(new MetaCoexistenceEligibility(false, '14'))->availableForClient('14'), 'comparação não é frouxa');
eligibilityAssert((new MetaCoexistenceEligibility(true, ''))->availableForClient(999), 'flag global libera qualquer cliente');

$root = dirname(__DIR__);
$controller = file_get_contents($root . '/app/Controllers/ConfiguracaoController.php');
$view = file_get_contents($root . '/app/Views/configuracao/meta.php');
$config = file_get_contents($root . '/config/config.php');

eligibilityAssert(substr_count($controller, 'exigirCoexistenceDisponivel') >= 3, 'backend protege a conclusão do Coexistence');
eligibilityAssert(strpos($controller, "\$_POST['onboarding_mode']") === false, 'cliente não escolhe nem forja modalidade no início');
eligibilityAssert(strpos($view, "postForm(BASE_URL + '/index.php?url=configuracao/iniciarEmbeddedSignup', {})") !== false, 'ação única inicia sem modalidade pré-selecionada');
eligibilityAssert(strpos($view, "options.extras.featureType = 'whatsapp_business_app_onboarding'") !== false, 'payload Coexistence mantém featureType');
eligibilityAssert(strpos($view, 'if(resp.coexistenceAvailable === true)') !== false, 'featureType segue elegibilidade centralizada');
eligibilityAssert(strpos($config, "env_valor('META_COEXISTENCE_ENABLED', 'false')") !== false, 'flag global permanece false por padrão');
eligibilityAssert(strpos($config, "env_valor('META_COEXISTENCE_TEST_CLIENT_IDS', '')") !== false, 'allowlist permanece vazia por padrão');

echo "Meta Coexistence eligibility tests passed\n";
