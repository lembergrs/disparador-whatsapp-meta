<?php

defined('BASE_URL') || define('BASE_URL', 'https://disparador.test');
require_once __DIR__ . '/../app/Services/CanalNotificacao.php';
require_once __DIR__ . '/../app/Services/EventoNotificacao.php';
require_once __DIR__ . '/../app/Services/WhatsAppInstitucionalService.php';
require_once __DIR__ . '/../app/Services/NotificacaoService.php';

use Services\CanalNotificacao;
use Services\EventoNotificacao;
use Services\NotificacaoService;
use Services\WhatsAppInstitucionalService;

function fluxoWaAssert($cond,$msg){ if(!$cond){ fwrite(STDERR,"FAIL: {$msg}\n"); exit(1); } }
class ConfigWaFake { public function canaisEfetivos(array $c){ return $c['eventos']; } }
class RegistroWaFake {
    public $rows=[]; public $finalizados=[];
    public function criar(array $d){ $this->rows['email-'.count($this->rows)]=['NOT_ID'=>count($this->rows)+1]+$d; return count($this->rows); }
    public function finalizar($id,array $resultado){ $this->finalizados[]=$resultado; return true; }
    public function reservarIdempotente(array $d){ if(!isset($this->rows[$d['chave']])) $this->rows[$d['chave']]=['NOT_ID'=>count($this->rows)+1,'NOT_Status'=>'pendente']+$d; return $this->rows[$d['chave']]; }
    public function marcarProcessando($id){ foreach($this->rows as &$r){ if($r['NOT_ID']===$id && $r['NOT_Status']==='pendente'){ $r['NOT_Status']='processando'; return true; } } return false; }
    public function finalizarWhatsApp($id,array $resultado){ foreach($this->rows as &$r){ if($r['NOT_ID']===$id) $r['NOT_Status']=!empty($resultado['sucesso'])?'enviada':$resultado['status']; } $this->finalizados[]=$resultado; return true; }
}
class CanalWaFake extends WhatsAppInstitucionalService {
    public $chamadas=0; public $preparados=[]; public function __construct(){}
    public function preparar($evento,array $contexto){ $this->preparados[]=compact('evento','contexto'); return ['sucesso'=>true,'telefone'=>'5511999991234','template'=>self::template($evento),'parametros'=>$evento===EventoNotificacao::BOAS_VINDAS?[$contexto['nome']]:[]]; }
    public function enviarPreparado(array $p){ $this->chamadas++; return ['sucesso'=>true,'status'=>'enviada','message_id'=>'wamid.flow']; }
}
class EmailCanalFake {
    public $chamadas=0;
    public function preparar($evento,array $contexto){ return ['assunto'=>'Bem-vindo','html'=>'ok','texto'=>'ok']; }
    public function enviar(){ $this->chamadas++; return ['sucesso'=>true,'status'=>'enviada']; }
}
$model=new RegistroWaFake(); $wa=new CanalWaFake();
$service=new NotificacaoService([CanalNotificacao::WHATSAPP=>$wa],$model,['eventos'=>[EventoNotificacao::BOAS_VINDAS=>[CanalNotificacao::WHATSAPP]]],new ConfigWaFake());
$cliente=['CLI_ID'=>9,'CLI_Nome'=>'Ana','CLI_Telefone'=>'(11) 99999-1234','MTA_Token'=>'TOKEN_CLIENTE','MTA_PhoneNumberId'=>'numero-cliente'];
$r1=$service->disparar(EventoNotificacao::BOAS_VINDAS,$cliente); $r2=$service->disparar(EventoNotificacao::BOAS_VINDAS,$cliente);
fluxoWaAssert($wa->chamadas===1 && count($model->rows)===1,'evento repetido deve manter uma reserva e um envio');
fluxoWaAssert($model->rows['cliente:9:whatsapp:boas_vindas']['destino']==='5511999991234','destino deve ser telefone de contato');
fluxoWaAssert($model->rows['cliente:9:whatsapp:boas_vindas']['template']==='boas_vindas_cadastro','registro deve conter template');
fluxoWaAssert($wa->preparados[0]['contexto']['nome']==='Ana' && $wa->preparados[0]['contexto']['telefone']==='(11) 99999-1234','contexto deve usar nome e telefone do cliente');
fluxoWaAssert(!isset($wa->preparados[0]['contexto']['MTA_Token']) && !isset($wa->preparados[0]['contexto']['MTA_PhoneNumberId']),'credenciais Meta do cliente não devem chegar ao canal');
$off=new CanalWaFake(); $offService=new NotificacaoService([CanalNotificacao::WHATSAPP=>$off],new RegistroWaFake(),['eventos'=>[EventoNotificacao::META_CONECTADA=>[]]],new ConfigWaFake());
$offService->disparar(EventoNotificacao::META_CONECTADA,$cliente);
fluxoWaAssert($off->chamadas===0,'canal desativado não deve chamar Meta');
fluxoWaAssert(($r1['resultados']['whatsapp']['message_id']??'')==='wamid.flow' && !empty($r2['resultados']['whatsapp']['sucesso']),'histórico deve preservar resultado e duplicata enviada');
$email=new EmailCanalFake(); $waFalha=new CanalWaFake();
$independente=new NotificacaoService([CanalNotificacao::EMAIL=>$email,CanalNotificacao::WHATSAPP=>$waFalha],new RegistroWaFake(),['eventos'=>[EventoNotificacao::BOAS_VINDAS=>[CanalNotificacao::EMAIL,CanalNotificacao::WHATSAPP]]],new ConfigWaFake());
$independente->disparar(EventoNotificacao::BOAS_VINDAS,$cliente);
fluxoWaAssert($email->chamadas===1 && $waFalha->chamadas===1,'e-mail e WhatsApp devem ser processados como canais independentes');
$controller=file_get_contents(__DIR__ . '/../app/Controllers/ConfiguracaoController.php');
$trialPos=strpos($controller, 'iniciarTrialSePendente($clienteId)');
$waPos=strpos($controller, 'dispararMetaConectada($clienteId, CanalNotificacao::WHATSAPP)');
fluxoWaAssert($trialPos!==false && $waPos>$trialPos && strpos($controller, 'dispararMetaConectada($clienteId, CanalNotificacao::EMAIL)')!==false,'WhatsApp Meta conectada deve ocorrer após trial/persistência, preservando e-mail existente');

echo "NotificacaoWhatsAppFlowTest OK\n";
