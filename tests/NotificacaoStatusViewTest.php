<?php
$root = dirname(__DIR__);
$controller = file_get_contents($root . '/app/Controllers/NotificacaoController.php');
$view = file_get_contents($root . '/app/Views/notificacoes/index.php');
$css = file_get_contents($root . '/public/assets/css/style.css');
function notificacaoViewAssert($condicao, $mensagem){ if(!$condicao){ fwrite(STDERR, "FAIL: {$mensagem}\n"); exit(1); } }

notificacaoViewAssert(strpos($controller, 'NotificacaoStatusService::apresentacao')!==false, 'controller deve centralizar a apresentação no service');
foreach(['data-toggle="tooltip"','data-placement="top"','aria-label="','class="sr-only"','aria-hidden="true"'] as $trecho){
    notificacaoViewAssert(strpos($controller, $trecho)!==false, "indicador deve conter {$trecho}");
}
notificacaoViewAssert(strpos($controller, '<dt class="col-sm-4">Status</dt>')!==false && strpos($controller, 'NotificacaoFormatador::status($n[\'NOT_Status\']')!==false, 'detalhes devem preservar status textual');
notificacaoViewAssert(strpos($controller, 'NOT_ProviderMessageId')!==false && strpos($controller, 'NOT_CodigoErro')!==false && strpos($controller, 'NOT_DataEnvio')!==false && strpos($controller, 'NOT_DataLeitura')!==false, 'detalhes devem preservar metadados e datas existentes');
notificacaoViewAssert(strpos($view, 'drawCallback')!==false && strpos($view, '[data-toggle="tooltip"]')!==false && strpos($view, ").tooltip({container: 'body'})")!==false, 'tooltips devem ser reinicializados após atualização assíncrona');
foreach(['notificacao-status-pendente','notificacao-status-enviada','notificacao-status-entregue','notificacao-status-lida','notificacao-status-erro-temporario','notificacao-status-erro-definitivo'] as $classe){
    notificacaoViewAssert(strpos($css, $classe)!==false, "CSS deve definir {$classe}");
}
notificacaoViewAssert(strpos($controller, 'NOT_Dados')!==false && strpos($controller, 'dadosSeguros')!==false, 'detalhes devem continuar sanitizando dados');

echo "NotificacaoStatusViewTest OK\n";
