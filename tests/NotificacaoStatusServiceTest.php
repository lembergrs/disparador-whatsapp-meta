<?php
require_once __DIR__ . '/../app/Services/CanalNotificacao.php';
require_once __DIR__ . '/../app/Services/NotificacaoFormatador.php';
require_once __DIR__ . '/../app/Services/MensagemStatusService.php';
require_once __DIR__ . '/../app/Services/NotificacaoStatusService.php';

use Services\CanalNotificacao;
use Services\NotificacaoStatusService;

function notificacaoStatusAssert($condicao, $mensagem){ if(!$condicao){ fwrite(STDERR, "FAIL: {$mensagem}\n"); exit(1); } }

$casos = [
    'pendente'=>['fa-clock','notificacao-status-pendente','Na fila'],
    'enviada'=>['fa-check','notificacao-status-enviada','Enviada'],
    'entregue'=>['fa-check-double','notificacao-status-entregue','Entregue'],
    'lida'=>['fa-check-double','notificacao-status-lida','Lida'],
    'erro_temporario'=>['fa-exclamation-triangle','notificacao-status-erro-temporario','Erro temporário'],
    'erro_definitivo'=>['fa-times-circle','notificacao-status-erro-definitivo','Erro definitivo'],
];
foreach($casos as $status=>$esperado){
    $visual = NotificacaoStatusService::apresentacao($status, CanalNotificacao::WHATSAPP);
    notificacaoStatusAssert($visual['icone']===$esperado[0] && $visual['classe']===$esperado[1] && $visual['rotulo']===$esperado[2], "visual de {$status}");
}

$email = NotificacaoStatusService::apresentacao('enviada', CanalNotificacao::EMAIL);
$whatsapp = NotificacaoStatusService::apresentacao('enviada', CanalNotificacao::WHATSAPP);
notificacaoStatusAssert($email['tooltip']==='Enviada ao provedor de e-mail', 'tooltip de e-mail deve mencionar o provedor');
notificacaoStatusAssert(strpos($email['tooltip'], 'Meta')===false, 'tooltip de e-mail não deve mencionar Meta');
notificacaoStatusAssert($whatsapp['tooltip']==='Enviada para a Meta', 'tooltip de WhatsApp deve mencionar Meta');
notificacaoStatusAssert(NotificacaoStatusService::apresentacao('entregue', CanalNotificacao::EMAIL)['status']==='desconhecido', 'e-mail não deve inferir entrega');
$desconhecido = NotificacaoStatusService::apresentacao('legado_desconhecido', CanalNotificacao::WHATSAPP);
notificacaoStatusAssert($desconhecido['icone']==='fa-circle' && $desconhecido['status']==='desconhecido', 'status legado deve ser neutro');
$erro = NotificacaoStatusService::apresentacao('erro_definitivo', CanalNotificacao::WHATSAPP, '131026', 'token=segredo configuração ausente e uma explicação muito longa que não deve dominar a tela');
notificacaoStatusAssert(strpos($erro['tooltip'], 'segredo')===false && mb_strlen($erro['tooltip'])<=97, 'tooltip de erro deve ser curto e sanitizado');
notificacaoStatusAssert(NotificacaoStatusService::podeAvancar('pendente','enviada'), 'pendente deve avançar para enviada');
notificacaoStatusAssert(NotificacaoStatusService::podeAvancar('enviada','entregue'), 'enviada deve avançar para entregue');
notificacaoStatusAssert(NotificacaoStatusService::podeAvancar('enviada','lida'), 'read fora de ordem deve avançar diretamente');
notificacaoStatusAssert(!NotificacaoStatusService::podeAvancar('lida','entregue'), 'lida não deve regredir');
notificacaoStatusAssert(!NotificacaoStatusService::podeAvancar('entregue','enviada'), 'entregue não deve regredir');
notificacaoStatusAssert(NotificacaoStatusService::podeAvancar('enviada','erro_definitivo'), 'falha posterior à aceitação inicial deve ser registrada');

echo "NotificacaoStatusServiceTest OK\n";
