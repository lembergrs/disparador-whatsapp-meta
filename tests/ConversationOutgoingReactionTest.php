<?php

function centralReactionAssert($condition, $message)
{
    if(!$condition){
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$root = dirname(__DIR__);
$controller = file_get_contents($root . '/app/Controllers/ConversaController.php');
$meta = file_get_contents($root . '/app/Services/MetaService.php');
$painel = file_get_contents($root . '/app/Views/conversas/partials/painel.php');
$mensagens = file_get_contents($root . '/app/Views/conversas/partials/mensagens.php');
$index = file_get_contents($root . '/app/Views/conversas/index.php');
$model = file_get_contents($root . '/app/Models/Conversa.php');

centralReactionAssert(strpos($meta, "public function enviarReaction") !== false, 'MetaService deve expor envio de reaction');
centralReactionAssert(strpos($meta, "'type' => 'reaction'") !== false, 'payload deve usar tipo reaction');
centralReactionAssert(strpos($meta, "'message_id' => $messageId") !== false, 'reaction deve referenciar wamid original');

centralReactionAssert(strpos($controller, 'public function reagirAjax()') !== false, 'controller deve expor endpoint AJAX');
centralReactionAssert(strpos($controller, '$this->validarCsrfAjax()') !== false, 'endpoint deve validar CSRF');
centralReactionAssert(strpos($controller, 'buscarMensagemAcessivel') !== false, 'endpoint deve validar acesso à mensagem');
centralReactionAssert(strpos($controller, "(int) ($mensagem['CVS_ID'] ?? 0) !== $conversaId") !== false, 'mensagem deve pertencer à conversa');
centralReactionAssert(strpos($controller, "'tipo'=>'reaction'") !== false, 'reaction enviada deve ser persistida');
centralReactionAssert(strpos($controller, "'reacao_message_id'=>$metaMessageId") !== false, 'alvo da reaction deve ser persistido');

centralReactionAssert(strpos($painel, 'id="btnEmojiMensagem"') !== false, 'composer deve ter botão de emoji');
centralReactionAssert(strpos($painel, 'js-inserir-emoji') !== false, 'composer deve renderizar seletor de emoji');
centralReactionAssert(strpos($mensagens, 'js-abrir-reactions') !== false, 'mensagem com wamid deve permitir reaction');
centralReactionAssert(strpos($index, 'js-enviar-reaction') !== false, 'frontend deve enviar reaction');
centralReactionAssert(strpos($index, "conversa/reagirAjax") !== false, 'frontend deve usar endpoint dedicado');

centralReactionAssert(strpos($model, "if($tipo === 'reaction'){") !== false, 'reaction não deve substituir resumo da conversa');

echo "Central emoji/reaction static tests passed\n";
