<?php
require_once __DIR__ . '/../app/Services/MensagemStatusService.php';
function interfaceStatusAssert($c,$m){ if(!$c){ fwrite(STDERR,"FAIL: {$m}\n"); exit(1); } }
$mensagens=[
 ['MSG_ID'=>1,'MSG_Direcao'=>'enviada','MSG_Texto'=>'Saída','MSG_Status'=>'read','MSG_DataMensagem'=>'2026-07-30 09:42:00'],
 ['MSG_ID'=>2,'MSG_Direcao'=>'recebida','MSG_Texto'=>'Entrada','MSG_Status'=>'read','MSG_DataMensagem'=>'2026-07-30 09:43:00'],
 ['MSG_ID'=>3,'MSG_Direcao'=>'enviada','MSG_Texto'=>'Falhou','MSG_Status'=>'failed','MSG_DataMensagem'=>'2026-07-30 09:44:00','MSG_FalhouEm'=>'2026-07-30 09:45:00','MSG_CodigoErro'=>'131026','MSG_MensagemErro'=>'token=segredo Não foi possível entregar'],
 ['MSG_ID'=>4,'MSG_Direcao'=>'enviada','MSG_Texto'=>'Antiga','MSG_Status'=>null,'MSG_DataMensagem'=>'2026-07-30 09:46:00'],
 ['MSG_ID'=>5,'MSG_Direcao'=>'enviada','MSG_Texto'=>'Pendente','MSG_Status'=>'processando','MSG_DataMensagem'=>'2026-07-30 09:47:00'],
 ['MSG_ID'=>6,'MSG_Direcao'=>'enviada','MSG_Texto'=>'Enviada','MSG_Status'=>'sent','MSG_DataMensagem'=>'2026-07-30 09:48:00'],
 ['MSG_ID'=>7,'MSG_Direcao'=>'enviada','MSG_Texto'=>'Entregue','MSG_Status'=>'delivered','MSG_DataMensagem'=>'2026-07-30 09:49:00'],
];
ob_start(); require __DIR__ . '/../app/Views/conversas/partials/mensagens.php'; $html=ob_get_clean();
interfaceStatusAssert(substr_count($html,'data-message-status-id=')===5,'recebida e status ausente não devem exibir indicador');
interfaceStatusAssert(strpos($html,'fa-clock')!==false && strpos($html,'aria-label="Aguardando envio"')!==false,'processando deve exibir relógio');
interfaceStatusAssert(strpos($html,'fa-check')!==false && strpos($html,'aria-label="Enviada"')!==false,'sent deve exibir um check');
interfaceStatusAssert(strpos($html,'mensagem-status-entregue')!==false && strpos($html,'aria-label="Entregue"')!==false,'delivered deve exibir checks cinza');
interfaceStatusAssert(strpos($html,'fa-check-double')!==false && strpos($html,'mensagem-status-lida')!==false && strpos($html,'aria-label="Lida"')!==false,'read deve exibir checks azuis acessíveis');
interfaceStatusAssert(strpos($html,'fa-exclamation-circle')!==false && strpos($html,'mensagem-status-falha')!==false && strpos($html,'Código Meta: 131026')!==false,'failed deve exibir alerta e tooltip');
interfaceStatusAssert(strpos($html,'segredo')===false && strpos($html,'payload')===false && strpos($html,'Authorization')===false,'marcação não deve vazar segredo ou payload');
interfaceStatusAssert(substr_count($html,'mensagem-meta mensagem-meta-saida')===6,'toda mensagem de saída deve ter metadados em container próprio');
interfaceStatusAssert(strpos($html,'mensagem-horario')!==false,'horário deve permanecer junto ao indicador');
$view=file_get_contents(__DIR__ . '/../app/Views/conversas/index.php'); $css=file_get_contents(__DIR__ . '/../public/assets/css/style.css');
interfaceStatusAssert(substr_count($view,'setInterval(')===1,'polling não deve criar múltiplos timers');
interfaceStatusAssert(strpos($view,'atualizarIndicadoresStatus(retorno.statuses || [])')!==false && strpos($view,'data-message-status-id')!==false,'polling deve atualizar mensagem específica');
$inicio=strpos($view,'function atualizarIndicadoresStatus'); $fim=strpos($view,'function iniciarAtualizacaoAutomatica',$inicio); $funcao=substr($view,$inicio,$fim-$inicio);
interfaceStatusAssert(strpos($funcao,'.html(')===false && strpos($funcao,'scroll')===false,'atualização de status não deve recriar conversa nem mover rolagem');
foreach(['mensagem-status-enviada','mensagem-status-entregue','mensagem-status-lida','mensagem-status-falha'] as $classe){ interfaceStatusAssert(strpos($css,'.'.$classe)!==false,'CSS ausente: '.$classe); }
interfaceStatusAssert(strpos($css,'.mensagem-meta-saida')!==false && strpos($css,'justify-content: flex-end')!==false && strpos($css,'width: 100%')!==false,'rodapé de saída deve ocupar a bolha e alinhar à direita');
interfaceStatusAssert(strpos($css,'float:')===false && strpos($css,'position: absolute')===false,'alinhamento não deve usar float ou posição absoluta');
echo "ConversaStatusInterfaceTest OK\n";
