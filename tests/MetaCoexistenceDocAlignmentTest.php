<?php

require_once __DIR__ . '/../app/Services/EmbeddedSignupOnboardingMode.php';
require_once __DIR__ . '/../app/Services/MetaCoexistenceSyncService.php';
require_once __DIR__ . '/../app/Services/MetaCoexistenceLifecycleService.php';
require_once __DIR__ . '/../app/Services/MetaWebhookMessageIngestionService.php';

use Services\MetaCoexistenceSyncService;
use Services\MetaCoexistenceLifecycleService;
use Services\MetaWebhookMessageIngestionService;

function c2cAssert($condition, $message){ if(!$condition){ fwrite(STDERR, "FAIL: {$message}\n"); exit(1); } }

class C2CConversationFake
{
    public $rows=[];
    public function buscarOuCriar($cli,$mta,$phone,$name=null,$create=true){ return ($cli*100)+$mta; }
    public function ingerirMensagemIdempotente($mta,array $data,callable $resolver){
        $key=$mta.':'.$data['message_id'];
        if(isset($this->rows[$key])){
            $old=&$this->rows[$key];
            $media=['image','video','document','audio','sticker'];
            if(($old['origem']??null)==='history' && ($old['tipo']??null)==='media_placeholder' && in_array($data['tipo'],$media,true)){
                $old['tipo']=$data['tipo']; $old['texto']=$data['texto']; $old['retorno']=$data['retorno'];
                return ['id'=>$old['id'],'created'=>false,'enriched'=>true];
            }
            return ['id'=>$old['id'],'created'=>false,'enriched'=>false];
        }
        $data['id']=count($this->rows)+1; $data['conversa_id']=$resolver(); $this->rows[$key]=$data;
        return ['id'=>$data['id'],'created'=>true];
    }
}

function c2cHistory(array $messages){ return ['metadata'=>['phone_number_id'=>'123','display_phone_number'=>'5511888880000'],'history'=>[['metadata'=>['request_id'=>'req-h','phase'=>'messages','chunk_order'=>2,'progress'=>50],'threads'=>[['id'=>'5511999990000','messages'=>$messages]]]]]; }
$account=['MTA_ID'=>9,'CLI_ID'=>4]; $fake=new C2CConversationFake(); $ingestion=new MetaWebhookMessageIngestionService($fake);
$withoutTo=['from'=>'5511888880000','id'=>'out-no-to','timestamp'=>'1750000000','type'=>'text','text'=>['body'=>'out'],'history_context'=>['status'=>'SENT']];
$validTo=$withoutTo; $validTo['id']='out-valid-to'; $validTo['to']='5511999990000'; $validTo['history_context']['status']='DELIVERED';
$badTo=$withoutTo; $badTo['id']='out-bad-to'; $badTo['to']='5511777770000';
$in=['from'=>'5511999990000','id'=>'in','timestamp'=>'1750000001','type'=>'text','text'=>['body'=>'in'],'history_context'=>['status'=>'READ']];
$r=$ingestion->processarHistorico(c2cHistory([$withoutTo,$validTo,$badTo,$in]),$account);
c2cAssert($r['criadas']===3 && $r['invalidas']===1,'direção aceita outbound sem to/com to válido e rejeita to inconsistente');
c2cAssert($fake->rows['9:out-no-to']['direcao']==='enviada' && $fake->rows['9:in']['direcao']==='recebida','inbound permanece recebida');

$statuses=['PENDING'=>'pending','SENT'=>'sent','DELIVERED'=>'delivered','READ'=>'read','PLAYED'=>'read','ERROR'=>'failed'];
foreach($statuses as $meta=>$local){ $m=$withoutTo; $m['id']='status-'.$meta; $m['history_context']['status']=$meta; $ingestion->processarHistorico(c2cHistory([$m]),$account); c2cAssert($fake->rows['9:status-'.$meta]['status']===$local,"status {$meta} mapeado"); }
$unknown=$withoutTo; $unknown['id']='unknown'; $unknown['history_context']['status']='MYSTERY';
c2cAssert($ingestion->processarHistorico(c2cHistory([$unknown]),$account)['invalidas']===1,'status desconhecido não vira sent');

$placeholder=$withoutTo; $placeholder['id']='media'; $placeholder['type']='media_placeholder';
$ingestion->processarHistorico(c2cHistory([$placeholder]),$account);
$media=$placeholder; $media['type']='image'; $media['image']=['id'=>'media-id','caption'=>'foto']; $media['history_context']['status']='READ';
$enriched=$ingestion->processarHistorico(c2cHistory([$media]),$account);
c2cAssert($enriched['enriquecidas']===1 && count(array_filter(array_keys($fake->rows),function($k){return strpos($k,'9:media')===0;}))===1,'placeholder é enriquecido sem duplicar');
c2cAssert($fake->rows['9:media']['origem']==='history' && $fake->rows['9:media']['direcao']==='enviada','enriquecimento preserva origem e direção');

