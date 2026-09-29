<?php

$assert = function($condition, $message){
    if(!$condition){
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$model = file_get_contents(dirname(__DIR__) . '/app/Models/Conversa.php');
$view = file_get_contents(dirname(__DIR__) . '/app/Views/conversas/partials/mensagens.php');

$assert(strpos($model, "MSG_Tipo'] ?? '')) === 'reaction'") !== false, 'Listagem deve identificar reactions.');
$assert(strpos($model, "MSG_ReacaoMessageId") !== false, 'Reaction deve ser associada pelo message_id original.');
$assert(strpos($model, "MSG_ReacaoEmoji") !== false, 'Listagem deve usar o emoji persistido.');
$assert(strpos($model, "unset(\$reacoes[\$chave])") !== false, 'Reaction removida deve apagar o estado visual.');
$assert(strpos($model, "\$resultado[] = \$mensagem") !== false, 'Mensagens normais devem continuar na listagem.');
$assert(strpos($view, "MSG_Reacoes") !== false, 'View deve renderizar reactions associadas.');
$assert(strpos($view, "Reação") !== false, 'Reaction deve possuir indicação acessível.');

echo "Conversation reaction rendering checks passed\n";
