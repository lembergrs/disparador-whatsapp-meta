<?php

require_once __DIR__ . '/../app/Services/EmbeddedSignupOnboardingMode.php';
require_once __DIR__ . '/../app/Services/MetaCoexistenceSyncService.php';

use Services\MetaCoexistenceSyncService;

function retryAssert($condition, $message){ if(!$condition){ fwrite(STDERR, "FAIL: {$message}\n"); exit(1); } }

class RetrySyncRepoFake
{
    public $states = [];
    public function add($id, $type, $status, $ageMinutes, $requestId){
        $this->states[$id][$type] = ['status'=>$status, 'age'=>$ageMinutes, 'request_id'=>$requestId];
    }
    public function reservarRetrySync($id, $type){
        $state =& $this->states[$id][$type];
        if(!isset($state) || !in_array($state['status'], ['requested','request_failed'], true) || $state['age'] < 15) return null;
        $old = $state['request_id'];
        $state['status'] = 'requesting';
        return ['previous_request_id'=>$old];
    }
    public function confirmarSyncSolicitado($id, $type, $requestId){
        if($this->states[$id][$type]['status'] !== 'requesting') return false;
        $this->states[$id][$type] = ['status'=>'requested','age'=>0,'request_id'=>$requestId];
        return true;
    }
    public function marcarSyncFalho($id, $type){
        $this->states[$id][$type]['status'] = 'request_failed';
        $this->states[$id][$type]['age'] = 0;
        return true;
    }
}

$account = ['MTA_ID'=>5,'MTA_OnboardingType'=>'coexistence','MTA_PhoneNumberId'=>'123','MTA_Token'=>'secret'];
$calls = [];
$repo = new RetrySyncRepoFake();
$service = new MetaCoexistenceSyncService(function($endpoint,$payload) use (&$calls){
    $calls[] = [$endpoint,$payload['sync_type']];
    return ['request_id'=>'new-'.$payload['sync_type']];
}, $repo);

$repo->add(5,'contact','requested',14,'old-contact');
retryAssert(!$service->repetir($account,'contact')['iniciado'], 'requested com menos de 15 minutos não repete');
$repo->add(5,'contact','requested',15,'old-contact');
$audit = null;
$result = $service->repetir($account,'contact',function($old) use (&$audit){ $audit=$old; });
retryAssert($result['iniciado'] && $calls[0][1] === 'smb_app_state_sync', 'requested antigo repete contatos');
retryAssert($audit === 'old-contact', 'request_id anterior fica disponível para auditoria');
retryAssert($repo->states[5]['contact']['request_id'] === 'new-smb_app_state_sync', 'novo request_id substitui o atual');

$repo->add(5,'history','request_failed',16,'old-history');
$result = $service->repetir($account,'history');
retryAssert($result['iniciado'] && $calls[1][1] === 'history', 'request_failed permite retry independente de history');
retryAssert($repo->states[5]['history']['request_id'] === 'new-history', 'history persiste seu novo request_id');

foreach(['completed','declined'] as $terminal){
    $repo->add(5,'contact',$terminal,60,'terminal-id');
    retryAssert(!$service->repetir($account,'contact')['iniciado'], "{$terminal} nunca repete");
}
retryAssert(!$service->repetir(array_merge($account,['MTA_OnboardingType'=>'traditional']),'contact')['iniciado'], 'traditional nunca repete');

$repo->add(5,'contact','requested',20,'old-race');
retryAssert($repo->reservarRetrySync(5,'contact') !== null, 'primeira reserva concorrente vence');
retryAssert($repo->reservarRetrySync(5,'contact') === null, 'segunda reserva concorrente perde');

$errorRepo = new RetrySyncRepoFake();
$errorRepo->add(5,'history','requested',20,'old-on-error');
$errorService = new MetaCoexistenceSyncService(function(){ throw new RuntimeException('graph error'); }, $errorRepo);
try{ $errorService->repetir($account,'history'); retryAssert(false,'erro Graph deveria propagar'); }catch(RuntimeException $e){}
retryAssert($errorRepo->states[5]['history']['status'] === 'request_failed', 'erro Graph marca request_failed');
retryAssert($errorRepo->states[5]['history']['request_id'] === 'old-on-error', 'erro Graph preserva request_id anterior');

$root = dirname(__DIR__);
$model = file_get_contents($root.'/app/Models/MetaConta.php');
$controller = file_get_contents($root.'/app/Controllers/ConfiguracaoController.php');
$worker = file_get_contents($root.'/app/Services/WorkerService.php');
retryAssert(strpos($model, 'FOR UPDATE') !== false && strpos($model, "DATE_SUB(NOW(), INTERVAL 15 MINUTE)") !== false, 'repository reserva retry atomicamente após 15 minutos');
retryAssert(strpos($model, "IN ('requested','request_failed')") !== false, 'repository limita estados repetíveis');
retryAssert(strpos($controller, 'public function repetirSyncCoexistenceAjax()') !== false && strpos($controller, '\\Core\\Csrf::exigirPost()') !== false, 'operação administrativa explícita exige POST e CSRF');
retryAssert(strpos($controller, "(\$usuario['nivel'] ?? null) !== 'admin'") !== false, 'retry exige administrador');
retryAssert(strpos($worker, 'repetirSyncCoexistence') === false, 'worker não executa retry automático');

echo "Meta Coexistence sync retry tests passed\n";
