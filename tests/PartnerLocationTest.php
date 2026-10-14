<?php
require_once dirname(__DIR__).'/app/Services/MetaWebhookMessageIngestionService.php';
use Services\MetaWebhookMessageIngestionService;

function locationAssert($condition, $message){
    if(!$condition){fwrite(STDERR, "FAIL: ".$message."\n");exit(1);}
}

class LocationConversationStub {
    public $saved = [];
    public function ingerirMensagemIdempotente($metaId, $data, $resolve){
        $this->saved[] = $data;
        $resolve();
        return ['id'=>91,'created'=>true];
    }
    public function buscarOuCriar(){return 42;}
}

$model = new LocationConversationStub();
$received = [];
$service = new MetaWebhookMessageIngestionService($model, null, null,
    function($account,$data,$persistence) use (&$received){$received[]=$data;}
);
$account=['MTA_ID'=>9,'CLI_ID'=>10];
$message=[
    'id'=>'wamid.location.test','from'=>'5541999999999','type'=>'location',
    'timestamp'=>'1792000000',
    'location'=>['latitude'=>-25.4284,'longitude'=>-49.2733,'name'=>'Centro','address'=>'Curitiba']
];
$result=$service->processarInbound(['messages'=>[$message]],$account);
locationAssert($result['criadas']===1,'localizacao recebida deve ser persistida');
locationAssert($model->saved[0]['tipo']==='location','tipo de mensagem deve ser location');
locationAssert(strpos($model->saved[0]['texto'],'[LOCATION] ')===0,'historico deve conter localizacao');
locationAssert($received[0]['location']['latitude']===-25.4284,'webhook deve receber latitude');
locationAssert($received[0]['location']['longitude']===-49.2733,'webhook deve receber longitude');
locationAssert($received[0]['location']['name']==='Centro','webhook deve receber nome');
locationAssert($received[0]['media_id']===null,'localizacao nao deve ser tratada como midia');

$ref=new ReflectionMethod(MetaWebhookMessageIngestionService::class,'dadosLocalizacao');
$ref->setAccessible(true);
locationAssert($ref->invoke($service,['type'=>'location','location'=>['latitude'=>91,'longitude'=>0]],'location')===null,'latitude fora do limite deve ser rejeitada');
locationAssert($ref->invoke($service,['type'=>'location','location'=>['latitude'=>0,'longitude'=>181]],'location')===null,'longitude fora do limite deve ser rejeitada');
locationAssert($ref->invoke($service,['type'=>'location','location'=>['latitude'=>'NaN','longitude'=>0]],'location')===null,'latitude nao numerica deve ser rejeitada');
locationAssert($ref->invoke($service,['type'=>'location','location'=>['latitude'=>0,'longitude'=>0]],'location')['latitude']===0.0,'coordenada zero deve ser aceita');

echo "Partner location behavior tests passed\n";