class C2CSyncRepo { public $state=[]; public function reservarSyncUmaVez($id,$type){ if(isset($this->state[$type])) return false; return $this->state[$type]='requesting'; } public function confirmarSyncSolicitado($id,$type,$rid){$this->state[$type]=$rid;} public function marcarSyncFalho($id,$type){$this->state[$type]='failed';} }
$calls=[]; $repo=new C2CSyncRepo();
$sync=new MetaCoexistenceSyncService(function($endpoint,$payload,$token,$method)use(&$calls){$calls[]=$payload['sync_type'];return ['request_id'=>'req-'.$payload['sync_type']];},$repo);
$traditional=$sync->iniciar(['MTA_ID'=>1,'MTA_OnboardingType'=>'traditional']); c2cAssert(!$traditional['iniciado'] && !$calls,'traditional nunca chama smb_app_data');
$co=$sync->iniciar(['MTA_ID'=>2,'MTA_OnboardingType'=>'coexistence','MTA_PhoneNumberId'=>'123','MTA_Token'=>'secret']);
c2cAssert($calls===['smb_app_state_sync','history'],'sync ocorre contatos primeiro e histórico depois');
c2cAssert($co['contact_request_id']==='req-smb_app_state_sync' && $co['history_request_id']==='req-history','request ids retornados/persistidos');
$controller=file_get_contents(dirname(__DIR__).'/app/Controllers/ConfiguracaoController.php');
c2cAssert(strpos($controller, '$onboardingType === EmbeddedSignupOnboardingMode::COEXISTENCE && $statusConexao === \'conectado\'')!==false,'Coexistence conectado aciona a orquestração existente');
$sync->iniciar(['MTA_ID'=>2,'MTA_OnboardingType'=>'coexistence','MTA_PhoneNumberId'=>'123','MTA_Token'=>'secret']); c2cAssert(count($calls)===2,'solicitações são one-time');

class C2CLifecycleRepo { public $events=[]; public function atualizarLifecycleCoexistence($id,$event,array $data=[]){$this->events[]=[$id,$event,$data];return true;} }
$lifeRepo=new C2CLifecycleRepo(); $life=new MetaCoexistenceLifecycleService($lifeRepo);
foreach(['PARTNER_REMOVED','ACCOUNT_OFFBOARDED','ACCOUNT_RECONNECTED'] as $event){ c2cAssert($life->processar(['event'=>$event,'reason'=>'reason','initiated_by'=>'business'],['MTA_ID'=>9,'MTA_OnboardingType'=>'coexistence']),"lifecycle {$event}"); }
c2cAssert(!$life->processar(['event'=>'PARTNER_REMOVED'],['MTA_ID'=>10,'MTA_OnboardingType'=>'traditional']),'lifecycle Coexistence não altera conta traditional');
c2cAssert(count($lifeRepo->events)===3,'lifecycle não é mensagem');

$root=dirname(__DIR__); $webhook=file_get_contents($root.'/public/webhook/meta.php'); $queue=file_get_contents($root.'/app/Services/MetaCoexistenceHistoryQueueService.php'); $migration=file_get_contents($root.'/database/migrations/20260812_add_meta_coexistence_sync_operations.sql'); $config=file_get_contents($root.'/config/config.php');
c2cAssert(strpos($webhook,'$historyQueueService->enfileirar')!==false && strpos($webhook,'processarHistorico($value, $metaConta)')===false,'history não é processado inline no webhook');
c2cAssert(strpos($queue,'MCH_RequestId')!==false && strpos($queue,"=== 2593109")!==false && strpos($queue,"'declined'")!==false,'request/progresso e recusa 2593109 persistidos');
c2cAssert(strpos($migration,'MTA_ContactSyncRequestId')!==false && strpos($migration,'MTA_HistoryProgress')!==false && strpos($migration,'meta_coexistence_history_jobs')!==false,'migration cobre sync e fila');
c2cAssert(strpos($webhook,'131060')!==false && strpos($webhook,"\$field === 'account_update'")!==false,'erro esperado e lifecycle reconhecidos');
c2cAssert(strpos($config,"env_valor('META_COEXISTENCE_ENABLED', 'false')")!==false,'flag continua false por padrão');
echo "Meta Coexistence official documentation alignment tests passed\n";
