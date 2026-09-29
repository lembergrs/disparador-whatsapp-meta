<?php

$assert = function($condition, $message){
    if(!$condition){
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$model = file_get_contents(dirname(__DIR__) . '/app/Models/Conversa.php');
$view = file_get_contents(dirname(__DIR__) . '/app/Views/conversas/index.php');
$mensagens = file_get_contents(dirname(__DIR__) . '/app/Views/conversas/partials/mensagens.php');

$assert(strpos($model, 'MAX(m.MSG_AtualizadoEm)') !== false, 'Polling deve detectar alterações nas mensagens, inclusive reactions.');
$assert(strpos($view, '}, 5000);') !== false, 'Conversa ativa deve verificar atualizações em intervalo curto.');
$assert(strpos($mensagens, 'data-ultima-mensagem="1"') !== false, 'Última mensagem visível deve ser identificável.');
$assert(strpos($view, 'scrollIntoView') !== false, 'Atualização deve manter a última mensagem visível.');

echo "Conversation live reaction checks passed\n";
