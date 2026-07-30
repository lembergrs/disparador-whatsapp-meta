<?php
require_once __DIR__ . '/../app/Services/MensagemStatusService.php';
use Services\MensagemStatusService;
function statusAssert($c,$m){ if(!$c){ fwrite(STDERR,"FAIL: {$m}\n"); exit(1); } }
foreach(['pendente'=>'pending','fila'=>'pending','aguardando_confirmacao'=>'pending','processando'=>'processing','enviado'=>'sent','sent'=>'sent','entregue'=>'delivered','delivered'=>'delivered','lido'=>'read','read'=>'read','erro'=>'failed','failed'=>'failed'] as $entrada=>$esperado){ statusAssert(MensagemStatusService::normalizar($entrada)===$esperado,'normalização: '.$entrada); }
statusAssert(MensagemStatusService::normalizar('legado_desconhecido')===null,'status desconhecido deve ser neutro');
statusAssert(MensagemStatusService::podeAvancar('pendente','sent'),'pendente deve avançar para sent');
statusAssert(MensagemStatusService::podeAvancar('sent','delivered'),'sent deve avançar para delivered');
statusAssert(MensagemStatusService::podeAvancar('delivered','read'),'delivered deve avançar para read');
statusAssert(!MensagemStatusService::podeAvancar('read','delivered'),'read não pode regredir');
statusAssert(!MensagemStatusService::podeAvancar('delivered','sent'),'delivered não pode regredir');
statusAssert(MensagemStatusService::podeAvancar('processing','failed') && !MensagemStatusService::podeAvancar('sent','failed'),'falha somente antes da confirmação sent');
$esperados=['pending'=>['fa-clock','Aguardando envio','mensagem-status-pendente'],'sent'=>['fa-check','Enviada','mensagem-status-enviada'],'delivered'=>['fa-check-double','Entregue','mensagem-status-entregue'],'read'=>['fa-check-double','Lida','mensagem-status-lida'],'failed'=>['fa-exclamation-circle','Falha no envio','mensagem-status-falha']];
foreach($esperados as $status=>$partes){ $v=MensagemStatusService::apresentacao($status,'131026','Não entregue'); statusAssert($v['icone']===$partes[0]&&$v['rotulo']===$partes[1]&&$v['classe']===$partes[2],'visual: '.$status); }
$falha=MensagemStatusService::apresentacao('failed','131026','token=segredo Falha segura','2026-07-30 10:20:00');
statusAssert(strpos($falha['tooltip'],'Código Meta: 131026')!==false && strpos($falha['tooltip'],'segredo')===false && strpos($falha['tooltip'],'30/07/2026 10:20')!==false,'falha deve sanitizar e detalhar');
echo "MensagemStatusServiceTest OK\n";
